<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;
use Throwable;

/**
 * Carga y consulta de jugadas.
 *
 * Una jugada son 10 numeros distintos entre 00 y 99 por $2.000. Al
 * confirmarla, el 60% se suma al pozo del ciclo abierto y el 40% queda
 * como gastos/ganancias. Todo eso ocurre en una sola transaccion: o se
 * guardan la jugada, sus 10 numeros y el aporte al pozo, o no se guarda
 * nada.
 */
class JugadaService
{
    private PDO $db;
    private ParametroService $parametros;
    private CicloService $ciclos;
    private PozoService $pozo;

    public function __construct(
        PDO $db,
        ParametroService $parametros,
        CicloService $ciclos,
        PozoService $pozo
    ) {
        $this->db         = $db;
        $this->parametros = $parametros;
        $this->ciclos     = $ciclos;
        $this->pozo       = $pozo;
    }

    /** Fabrica: arma el service con sus dependencias ya cableadas. */
    public static function crearDesde(PDO $db): self
    {
        $parametros = new ParametroService($db);
        return new self($db, $parametros, new CicloService($db), new PozoService($db));
    }

    /**
     * Registra una jugada pagada en el ciclo abierto.
     *
     * @param string[] $numerosCrudos Los 10 valores tal como vinieron del form.
     * @return int Id de la jugada nueva.
     * @throws ValidacionException
     */
    public function crear(int $clienteId, array $numerosCrudos, ?int $cargadoPor): int
    {
        $cliente = $this->buscarClienteActivo($clienteId);
        $numeros = $this->validarNumeros($numerosCrudos);

        $ciclo   = $this->ciclos->obtenerCicloActivo();
        $importe = $this->parametros->importeJugada();
        $reparto = $this->parametros->repartir($importe);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO jugadas (cliente_id, ciclo_id, importe, aporte_pozo,
                                      aporte_gastos, pagada, estado, cargado_por)
                 VALUES (:cliente, :ciclo, :importe, :pozo, :gastos, 1, 'activa', :usuario)"
            );
            $stmt->execute([
                ':cliente' => $cliente['id'],
                ':ciclo'   => $ciclo['id'],
                ':importe' => $importe,
                ':pozo'    => $reparto['pozo'],
                ':gastos'  => $reparto['gastos'],
                ':usuario' => $cargadoPor,
            ]);

            $jugadaId = (int) $this->db->lastInsertId();

            $stmtNum = $this->db->prepare(
                'INSERT INTO jugada_numeros (jugada_id, numero) VALUES (:jugada, :numero)'
            );
            foreach ($numeros as $numero) {
                $stmtNum->execute([':jugada' => $jugadaId, ':numero' => $numero]);
            }

            $this->pozo->acumular((int) $ciclo['id'], $reparto['pozo']);

            $this->db->commit();
            return $jugadaId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Normaliza y valida los numeros del formulario.
     *
     * Acepta "7", "07" y " 7 " como el mismo numero 7; rechaza vacios,
     * no numericos, fuera de rango, repetidos y cantidad distinta a 10.
     * Devuelve enteros 0..99 ordenados de menor a mayor.
     *
     * @param string[] $crudos
     * @return int[]
     * @throws ValidacionException
     */
    public function validarNumeros(array $crudos): array
    {
        $esperados = $this->parametros->numerosPorJugada();

        $numeros    = [];
        $repetidos  = [];
        $invalidos  = [];
        $vacios     = 0;

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

            $numero = (int) $valor;
            if (in_array($numero, $numeros, true)) {
                $repetidos[] = str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
                continue;
            }
            $numeros[] = $numero;
        }

        $errores = [];
        if ($invalidos) {
            $errores[] = 'Hay valores que no son numeros de dos cifras: ' . implode(', ', array_unique($invalidos)) . '.';
        }
        if ($repetidos) {
            $errores[] = 'No se puede repetir el mismo numero: ' . implode(', ', array_unique($repetidos)) . '.';
        }
        if ($vacios > 0 && !$invalidos && !$repetidos) {
            $errores[] = 'Faltan ' . $vacios . ' ' . ($vacios === 1 ? 'numero' : 'numeros') . ' para completar los ' . $esperados . '.';
        }
        if (!$errores && count($numeros) !== $esperados) {
            $errores[] = 'La jugada tiene que tener exactamente ' . $esperados . ' numeros distintos.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        sort($numeros);
        return $numeros;
    }

    /**
     * Jugadas de un ciclo, con el cliente y sus numeros ya agrupados.
     *
     * @return array<int,array>
     */
    public function listarPorCiclo(int $cicloId, string $busqueda = '', int $limite = 200): array
    {
        $where  = ['j.ciclo_id = :ciclo'];
        $params = [':ciclo' => $cicloId];

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            // Sin emulacion de prepares, cada aparicion necesita su propio
            // placeholder: PDO no reutiliza el mismo nombre dos veces.
            $where[] = '(c.nombre LIKE :q1 OR c.dni LIKE :q2 OR c.nro_cliente LIKE :q3)';
            $patron  = '%' . $busqueda . '%';
            $params[':q1'] = $patron;
            $params[':q2'] = $patron;
            $params[':q3'] = $patron;
        }

        // GROUP_CONCAT ordenado trae los 10 numeros en una sola pasada,
        // sin una consulta extra por jugada.
        $sql = 'SELECT j.id, j.importe, j.aporte_pozo, j.pagada, j.estado, j.fecha_carga,
                       c.id AS cliente_id, c.nombre AS cliente_nombre,
                       c.nro_cliente, c.dni,
                       u.nombre AS cargado_por_nombre,
                       GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
                  FROM jugadas j
                  JOIN clientes c        ON c.id = j.cliente_id
                  LEFT JOIN usuarios u   ON u.id = j.cargado_por
                  LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
                 WHERE ' . implode(' AND ', $where) . '
                 GROUP BY j.id
                 ORDER BY j.fecha_carga DESC, j.id DESC
                 LIMIT ' . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT j.*, c.nombre AS cliente_nombre, c.nro_cliente, c.dni,
                    cl.numero AS ciclo_numero, cl.estado AS ciclo_estado,
                    u.nombre AS cargado_por_nombre
               FROM jugadas j
               JOIN clientes c      ON c.id  = j.cliente_id
               JOIN ciclos   cl     ON cl.id = j.ciclo_id
               LEFT JOIN usuarios u ON u.id  = j.cargado_por
              WHERE j.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $jugada = $stmt->fetch();
        if (!$jugada) {
            return null;
        }

        $stmtNum = $this->db->prepare(
            'SELECT numero FROM jugada_numeros WHERE jugada_id = :id ORDER BY numero ASC'
        );
        $stmtNum->execute([':id' => $id]);
        $jugada['numeros'] = array_map('intval', $stmtNum->fetchAll(PDO::FETCH_COLUMN));

        return $jugada;
    }

    /** Ultimas jugadas cargadas, para el tablero. */
    public function ultimas(int $limite = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT j.id, j.importe, j.fecha_carga,
                    c.nombre AS cliente_nombre, c.nro_cliente,
                    GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
               FROM jugadas j
               JOIN clientes c ON c.id = j.cliente_id
               LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
              GROUP BY j.id
              ORDER BY j.id DESC
              LIMIT ' . (int) $limite
        );
        $stmt->execute();

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    /**
     * Borra una jugada y le devuelve al pozo el aporte que habia sumado.
     * Solo el admin llega aca (el handler usa requireAdmin()).
     *
     * @throws ValidacionException
     */
    public function eliminar(int $id): void
    {
        $jugada = $this->buscarPorId($id);
        if (!$jugada) {
            throw ValidacionException::de('La jugada no existe.');
        }
        if ($jugada['estado'] === 'ganadora') {
            throw ValidacionException::de('No se puede borrar una jugada ganadora ya liquidada.');
        }

        $this->db->beginTransaction();
        try {
            // Si el ciclo ya se cerro y liquido, el pozo no se toca.
            if ($jugada['ciclo_estado'] === CicloService::ESTADO_ABIERTO && (int) $jugada['pagada'] === 1) {
                $this->pozo->descontar((int) $jugada['ciclo_id'], (float) $jugada['aporte_pozo']);
            }

            // jugada_numeros cae sola por el ON DELETE CASCADE.
            $this->db->prepare('DELETE FROM jugadas WHERE id = :id')->execute([':id' => $id]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Internos ────────────────────────────────────────────

    /** @throws ValidacionException */
    private function buscarClienteActivo(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, nro_cliente, activo FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            throw ValidacionException::de('Elegí un cliente de la lista.');
        }
        if ((int) $cliente['activo'] !== 1) {
            throw ValidacionException::de('El cliente ' . $cliente['nombre'] . ' esta desactivado.');
        }

        return $cliente;
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
