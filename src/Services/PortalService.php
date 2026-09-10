<?php

namespace Polla\Services;

use PDO;

/**
 * Consultas del portal del cliente.
 *
 * Regla de la clase: todo metodo que devuelva jugadas recibe el
 * $clienteId como PRIMER parametro obligatorio y lo aplica en el WHERE
 * de la consulta. No existe aca un metodo que acepte solo un id de
 * jugada o de ciclo y devuelva datos: aunque alguien manipule una URL,
 * no hay por donde pedir las jugadas de otro.
 *
 * Los sorteos y el pozo si son datos del ciclo, iguales para todos, y
 * por eso sus metodos no llevan cliente.
 */
class PortalService
{
    /** Ciclos cerrados que se muestran en el historial. */
    private const CICLOS_HISTORIAL = 8;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Jugadas de UN cliente en UN ciclo, con sus numeros y, si gano,
     * el premio que le toco.
     *
     * @return array<int,array>
     */
    public function jugadasDelCiclo(int $clienteId, int $cicloId): array
    {
        $stmt = $this->db->prepare(
            'SELECT j.id, j.importe, j.estado, j.fecha_carga,
                    g.monto_premio,
                    sg.fecha AS fecha_premio,
                    GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
               FROM jugadas j
               LEFT JOIN ganadores g       ON g.jugada_id = j.id
               LEFT JOIN sorteos   sg      ON sg.id       = g.sorteo_id
               LEFT JOIN jugada_numeros n  ON n.jugada_id = j.id
              WHERE j.cliente_id = :cliente
                AND j.ciclo_id   = :ciclo
              GROUP BY j.id
              ORDER BY j.fecha_carga ASC, j.id ASC'
        );
        $stmt->execute([':cliente' => $clienteId, ':ciclo' => $cicloId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    /**
     * Ciclos ya cerrados en los que este cliente jugo, del mas nuevo al
     * mas viejo, para un tipo de juego dado. El EXISTS con cliente_id es
     * lo que impide que aparezca un ciclo en el que no participo.
     *
     * El filtro de tipo no es cosmetico: `numero` se repite entre
     * semanal y sabado (cada uno tiene su propia secuencia), asi que
     * mezclarlos en un mismo listado mostraria "Ciclo 5" dos veces con
     * fechas y montos distintos.
     *
     * @return array<int,array>
     */
    public function ciclosJugados(
        int $clienteId,
        string $tipoJuego = CicloService::TIPO_SEMANAL,
        int $limite = self::CICLOS_HISTORIAL
    ): array {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.numero, c.fecha_inicio, c.fecha_fin, c.estado,
                    p.monto_acumulado, p.monto_pagado
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.estado <> 'abierto'
                AND c.tipo = :tipo
                AND EXISTS (
                        SELECT 1 FROM jugadas j
                         WHERE j.ciclo_id   = c.id
                           AND j.cliente_id = :cliente
                    )
              ORDER BY c.numero DESC
              LIMIT " . (int) $limite
        );
        $stmt->execute([':cliente' => $clienteId, ':tipo' => $tipoJuego]);

        return $stmt->fetchAll();
    }

    /**
     * Sorteos de un ciclo con sus 20 numeros, en orden de fecha.
     * Dato publico del ciclo: no depende del cliente.
     *
     * @return array<int,array>
     */
    public function sorteosDelCiclo(int $cicloId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id, s.fecha,
                    GROUP_CONCAT(n.numero ORDER BY n.posicion ASC) AS numeros
               FROM sorteos s
               LEFT JOIN sorteo_numeros n ON n.sorteo_id = s.id
              WHERE s.ciclo_id = :ciclo
              GROUP BY s.id
              ORDER BY s.fecha ASC'
        );
        $stmt->execute([':ciclo' => $cicloId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    /**
     * Cuenta cuantos numeros de la jugada salieron EN CADA SORTEO por
     * separado.
     *
     * Deliberadamente no se acumula a lo largo de la semana. La regla
     * del juego exige los 10 en un mismo sorteo, pero entre los 5
     * sorteos de una semana salen hasta 100 numeros: simulando 200.000
     * jugadas, en el 0,93% de los casos los 10 numeros aparecen en algun
     * momento de la semana sin haber ganado nunca. Marcar la union
     * dejaria a uno de cada cien clientes viendo sus 10 numeros en verde
     * y creyendo que gano.
     *
     * @param int[] $numerosJugada Los 10 numeros, distintos entre si.
     * @param array $sorteos       Salida de sorteosDelCiclo().
     * @return array{
     *     porSorteo: array<int,array{fecha:string, aciertos:int, acertados:array<int,bool>}>,
     *     mejor: ?array,
     *     mejorAciertos: int
     * }
     */
    public static function evaluar(array $numerosJugada, array $sorteos): array
    {
        $porSorteo = [];
        $mejor     = null;

        foreach ($sorteos as $sorteo) {
            // Los numeros de la jugada ya son distintos entre si, asi que
            // la interseccion no puede contar dos veces el mismo numero
            // aunque el extracto lo haya repetido en dos posiciones.
            $salieron  = array_flip($sorteo['numeros']);
            $acertados = [];

            foreach ($numerosJugada as $numero) {
                if (isset($salieron[$numero])) {
                    $acertados[$numero] = true;
                }
            }

            $entrada = [
                'fecha'     => $sorteo['fecha'],
                'aciertos'  => count($acertados),
                'acertados' => $acertados,
            ];

            $porSorteo[] = $entrada;

            if ($mejor === null || $entrada['aciertos'] > $mejor['aciertos']) {
                $mejor = $entrada;
            }
        }

        return [
            'porSorteo'     => $porSorteo,
            'mejor'         => $mejor,
            'mejorAciertos' => $mejor['aciertos'] ?? 0,
        ];
    }

    /**
     * Total ganado por el cliente en toda su historia, para el encabezado
     * del historial.
     */
    public function totalGanado(int $clienteId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(g.monto_premio), 0)
               FROM ganadores g
               JOIN jugadas j ON j.id = g.jugada_id
              WHERE j.cliente_id = :cliente'
        );
        $stmt->execute([':cliente' => $clienteId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Cantidad de jugadas que el cliente lleva jugadas en total.
     *
     * estado_pago = 'confirmada' [Fase 6]: sin esto, una jugada armada
     * desde el portal y todavia sin pagar (o rechazada) inflaria este
     * numero antes de ser real.
     */
    public function totalJugadas(int $clienteId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM jugadas
              WHERE cliente_id = :cliente
                AND estado <> 'anulada'
                AND estado_pago = 'confirmada'"
        );
        $stmt->execute([':cliente' => $clienteId]);

        return (int) $stmt->fetchColumn();
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
