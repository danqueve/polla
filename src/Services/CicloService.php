<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * Ciclos semanales de juego (lunes a viernes).
 *
 * Regla confirmada con el cliente: cuando un ciclo se corta a mitad de
 * semana porque hubo ganador, los sorteos que quedan de esa semana ya no
 * participan y toda jugada nueva entra al ciclo de la semana siguiente.
 * Por eso abrirSiguiente() no arranca "hoy": arranca el lunes posterior
 * al fin del ultimo ciclo cerrado.
 *
 * A nivel base hay un UNIQUE sobre una columna generada que solo tiene
 * valor cuando el estado es 'abierto', asi que el motor no permite dos
 * ciclos abiertos ni aunque dos requests intenten abrirlo a la vez.
 */
class CicloService
{
    public const ESTADO_ABIERTO    = 'abierto';
    public const ESTADO_CON_GANADOR = 'cerrado_con_ganador';
    public const ESTADO_SIN_GANADOR = 'cerrado_sin_ganador';

    public const ESTADOS = [
        self::ESTADO_ABIERTO     => 'Abierto',
        self::ESTADO_CON_GANADOR => 'Cerrado con ganador',
        self::ESTADO_SIN_GANADOR => 'Cerrado sin ganador',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ciclo abierto actual. Si no hay ninguno (primer arranque del sistema
     * o justo despues de una liquidacion), abre el que corresponde.
     */
    public function obtenerCicloActivo(): array
    {
        $ciclo = $this->buscarAbierto();
        if ($ciclo) {
            return $ciclo;
        }

        try {
            $this->abrirSiguiente();
        } catch (PDOException $e) {
            // Otro request gano la carrera y ya lo abrio: nos sirve el suyo.
            if (strpos($e->getMessage(), 'uk_ciclo_abierto') === false) {
                throw $e;
            }
        }

        $ciclo = $this->buscarAbierto();
        if (!$ciclo) {
            throw ValidacionException::de('No se pudo abrir el ciclo semanal. Revisá la base de datos.');
        }

        return $ciclo;
    }

    public function buscarAbierto(): ?array
    {
        $fila = $this->db->query(
            "SELECT c.*, p.monto_acumulado, p.monto_pagado, p.monto_arrastrado
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.estado = 'abierto'
              LIMIT 1"
        )->fetch();

        return $fila ?: null;
    }

    /**
     * Igual que buscarAbierto(), pero deja la fila del ciclo bloqueada
     * hasta el commit.
     *
     * Es el candado del cotejo: si el admin y el supervisor guardan dos
     * sorteos al mismo tiempo, sin esto los dos leerian el pozo lleno y
     * lo liquidarian por duplicado. Con el lock, el segundo espera y se
     * encuentra el ciclo ya cerrado.
     *
     * Solo tiene sentido llamarlo dentro de una transaccion.
     */
    public function bloquearAbierto(): ?array
    {
        $fila = $this->db->query(
            "SELECT c.*, p.monto_acumulado, p.monto_pagado, p.monto_arrastrado
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.estado = 'abierto'
              LIMIT 1
                FOR UPDATE"
        )->fetch();

        return $fila ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.monto_arrastrado, p.monto_acumulado, p.monto_pagado,
                    p.monto_piso_aplicado, p.fecha_liquidacion
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** @return array<int,array> */
    public function listar(int $limite = 20): array
    {
        return $this->db->query(
            'SELECT c.*, p.monto_arrastrado, p.monto_acumulado, p.monto_pagado, p.fecha_liquidacion,
                    (SELECT COUNT(*) FROM jugadas   j WHERE j.ciclo_id  = c.id) AS jugadas_total,
                    (SELECT COUNT(*) FROM sorteos   s WHERE s.ciclo_id  = c.id) AS sorteos_total,
                    (SELECT COUNT(*) FROM ganadores g WHERE g.ciclo_id  = c.id) AS ganadores_total
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              ORDER BY c.numero DESC
              LIMIT ' . (int) $limite
        )->fetchAll();
    }

    /**
     * Abre el proximo ciclo y le crea el pozo.
     *
     * $saldoInicial es el arrastre: cero cuando el ciclo anterior se cerro
     * con ganador (el pozo se repartio entero), o el pozo sobrante cuando
     * la semana cerro sin que nadie ganara.
     *
     * Si ya viene una transaccion en curso (el cierre del ciclo anterior)
     * se suma a esa; si no, abre la suya.
     *
     * @return int Id del ciclo nuevo.
     */
    public function abrirSiguiente(float $saldoInicial = 0.0): int
    {
        [$inicio, $fin] = $this->fechasDelProximoCiclo();

        $numero = (int) $this->db->query('SELECT COALESCE(MAX(numero), 0) FROM ciclos')->fetchColumn() + 1;

        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO ciclos (numero, fecha_inicio, fecha_fin, estado)
                 VALUES (:numero, :inicio, :fin, 'abierto')"
            );
            $stmt->execute([
                ':numero' => $numero,
                ':inicio' => $inicio->format('Y-m-d'),
                ':fin'    => $fin->format('Y-m-d'),
            ]);

            $cicloId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                'INSERT INTO pozo_ciclo (ciclo_id, monto_arrastrado, monto_acumulado, monto_pagado)
                 VALUES (:id, :arrastre, :arrastre2, 0)'
            )->execute([
                ':id'        => $cicloId,
                ':arrastre'  => $saldoInicial,
                ':arrastre2' => $saldoInicial,
            ]);

            if ($propia) {
                $this->db->commit();
            }
            return $cicloId;
        } catch (PDOException $e) {
            if ($propia) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cierra un ciclo. No abre el siguiente ni toca el pozo: de eso se
     * encarga SorteoService, que es quien sabe si hubo ganador.
     *
     * @param string $estado ESTADO_CON_GANADOR | ESTADO_SIN_GANADOR
     */
    public function cerrar(int $cicloId, string $estado): void
    {
        if ($estado !== self::ESTADO_CON_GANADOR && $estado !== self::ESTADO_SIN_GANADOR) {
            throw ValidacionException::de('Estado de cierre invalido: ' . $estado);
        }

        $this->db->prepare(
            'UPDATE ciclos
                SET estado = :estado, fecha_cierre = NOW()
              WHERE id = :id AND estado = :abierto'
        )->execute([
            ':estado'  => $estado,
            ':id'      => $cicloId,
            ':abierto' => self::ESTADO_ABIERTO,
        ]);
    }

    /**
     * Lunes y viernes que le tocan al proximo ciclo.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function fechasDelProximoCiclo(): array
    {
        $lunesDeEstaSemana = new DateTimeImmutable('monday this week');

        $ultimoFin = $this->db->query(
            "SELECT MAX(fecha_fin) FROM ciclos WHERE estado <> 'abierto'"
        )->fetchColumn();

        $inicio = $lunesDeEstaSemana;

        if ($ultimoFin) {
            // El ciclo cerrado termino un viernes: el siguiente arranca el
            // lunes posterior, aunque todavia estemos en la misma semana.
            $siguienteLunes = (new DateTimeImmutable($ultimoFin))->modify('next monday');
            if ($siguienteLunes > $inicio) {
                $inicio = $siguienteLunes;
            }
        }

        return [$inicio, $inicio->modify('+4 days')];
    }

    /**
     * Numeros de la semana para el tablero: jugadas cargadas, clientes
     * distintos, recaudacion y reparto.
     */
    public function resumen(int $cicloId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)                                  AS jugadas_total,
                    COUNT(DISTINCT cliente_id)                AS clientes_distintos,
                    COALESCE(SUM(importe), 0)                 AS recaudado,
                    COALESCE(SUM(aporte_pozo), 0)             AS al_pozo,
                    COALESCE(SUM(aporte_gastos), 0)           AS a_gastos
               FROM jugadas
              WHERE ciclo_id = :id AND estado <> 'anulada' AND pagada = 1"
        );
        $stmt->execute([':id' => $cicloId]);

        return $stmt->fetch() ?: [
            'jugadas_total'      => 0,
            'clientes_distintos' => 0,
            'recaudado'          => 0,
            'al_pozo'            => 0,
            'a_gastos'           => 0,
        ];
    }

    /** "Lun 09/03 al Vie 13/03" */
    public static function rotulo(array $ciclo): string
    {
        $inicio = new DateTimeImmutable($ciclo['fecha_inicio']);
        $fin    = new DateTimeImmutable($ciclo['fecha_fin']);
        return $inicio->format('d/m') . ' al ' . $fin->format('d/m/Y');
    }
}
