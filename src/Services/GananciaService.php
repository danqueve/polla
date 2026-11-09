<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;
use Throwable;

/**
 * Ganancias de la empresa por ciclo, y su liquidacion (el retiro).
 *
 * De cada jugada, el porcentaje de gastos (hoy 40%) es lo que le queda a
 * la empresa. Ya se guarda jugada por jugada en jugadas.aporte_gastos con
 * el porcentaje vigente EN ESE MOMENTO, asi que cambiar el reparto en
 * Configuracion no reescribe la historia: cada ciclo suma lo que de
 * verdad se separo en cada jugada.
 *
 * La ganancia de un ciclo es la suma de aporte_gastos de sus jugadas
 * pagadas y no anuladas -- el mismo criterio que
 * ReporteService::condicionesJugadas(), asi las cifras coinciden con las
 * de Reportes. Es el 40% BRUTO: no descuenta las comisiones de referidos
 * ni el piso garantizado del premio.
 *
 * Liquidar es un libro mayor, igual que LiquidacionService con las
 * comisiones: el pendiente de un ciclo es su ganancia ACTUAL menos lo ya
 * liquidado (sin contar las anuladas). Nada queda congelado: si un ciclo
 * cambia despues de liquidado, la diferencia aparece sola como pendiente.
 *
 * Solo se liquida un ciclo CERRADO (con o sin ganador): mientras esta
 * abierto el numero todavia puede moverse. Exclusivo del administrador;
 * los handlers lo protegen con requireAdmin().
 *
 * Los montos se suman en centavos enteros: acumular floats de a muchos
 * ciclos dejaba residuos tipo 21831.599999 que hacian que las tarjetas
 * no coincidieran con la tabla.
 */
class GananciaService
{
    private const LARGO_MAXIMO_NOTA = 200;

    /** Estados en los que el pozo ya no se mueve y se puede liquidar. */
    private const ESTADOS_CERRADOS = [
        CicloService::ESTADO_CON_GANADOR,
        CicloService::ESTADO_SIN_GANADOR,
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    // ── Lectura ─────────────────────────────────────────────

    /**
     * Una fila por ciclo que tenga jugadas (o liquidaciones), de la mas
     * nueva a la mas vieja.
     *
     * @param string|null $tipo 'semanal' | 'sabado' | null (los dos).
     * @return array<int,array<string,mixed>>
     */
    public function porCiclo(?string $tipo = null): array
    {
        $condiciones = ['(COALESCE(j.jugadas, 0) > 0 OR COALESCE(l.liquidado, 0) > 0)'];
        $params      = [];
        if ($tipo !== null) {
            $condiciones[]   = 'cl.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        return $this->filas($condiciones, $params);
    }

    /**
     * El mismo detalle para UN ciclo, exista o no movimiento: la pantalla
     * de un ciclo cerrado sin jugadas tiene que poder abrirse igual.
     *
     * @return array<string,mixed>|null
     */
    public function deCiclo(int $cicloId): ?array
    {
        return $this->filas(['cl.id = :id'], [':id' => $cicloId])[0] ?? null;
    }

    /**
     * Las cuatro cifras de arriba de la pantalla, calculadas con las
     * MISMAS filas que la tabla (porCiclo()): lo que suman las tarjetas es
     * exactamente lo que suma la tabla, sin una segunda consulta que
     * pueda desalinearse.
     *
     * - pendiente: lo que "Liquidar todo" liquidaria hoy -- ciclos
     *   cerrados con saldo a favor. Un ciclo liquidado de mas no resta.
     * - en_curso: el 40% que se va juntando en los ciclos que siguen abiertos.
     *
     * @return array{pendiente:float, liquidado:float, en_curso:float, recaudado:float,
     *               ganancia:float, al_pozo:float, ciclos_pendientes:int, jugadas:int}
     */
    public function resumen(?string $tipo = null): array
    {
        return self::resumirFilas($this->porCiclo($tipo));
    }

    /**
     * Lo mismo que resumen(), sobre filas que la pantalla ya tiene (las de
     * porCiclo()): no repite la consulta y garantiza que las tarjetas
     * sumen exactamente lo que muestra la tabla.
     *
     * @param array<int,array<string,mixed>> $filas Salida de porCiclo().
     * @return array{pendiente:float, liquidado:float, en_curso:float, recaudado:float,
     *               ganancia:float, al_pozo:float, ciclos_pendientes:int, jugadas:int}
     */
    public static function resumirFilas(array $filas): array
    {
        $pendiente = $liquidado = $enCurso = $recaudado = $ganancia = $alPozo = 0;
        $ciclosPendientes = $jugadas = 0;

        foreach ($filas as $f) {
            $recaudado += self::centavos($f['recaudado']);
            $alPozo    += self::centavos($f['al_pozo']);
            $ganancia  += self::centavos($f['ganancia']);
            $liquidado += self::centavos($f['liquidado']);
            $jugadas   += $f['jugadas'];

            $saldo = self::centavos($f['ganancia']) - self::centavos($f['liquidado']);
            if ($saldo <= 0) {
                continue;
            }
            if ($f['cerrado']) {
                $pendiente += $saldo;
                $ciclosPendientes++;
            } else {
                $enCurso += $saldo;
            }
        }

        return [
            'pendiente'         => $pendiente / 100.0,
            'liquidado'         => $liquidado / 100.0,
            'en_curso'          => $enCurso / 100.0,
            'recaudado'         => $recaudado / 100.0,
            'ganancia'          => $ganancia / 100.0,
            'al_pozo'           => $alPozo / 100.0,
            'ciclos_pendientes' => $ciclosPendientes,
            'jugadas'           => $jugadas,
        ];
    }

    /**
     * Liquidaciones, la mas nueva primero; con $cicloId, solo las de ese
     * ciclo. Incluye las anuladas (marcadas), para que se vea el rastro.
     *
     * @return array<int,array<string,mixed>>
     */
    public function historial(?int $cicloId = null, int $limite = 30, ?string $tipo = null): array
    {
        $this->exigirTabla();

        $condiciones = ['1 = 1'];
        $params      = [];
        if ($cicloId !== null) {
            $condiciones[]     = 'g.ciclo_id = :ciclo';
            $params[':ciclo']  = $cicloId;
        }
        if ($tipo !== null) {
            $condiciones[]   = 'cl.tipo = :tipo';
            $params[':tipo'] = $tipo;
        }

        $stmt = $this->db->prepare(
            'SELECT g.id, g.ciclo_id, g.monto, g.nota, g.fecha, g.anulada_en,
                    cl.tipo, cl.numero, cl.fecha_inicio, cl.fecha_fin,
                    u.nombre  AS liquidado_por_nombre,
                    ua.nombre AS anulada_por_nombre
               FROM ganancias_liquidadas g
               JOIN ciclos cl        ON cl.id = g.ciclo_id
               LEFT JOIN usuarios u  ON u.id  = g.liquidado_por
               LEFT JOIN usuarios ua ON ua.id = g.anulada_por
              WHERE ' . implode(' AND ', $condiciones) . '
              ORDER BY g.id DESC
              LIMIT ' . max(1, $limite)
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ── Escritura ───────────────────────────────────────────

    /**
     * Liquida el pendiente ACTUAL de un ciclo cerrado: siempre el total,
     * nunca un monto tipeado.
     *
     * Bloquea la fila del ciclo (FOR UPDATE) y recalcula el pendiente
     * ADENTRO de la transaccion: un doble toque, o dos personas a la
     * vez, hacen que la segunda espere a la primera y se encuentre con
     * "ya esta liquidado" en vez de duplicar el retiro. Los cierres y
     * reaperturas de ciclo (CicloService / SorteoService::corregir())
     * toman el mismo candado, asi que tampoco se cruzan con esto.
     *
     * @return float El monto liquidado.
     * @throws ValidacionException
     */
    public function liquidar(int $cicloId, ?string $nota, int $usuarioId): float
    {
        $nota = $this->limpiarNota($nota);
        $this->exigirTabla();

        $this->db->beginTransaction();
        try {
            $ciclo = $this->bloquearCiclo($cicloId);
            if (!$ciclo) {
                throw ValidacionException::de('Ese ciclo no existe.');
            }
            if (!in_array($ciclo['estado'], self::ESTADOS_CERRADOS, true)) {
                throw ValidacionException::de(
                    'Solo se puede liquidar un ciclo cerrado. Este todavía está en curso: '
                  . 'cuando cierre vas a poder liquidarlo.'
                );
            }

            $pendiente = $this->pendienteCentavos($cicloId);
            if ($pendiente <= 0) {
                throw ValidacionException::de('Este ciclo ya está liquidado: no queda nada pendiente.');
            }

            $this->insertar($cicloId, $pendiente, $nota, $usuarioId);

            $this->db->commit();
            return $pendiente / 100.0;
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Liquida de una vez todos los ciclos cerrados que tengan pendiente
     * (del tipo dado, o de los dos), con la misma nota. Todo o nada.
     *
     * Los ciclos se recorren por id ascendente: dos liquidaciones masivas
     * simultaneas toman los candados en el mismo orden y no se traban
     * entre si.
     *
     * @return array{ciclos:int, monto:float, ids:int[]}
     * @throws ValidacionException si no habia nada para liquidar.
     */
    public function liquidarTodo(?string $tipo, ?string $nota, int $usuarioId): array
    {
        $nota = $this->limpiarNota($nota);
        $this->exigirTabla();

        $this->db->beginTransaction();
        try {
            $ids = $this->idsCerrados($tipo);

            $total      = 0;
            $liquidados = [];
            foreach ($ids as $cicloId) {
                // Se vuelve a mirar bajo candado: entre la lista y este
                // punto un ciclo pudo reabrirse por una correccion.
                $ciclo = $this->bloquearCiclo($cicloId);
                if (!$ciclo || !in_array($ciclo['estado'], self::ESTADOS_CERRADOS, true)) {
                    continue;
                }

                $pendiente = $this->pendienteCentavos($cicloId);
                if ($pendiente <= 0) {
                    continue;
                }

                $this->insertar($cicloId, $pendiente, $nota, $usuarioId);
                $total       += $pendiente;
                $liquidados[] = $cicloId;
            }

            if (!$liquidados) {
                throw ValidacionException::de('No hay ganancias pendientes de ciclos cerrados para liquidar.');
            }

            $this->db->commit();
            return ['ciclos' => count($liquidados), 'monto' => $total / 100.0, 'ids' => $liquidados];
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    /**
     * Anula una liquidacion (fue un error): el monto vuelve a figurar como
     * pendiente de su ciclo. No borra la fila -- queda quien la hizo, cuando
     * y quien la anulo.
     *
     * @return array{ciclo_id:int, monto:float}
     * @throws ValidacionException
     */
    public function anular(int $liquidacionId, int $usuarioId): array
    {
        $this->exigirTabla();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'SELECT id, ciclo_id, monto, anulada_en FROM ganancias_liquidadas WHERE id = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $liquidacionId]);
            $fila = $stmt->fetch();

            if (!$fila) {
                throw ValidacionException::de('Esa liquidación no existe.');
            }
            if ($fila['anulada_en'] !== null) {
                throw ValidacionException::de('Esa liquidación ya estaba anulada.');
            }

            $this->db->prepare(
                'UPDATE ganancias_liquidadas SET anulada_en = NOW(), anulada_por = :usuario WHERE id = :id'
            )->execute([':usuario' => $usuarioId, ':id' => $liquidacionId]);

            $this->db->commit();
            return ['ciclo_id' => (int) $fila['ciclo_id'], 'monto' => (float) $fila['monto']];
        } catch (Throwable $e) {
            $this->revertir();
            throw $e;
        }
    }

    // ── Internos ────────────────────────────────────────────

    /**
     * @param string[]             $condiciones WHERE ya armado (alias cl, j, l).
     * @param array<string,mixed>  $params
     * @return array<int,array<string,mixed>>
     */
    private function filas(array $condiciones, array $params): array
    {
        $this->exigirTabla();

        // Cada tabla se agrega UNA vez por ciclo antes de unirse: unir
        // jugadas con liquidaciones directamente multiplicaria las sumas
        // (cada liquidacion repetiria todas las jugadas del ciclo).
        $stmt = $this->db->prepare(
            "SELECT cl.id, cl.tipo, cl.numero, cl.fecha_inicio, cl.fecha_fin, cl.estado, cl.fecha_cierre,
                    COALESCE(j.jugadas, 0)   AS jugadas,
                    COALESCE(j.recaudado, 0) AS recaudado,
                    COALESCE(j.al_pozo, 0)   AS al_pozo,
                    COALESCE(j.ganancia, 0)  AS ganancia,
                    COALESCE(l.liquidado, 0) AS liquidado,
                    l.ultima                 AS ultima_liquidacion
               FROM ciclos cl
               LEFT JOIN (
                    SELECT ciclo_id,
                           COUNT(*)           AS jugadas,
                           SUM(importe)       AS recaudado,
                           SUM(aporte_pozo)   AS al_pozo,
                           SUM(aporte_gastos) AS ganancia
                      FROM jugadas
                     WHERE estado <> 'anulada' AND pagada = 1
                     GROUP BY ciclo_id
               ) j ON j.ciclo_id = cl.id
               LEFT JOIN (
                    SELECT ciclo_id, SUM(monto) AS liquidado, MAX(fecha) AS ultima
                      FROM ganancias_liquidadas
                     WHERE anulada_en IS NULL
                     GROUP BY ciclo_id
               ) l ON l.ciclo_id = cl.id
              WHERE " . implode(' AND ', $condiciones) . '
              ORDER BY cl.fecha_inicio DESC, cl.id DESC'
        );
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$f) {
            $ganancia  = self::centavos($f['ganancia']);
            $liquidado = self::centavos($f['liquidado']);
            $saldo     = $ganancia - $liquidado;
            $cerrado   = in_array($f['estado'], self::ESTADOS_CERRADOS, true);

            $f['id']        = (int) $f['id'];
            $f['numero']    = (int) $f['numero'];
            $f['jugadas']   = (int) $f['jugadas'];
            $f['recaudado'] = self::pesos($f['recaudado']);
            $f['al_pozo']   = self::pesos($f['al_pozo']);
            $f['ganancia']  = $ganancia / 100.0;
            $f['liquidado'] = $liquidado / 100.0;
            $f['pendiente'] = $saldo / 100.0;
            $f['cerrado']   = $cerrado;
            // Liquidable: cerrado y con saldo a favor. Un ciclo "liquidado de
            // mas" (saldo negativo: bajo la ganancia despues de liquidar) no
            // se puede liquidar y la pantalla lo avisa.
            $f['liquidable']       = $cerrado && $saldo > 0;
            $f['sobre_liquidado']  = $saldo < 0;
        }
        unset($f);

        return $filas;
    }

    /** Ids de los ciclos cerrados (del tipo dado), por id ascendente. @return int[] */
    private function idsCerrados(?string $tipo): array
    {
        $marcas = implode(',', array_fill(0, count(self::ESTADOS_CERRADOS), '?'));
        $params = self::ESTADOS_CERRADOS;
        $sql    = "SELECT id FROM ciclos WHERE estado IN ($marcas)";
        if ($tipo !== null) {
            $sql    .= ' AND tipo = ?';
            $params[] = $tipo;
        }
        $sql .= ' ORDER BY id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return array{id:mixed, estado:string}|null */
    private function bloquearCiclo(int $cicloId): ?array
    {
        $stmt = $this->db->prepare('SELECT id, estado FROM ciclos WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $cicloId]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Ganancia actual menos lo liquidado vigente, en centavos, con la resta
     * hecha en SQL (DECIMAL exacto). Solo tiene sentido dentro de la
     * transaccion de liquidar(), despues de bloquear el ciclo.
     */
    private function pendienteCentavos(int $cicloId): int
    {
        // Un placeholder por aparicion: sin emulacion de prepares PDO no
        // deja repetir el mismo nombre en una sentencia.
        $stmt = $this->db->prepare(
            "SELECT (SELECT COALESCE(SUM(aporte_gastos), 0) FROM jugadas
                      WHERE ciclo_id = :ciclo_jugadas AND estado <> 'anulada' AND pagada = 1)
                  - (SELECT COALESCE(SUM(monto), 0) FROM ganancias_liquidadas
                      WHERE ciclo_id = :ciclo_liquidado AND anulada_en IS NULL)"
        );
        $stmt->execute([':ciclo_jugadas' => $cicloId, ':ciclo_liquidado' => $cicloId]);

        return self::centavos($stmt->fetchColumn());
    }

    private function insertar(int $cicloId, int $centavos, ?string $nota, int $usuarioId): void
    {
        $this->db->prepare(
            'INSERT INTO ganancias_liquidadas (ciclo_id, monto, nota, liquidado_por)
             VALUES (:ciclo, :monto, :nota, :usuario)'
        )->execute([
            ':ciclo'   => $cicloId,
            ':monto'   => number_format($centavos / 100, 2, '.', ''),
            ':nota'    => $nota,
            ':usuario' => $usuarioId,
        ]);
    }

    private function revertir(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /** Recorta; vacio pasa a null. @throws ValidacionException si es demasiado larga. */
    private function limpiarNota(?string $nota): ?string
    {
        $nota = trim((string) $nota);
        if ($nota === '') {
            return null;
        }
        if (mb_strlen($nota) > self::LARGO_MAXIMO_NOTA) {
            throw ValidacionException::de(
                'La nota es demasiado larga (máximo ' . self::LARGO_MAXIMO_NOTA . ' caracteres).'
            );
        }

        return $nota;
    }

    /**
     * Si todavia no se aplico la migracion, un mensaje claro en vez de una
     * pantalla en blanco. Se consulta una sola vez por pedido.
     *
     * @throws ValidacionException
     */
    private function exigirTabla(): void
    {
        static $existe = null;

        if ($existe === null) {
            try {
                $this->db->query('SELECT 1 FROM ganancias_liquidadas LIMIT 1');
                $existe = true;
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S02') {   // 42S02 = tabla inexistente
                    throw $e;
                }
                $existe = false;
            }
        }

        if (!$existe) {
            throw ValidacionException::de(
                'Falta crear la tabla de ganancias. Aplicá la migración '
              . 'db/migrations/2026-11-08_ganancias_liquidadas.sql en la base de datos.'
            );
        }
    }

    /** "21000.00" / 21000.5 / null -> 2100000 (centavos enteros, sin residuos de float). */
    private static function centavos($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    private static function pesos($valor): float
    {
        return self::centavos($valor) / 100.0;
    }
}
