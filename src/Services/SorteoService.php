<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use Polla\Support\ValidacionException;
use Throwable;

/**
 * Carga del extracto de la Nocturna (registrar(), semanal) o de un turno
 * del sabado (registrarTurnoSabado()), y motor de cotejo compartido por
 * los dos.
 *
 * Guardar un sorteo dispara toda la cadena en una sola transaccion:
 * validar, insertar, cotejar contra las jugadas activas del ciclo y,
 * segun el resultado, liquidar el pozo y cerrar la secuencia (semana o
 * sabado).
 *
 * El candado es CicloService::bloquearAbierto($tipo): mientras esta
 * transaccion corre, ningun otro request puede tocar ese ciclo.
 */
class SorteoService
{
    /** Dias con sorteo de la Nocturna (ISO-8601: 1 = lunes). */
    private const DIAS_HABILES = [1, 2, 3, 4, 5];

    private PDO $db;
    private CicloService $ciclos;
    private PozoService $pozo;
    private ParametroService $parametros;

    public function __construct(
        PDO $db,
        CicloService $ciclos,
        PozoService $pozo,
        ParametroService $parametros
    ) {
        $this->db         = $db;
        $this->ciclos     = $ciclos;
        $this->pozo       = $pozo;
        $this->parametros = $parametros;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db, new CicloService($db), new PozoService($db), new ParametroService($db));
    }

    /**
     * Registra el extracto y corre el cotejo.
     *
     * @param string   $fechaCruda   'YYYY-MM-DD' del sorteo.
     * @param string[] $numerosCrudos Los 20 valores del formulario.
     *
     * @return array{
     *     sorteo_id:int, ciclo_id:int, ganadores:array<int,float>,
     *     cerro_ciclo:bool, estado_cierre:?string, pozo_repartido:float,
     *     ciclo_nuevo_id:?int, arrastre:float
     * }
     * @throws ValidacionException
     */
    public function registrar(string $fechaCruda, array $numerosCrudos, ?int $cargadoPor): array
    {
        $fecha   = $this->validarFecha($fechaCruda);
        $numeros = $this->validarNumeros($numerosCrudos);

        $this->db->beginTransaction();
        try {
            // A partir de aca el ciclo es nuestro hasta el commit.
            $ciclo = $this->ciclos->bloquearAbierto(CicloService::TIPO_SEMANAL);
            if (!$ciclo) {
                throw ValidacionException::de(
                    'No hay ningun ciclo abierto. Entrá al tablero para que se abra el de esta semana.'
                );
            }

            $this->validarFechaContraCiclo($fecha, $ciclo);
            $this->validarFechaLibre($fecha);

            $sorteoId = $this->insertarSorteo((int) $ciclo['id'], $fecha, 1, $numeros, $cargadoPor);

            $esUltimoDeLaSecuencia = ($fecha->format('Y-m-d') === $ciclo['fecha_fin']);
            $resultado = $this->cotejarYCerrar(
                $sorteoId,
                $ciclo,
                $esUltimoDeLaSecuencia,
                CicloService::TIPO_SEMANAL
            );

            $this->db->commit();

            return ['sorteo_id' => $sorteoId, 'ciclo_id' => (int) $ciclo['id']] + $resultado;

        } catch (Throwable $e) {
            $this->db->rollBack();

            // Dos supervisores cargando la misma fecha a la vez: el UNIQUE
            // frena al segundo y le damos el mensaje de negocio, no el error crudo.
            if ($e instanceof PDOException && strpos($e->getMessage(), 'uk_sorteos_fecha_turno') !== false) {
                throw ValidacionException::de(
                    'El sorteo del ' . $fecha->format('d/m/Y') . ' ya estaba cargado.'
                );
            }
            throw $e;
        }
    }

    /**
     * Registra el proximo turno (1 a 5) del sabado en curso y corre el
     * mismo motor de cotejo que el semanal.
     *
     * A diferencia de registrar(), no recibe fecha: usa la del ciclo
     * sabado abierto (fecha_inicio === fecha_fin), y el turno se calcula
     * solo contando cuantos sorteos tiene ya cargados ese ciclo.
     *
     * @param string[] $numerosCrudos Los 20 valores del formulario.
     * @return array{
     *     sorteo_id:int, ciclo_id:int, ganadores:array<int,float>,
     *     cerro_ciclo:bool, estado_cierre:?string, pozo_repartido:float,
     *     ciclo_nuevo_id:?int, arrastre:float
     * }
     * @throws ValidacionException
     */
    public function registrarTurnoSabado(array $numerosCrudos, ?int $cargadoPor): array
    {
        $numeros = $this->validarNumeros($numerosCrudos);

        $this->db->beginTransaction();
        try {
            $ciclo = $this->ciclos->bloquearAbierto(CicloService::TIPO_SABADO);
            if (!$ciclo) {
                throw ValidacionException::de(
                    'No hay ningun ciclo de sábado abierto. Entrá al tablero de sábados para que se abra.'
                );
            }

            $turno = $this->proximoTurno((int) $ciclo['id']);
            if ($turno > 5) {
                throw ValidacionException::de('Ya se cargaron los 5 sorteos de este sábado.');
            }

            $fecha    = new DateTimeImmutable($ciclo['fecha_inicio']);
            $sorteoId = $this->insertarSorteo((int) $ciclo['id'], $fecha, $turno, $numeros, $cargadoPor);

            $resultado = $this->cotejarYCerrar($sorteoId, $ciclo, $turno === 5, CicloService::TIPO_SABADO);

            $this->db->commit();

            return ['sorteo_id' => $sorteoId, 'ciclo_id' => (int) $ciclo['id']] + $resultado;

        } catch (Throwable $e) {
            $this->db->rollBack();

            if ($e instanceof PDOException && strpos($e->getMessage(), 'uk_sorteos_fecha_turno') !== false) {
                throw ValidacionException::de('Ese turno ya estaba cargado.');
            }
            throw $e;
        }
    }

    private function proximoTurno(int $cicloId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sorteos WHERE ciclo_id = :ciclo');
        $stmt->execute([':ciclo' => $cicloId]);

        return (int) $stmt->fetchColumn() + 1;
    }

    // ── Cotejo ──────────────────────────────────────────────

    /**
     * Jugadas del ciclo cuyos numeros salieron TODOS en este sorteo.
     *
     * El COUNT(DISTINCT) no es decorativo: el extracto puede repetir un
     * numero entre sus 20 posiciones, y con un COUNT(*) comun esa jugada
     * sumaria 11 coincidencias y quedaria descartada por pasarse. Es decir,
     * la version ingenua no falla de menos: descarta al ganador legitimo.
     *
     * Y el total se compara contra los numeros que esa jugada realmente
     * tiene, no contra un 10 fijo: si algun dia cambia el parametro, las
     * jugadas viejas se siguen juzgando por lo que jugaron.
     *
     * @return int[] Ids de las jugadas ganadoras.
     */
    public function jugadasGanadoras(int $sorteoId, int $cicloId): array
    {
        $stmt = $this->db->prepare(
            "SELECT j.id
               FROM jugadas j
               JOIN jugada_numeros jn ON jn.jugada_id = j.id
               JOIN sorteo_numeros sn ON sn.sorteo_id = :sorteo AND sn.numero = jn.numero
              WHERE j.ciclo_id = :ciclo
                AND j.estado   = 'activa'
                AND j.pagada   = 1
              GROUP BY j.id
             HAVING COUNT(DISTINCT jn.numero)
                  = (SELECT COUNT(*) FROM jugada_numeros x WHERE x.jugada_id = j.id)
              ORDER BY j.id ASC"
        );
        $stmt->execute([':sorteo' => $sorteoId, ':ciclo' => $cicloId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Corre el cotejo y decide que hacer con el ciclo.
     *
     * $esUltimoDeLaSecuencia le dice si este sorteo es el ultimo posible
     * de su ciclo sin ganador (el viernes del ciclo semanal, o el turno 5
     * del ciclo sabado) — a partir de ahi, sin ganador, el pozo arrastra.
     *
     * @return array{ganadores:array<int,float>, cerro_ciclo:bool, estado_cierre:?string,
     *               pozo_repartido:float, ciclo_nuevo_id:?int, arrastre:float}
     */
    private function cotejarYCerrar(
        int $sorteoId,
        array $ciclo,
        bool $esUltimoDeLaSecuencia,
        string $tipo
    ): array {
        $cicloId   = (int) $ciclo['id'];
        $ganadoras = $this->jugadasGanadoras($sorteoId, $cicloId);

        $base = [
            'ganadores'      => [],
            'cerro_ciclo'    => false,
            'estado_cierre'  => null,
            'pozo_repartido' => 0.0,
            'ciclo_nuevo_id' => null,
            'arrastre'       => 0.0,
        ];

        $premioBase = $tipo === CicloService::TIPO_SABADO
            ? $this->parametros->premioBaseSabado()
            : $this->parametros->premioBase();

        // ── Hay ganador: se corta la secuencia (semana o sabado) ────
        if ($ganadoras) {
            $this->marcarEstado($ganadoras, 'ganadora');

            // Fase 7: el piso garantizado se aplica en cada ciclo, sin
            // excepcion. Se lee el premio_base VIGENTE justo en este
            // momento, no el que estaba cuando se abrio el ciclo.
            $premios = $this->pozo->liquidar($cicloId, $sorteoId, $ganadoras, $premioBase);
            $repartido = array_sum($premios);

            // Las que no ganaron quedan cerradas junto con el ciclo.
            $this->marcarRestantesPerdedoras($cicloId);

            $this->ciclos->cerrar($cicloId, CicloService::ESTADO_CON_GANADOR);

            // El pozo se repartio entero: el ciclo nuevo arranca en $0.
            $nuevoId = $this->ciclos->abrirSiguiente($tipo, 0.0);

            return [
                'ganadores'      => $premios,
                'cerro_ciclo'    => true,
                'estado_cierre'  => CicloService::ESTADO_CON_GANADOR,
                'pozo_repartido' => $repartido,
                'ciclo_nuevo_id' => $nuevoId,
                'arrastre'       => 0.0,
            ];
        }

        // ── Sin ganador y todavia quedan sorteos: no pasa nada ──
        if (!$esUltimoDeLaSecuencia) {
            return $base;
        }

        // ── Sin ganador y era el ultimo de la secuencia: cierra ──
        // El pozo no se pierde: pasa entero al ciclo siguiente.
        $this->marcarRestantesPerdedoras($cicloId);

        $arrastre = $this->pozo->montoAcumulado($cicloId);

        $this->ciclos->cerrar($cicloId, CicloService::ESTADO_SIN_GANADOR);
        $nuevoId = $this->ciclos->abrirSiguiente($tipo, $arrastre);

        return [
            'ganadores'      => [],
            'cerro_ciclo'    => true,
            'estado_cierre'  => CicloService::ESTADO_SIN_GANADOR,
            'pozo_repartido' => 0.0,
            'ciclo_nuevo_id' => $nuevoId,
            'arrastre'       => $arrastre,
        ];
    }

    /** @param int[] $jugadaIds */
    private function marcarEstado(array $jugadaIds, string $estado): void
    {
        if (!$jugadaIds) {
            return;
        }
        // Los ids salen de una consulta propia, pero igual van por placeholder.
        $marcas = implode(',', array_fill(0, count($jugadaIds), '?'));
        $stmt = $this->db->prepare("UPDATE jugadas SET estado = ? WHERE id IN ($marcas)");
        $stmt->execute(array_merge([$estado], $jugadaIds));
    }

    private function marcarRestantesPerdedoras(int $cicloId): void
    {
        $this->db->prepare(
            "UPDATE jugadas SET estado = 'perdedora'
              WHERE ciclo_id = :ciclo AND estado = 'activa'"
        )->execute([':ciclo' => $cicloId]);
    }

    // ── Validaciones ────────────────────────────────────────

    /** @throws ValidacionException */
    private function validarFecha(string $cruda): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', trim($cruda));
        if (!$fecha) {
            throw ValidacionException::de('Elegí la fecha del sorteo.');
        }

        if (!in_array((int) $fecha->format('N'), self::DIAS_HABILES, true)) {
            throw ValidacionException::de(
                'La Nocturna sortea de lunes a viernes. El ' . $fecha->format('d/m/Y') . ' no tuvo sorteo.'
            );
        }

        $hoy = new DateTimeImmutable('today');
        if ($fecha > $hoy) {
            throw ValidacionException::de('No se puede cargar un sorteo que todavia no ocurrio.');
        }

        return $fecha;
    }

    /**
     * La fecha tiene que caer dentro del ciclo abierto.
     *
     * Esta unica regla resuelve los dos casos raros:
     *
     *  - Corte a mitad de semana. Gano alguien el martes, el ciclo nuevo
     *    arranca el lunes siguiente. El miercoles queda antes de ese inicio
     *    y se rechaza, que es lo que pide el punto 3.2: los sorteos que
     *    quedan de esa semana ya no participan.
     *
     *  - Sorteo atrasado. Es lunes y falto cargar el viernes pasado. Ese
     *    viernes sigue dentro del ciclo, que nunca se cerro, asi que entra
     *    normal y su carga es justamente la que cierra la semana.
     *
     * @throws ValidacionException
     */
    private function validarFechaContraCiclo(DateTimeImmutable $fecha, array $ciclo): void
    {
        $inicio = new DateTimeImmutable($ciclo['fecha_inicio']);
        $fin    = new DateTimeImmutable($ciclo['fecha_fin']);

        if ($fecha < $inicio) {
            $corte = $this->ultimoCicloCerradoConGanador();
            if ($corte) {
                throw ValidacionException::de(
                    'Ese sorteo no participa: el ciclo ' . $corte['numero'] . ' se corto el '
                    . (new DateTimeImmutable($corte['fecha_cierre']))->format('d/m')
                    . ' porque hubo ganador. El proximo sorteo que juega es el del '
                    . $inicio->format('d/m/Y') . '.'
                );
            }
            throw ValidacionException::de(
                'Ese sorteo es anterior al ciclo abierto, que arranca el ' . $inicio->format('d/m/Y') . '.'
            );
        }

        if ($fecha > $fin) {
            throw ValidacionException::de(
                'Todavia falta cargar el sorteo del ' . $fin->format('d/m/Y')
                . ' para cerrar la semana. Cargá ese primero y despues seguí con este.'
            );
        }
    }

    private function ultimoCicloCerradoConGanador(): ?array
    {
        $fila = $this->db->query(
            "SELECT numero, fecha_cierre FROM ciclos
              WHERE estado = '" . CicloService::ESTADO_CON_GANADOR . "'
              ORDER BY numero DESC LIMIT 1"
        )->fetch();

        return $fila ?: null;
    }

    /** @throws ValidacionException */
    private function validarFechaLibre(DateTimeImmutable $fecha): void
    {
        $stmt = $this->db->prepare('SELECT 1 FROM sorteos WHERE fecha = :fecha LIMIT 1');
        $stmt->execute([':fecha' => $fecha->format('Y-m-d')]);

        if ($stmt->fetchColumn()) {
            throw ValidacionException::de(
                'El sorteo del ' . $fecha->format('d/m/Y') . ' ya estaba cargado.'
            );
        }
    }

    /**
     * Normaliza los 20 numeros del extracto.
     *
     * A diferencia de una jugada, aca los repetidos SI valen: la Nocturna
     * puede sacar el mismo numero en dos posiciones distintas. Por eso se
     * devuelve la lista en el orden de sorteo, sin deduplicar.
     *
     * @param string[] $crudos
     * @return int[]
     * @throws ValidacionException
     */
    public function validarNumeros(array $crudos): array
    {
        $esperados = $this->parametros->getInt('numeros_por_sorteo');

        $numeros   = [];
        $invalidos = [];
        $vacios    = 0;

        foreach ($crudos as $crudo) {
            $valor = trim((string) $crudo);

            if ($valor === '') {
                $vacios++;
                continue;
            }
            if (!preg_match('/^\d{1,2}$/', $valor)) {
                $invalidos[] = $valor;
                continue;
            }
            $numeros[] = (int) $valor;
        }

        $errores = [];
        if ($invalidos) {
            $errores[] = 'Hay valores que no son numeros de dos cifras: '
                       . implode(', ', array_unique($invalidos)) . '.';
        }
        if ($vacios > 0 && !$invalidos) {
            $errores[] = 'Faltan ' . $vacios . ' ' . ($vacios === 1 ? 'numero' : 'numeros')
                       . ' para completar los ' . $esperados . ' del extracto.';
        }
        if (!$errores && count($numeros) !== $esperados) {
            $errores[] = 'El extracto tiene que tener exactamente ' . $esperados . ' numeros.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        return $numeros;
    }

    // ── Escritura ───────────────────────────────────────────

    /** @param int[] $numeros */
    private function insertarSorteo(
        int $cicloId,
        DateTimeImmutable $fecha,
        int $turno,
        array $numeros,
        ?int $cargadoPor
    ): int {
        $this->db->prepare(
            'INSERT INTO sorteos (ciclo_id, fecha, turno, cargado_por) VALUES (:ciclo, :fecha, :turno, :usuario)'
        )->execute([
            ':ciclo'   => $cicloId,
            ':fecha'   => $fecha->format('Y-m-d'),
            ':turno'   => $turno,
            ':usuario' => $cargadoPor,
        ]);

        $sorteoId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO sorteo_numeros (sorteo_id, posicion, numero) VALUES (:sorteo, :pos, :numero)'
        );
        foreach ($numeros as $i => $numero) {
            $stmt->execute([':sorteo' => $sorteoId, ':pos' => $i + 1, ':numero' => $numero]);
        }

        return $sorteoId;
    }

    // ── Consultas ───────────────────────────────────────────

    /**
     * Sorteos de un ciclo, con sus numeros en orden de sorteo.
     *
     * @return array<int,array>
     */
    public function listarPorCiclo(int $cicloId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.fecha, s.turno, s.creado_en,
                    u.nombre AS cargado_por_nombre,
                    GROUP_CONCAT(n.numero ORDER BY n.posicion ASC) AS numeros,
                    (SELECT COUNT(*) FROM ganadores g WHERE g.sorteo_id = s.id) AS ganadores_total
               FROM sorteos s
               LEFT JOIN usuarios u       ON u.id = s.cargado_por
               LEFT JOIN sorteo_numeros n ON n.sorteo_id = s.id
              WHERE s.ciclo_id = :ciclo
              GROUP BY s.id
              ORDER BY s.fecha DESC, s.turno ASC'
        );
        $stmt->execute([':ciclo' => $cicloId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, u.nombre AS cargado_por_nombre, c.numero AS ciclo_numero, c.estado AS ciclo_estado
               FROM sorteos s
               LEFT JOIN usuarios u ON u.id = s.cargado_por
               JOIN ciclos c        ON c.id = s.ciclo_id
              WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $sorteo = $stmt->fetch();
        if (!$sorteo) {
            return null;
        }

        $stmtNum = $this->db->prepare(
            'SELECT numero FROM sorteo_numeros WHERE sorteo_id = :id ORDER BY posicion ASC'
        );
        $stmtNum->execute([':id' => $id]);
        $sorteo['numeros'] = array_map('intval', $stmtNum->fetchAll(PDO::FETCH_COLUMN));

        return $sorteo;
    }

    /**
     * Ganadores de un ciclo, con el cliente y el premio que le toco.
     *
     * @return array<int,array>
     */
    public function ganadoresDeCiclo(int $cicloId): array
    {
        $stmt = $this->db->prepare(
            'SELECT g.id, g.monto_premio, g.creado_en,
                    j.id AS jugada_id,
                    c.nombre AS cliente_nombre, c.nro_cliente, c.dni, c.telefono,
                    s.fecha AS sorteo_fecha,
                    GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
               FROM ganadores g
               JOIN jugadas  j ON j.id = g.jugada_id
               JOIN clientes c ON c.id = j.cliente_id
               JOIN sorteos  s ON s.id = g.sorteo_id
               LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
              WHERE g.ciclo_id = :ciclo
              GROUP BY g.id
              ORDER BY c.nombre ASC'
        );
        $stmt->execute([':ciclo' => $cicloId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    /**
     * Borra un sorteo. Exclusivo del admin y solo si el ciclo sigue abierto:
     * una vez liquidado el pozo, deshacer el cotejo dejaria premios pagados
     * sin sorteo que los respalde.
     *
     * @throws ValidacionException
     */
    public function eliminar(int $id): void
    {
        $sorteo = $this->buscarPorId($id);
        if (!$sorteo) {
            throw ValidacionException::de('El sorteo no existe.');
        }
        if ($sorteo['ciclo_estado'] !== CicloService::ESTADO_ABIERTO) {
            throw ValidacionException::de(
                'No se puede borrar: el ciclo ' . $sorteo['ciclo_numero'] . ' ya esta cerrado y liquidado.'
            );
        }

        // sorteo_numeros cae sola por el ON DELETE CASCADE.
        $this->db->prepare('DELETE FROM sorteos WHERE id = :id')->execute([':id' => $id]);
    }

    /** "3,17,42" -> [3, 17, 42] */
    private static function explotarNumeros(?string $concatenado): array
    {
        if ($concatenado === null || $concatenado === '') {
            return [];
        }
        return array_map('intval', explode(',', $concatenado));
    }
}
