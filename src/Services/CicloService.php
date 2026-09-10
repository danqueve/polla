<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * Ciclos de juego: semanales (lunes a viernes, TIPO_SEMANAL) o de sabados
 * (5 turnos en un mismo dia, TIPO_SABADO). Son dos cajas independientes
 * -- cada tipo tiene su propia numeracion y su propio pozo -- pero
 * comparten exactamente la misma mecanica de negocio: gana alguien y se
 * corta la secuencia (semana o sabado), no gana nadie y el pozo arrastra
 * entero al ciclo siguiente de ese mismo tipo.
 *
 * Regla confirmada con el cliente: cuando un ciclo semanal se corta a
 * mitad de semana porque hubo ganador, los sorteos que quedan de esa
 * semana ya no participan y toda jugada nueva entra al ciclo de la
 * semana siguiente. Por eso abrirSiguiente() no arranca "hoy": arranca
 * el lunes posterior al fin del ultimo ciclo cerrado (o, para sabados,
 * el sabado siguiente al ultimo cerrado).
 *
 * A nivel base hay un UNIQUE sobre (tipo, columna generada que solo
 * tiene valor cuando el estado es 'abierto'), asi que el motor no
 * permite dos ciclos abiertos del mismo tipo ni aunque dos requests
 * intenten abrirlo a la vez -- pero si permite un semanal y un sabado
 * abiertos en simultaneo.
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

    /** Juego semanal (lunes a viernes, un sorteo por dia) — el de siempre. */
    public const TIPO_SEMANAL = 'semanal';
    /** Juego de sabados: 5 sorteos secuenciales en la misma fecha, pozo propio. */
    public const TIPO_SABADO = 'sabado';

    public const TIPOS = [
        self::TIPO_SEMANAL => 'Semanal',
        self::TIPO_SABADO  => 'Sábados',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ciclo abierto actual (de un tipo dado). Si no hay ninguno (primer
     * arranque del sistema o justo despues de una liquidacion), abre el
     * que corresponde.
     */
    public function obtenerCicloActivo(string $tipo = self::TIPO_SEMANAL): array
    {
        $ciclo = $this->buscarAbierto($tipo);
        if ($ciclo) {
            return $ciclo;
        }

        try {
            $this->abrirSiguiente($tipo);
        } catch (PDOException $e) {
            // Otro request gano la carrera y ya lo abrio: nos sirve el suyo.
            if (strpos($e->getMessage(), 'uk_ciclo_tipo_abierto') === false) {
                throw $e;
            }
        }

        $ciclo = $this->buscarAbierto($tipo);
        if (!$ciclo) {
            throw ValidacionException::de('No se pudo abrir el ciclo. Revisá la base de datos.');
        }

        return $ciclo;
    }

    public function buscarAbierto(string $tipo = self::TIPO_SEMANAL): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, p.monto_acumulado, p.monto_pagado, p.monto_arrastrado
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.estado = 'abierto' AND c.tipo = :tipo
              LIMIT 1"
        );
        $stmt->execute([':tipo' => $tipo]);

        return $stmt->fetch() ?: null;
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
    public function bloquearAbierto(string $tipo = self::TIPO_SEMANAL): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*, p.monto_acumulado, p.monto_pagado, p.monto_arrastrado
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.estado = 'abierto' AND c.tipo = :tipo
              LIMIT 1
                FOR UPDATE"
        );
        $stmt->execute([':tipo' => $tipo]);

        return $stmt->fetch() ?: null;
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
    public function listar(int $limite = 20, string $tipo = self::TIPO_SEMANAL): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.monto_arrastrado, p.monto_acumulado, p.monto_pagado, p.fecha_liquidacion,
                    (SELECT COUNT(*) FROM jugadas   j WHERE j.ciclo_id  = c.id) AS jugadas_total,
                    (SELECT COUNT(*) FROM sorteos   s WHERE s.ciclo_id  = c.id) AS sorteos_total,
                    (SELECT COUNT(*) FROM ganadores g WHERE g.ciclo_id  = c.id) AS ganadores_total
               FROM ciclos c
               LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
              WHERE c.tipo = :tipo
              ORDER BY c.numero DESC
              LIMIT ' . (int) $limite
        );
        $stmt->execute([':tipo' => $tipo]);

        return $stmt->fetchAll();
    }

    /**
     * Abre el proximo ciclo (de un tipo dado) y le crea el pozo.
     *
     * $saldoInicial es el arrastre: cero cuando el ciclo anterior se cerro
     * con ganador (el pozo se repartio entero), o el pozo sobrante cuando
     * la semana (o el sabado) cerro sin que nadie ganara.
     *
     * Si ya viene una transaccion en curso (el cierre del ciclo anterior)
     * se suma a esa; si no, abre la suya.
     *
     * @return int Id del ciclo nuevo.
     */
    public function abrirSiguiente(string $tipo = self::TIPO_SEMANAL, float $saldoInicial = 0.0): int
    {
        [$inicio, $fin] = $tipo === self::TIPO_SABADO
            ? $this->fechaDelProximoSabado()
            : $this->fechasDelProximoCicloSemanal();

        $stmtNumero = $this->db->prepare('SELECT COALESCE(MAX(numero), 0) FROM ciclos WHERE tipo = :tipo');
        $stmtNumero->execute([':tipo' => $tipo]);
        $numero = (int) $stmtNumero->fetchColumn() + 1;

        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO ciclos (tipo, numero, fecha_inicio, fecha_fin, estado)
                 VALUES (:tipo, :numero, :inicio, :fin, 'abierto')"
            );
            $stmt->execute([
                ':tipo'   => $tipo,
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
     * Lunes y viernes que le tocan al proximo ciclo semanal.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function fechasDelProximoCicloSemanal(): array
    {
        $lunesDeEstaSemana = new DateTimeImmutable('monday this week');

        $ultimoFin = $this->db->query(
            "SELECT MAX(fecha_fin) FROM ciclos WHERE tipo = 'semanal' AND estado <> 'abierto'"
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
     * El sabado que le toca al proximo ciclo de sabados. A diferencia del
     * semanal, un ciclo de sabado dura un solo dia: fecha_inicio y
     * fecha_fin son la misma fecha, y esa fecha aloja hasta 5 sorteos
     * (turnos), no 5 fechas distintas.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function fechaDelProximoSabado(): array
    {
        $ultimoFin = $this->db->query(
            "SELECT MAX(fecha_fin) FROM ciclos WHERE tipo = 'sabado' AND estado <> 'abierto'"
        )->fetchColumn();

        if ($ultimoFin) {
            $proximo = (new DateTimeImmutable($ultimoFin))->modify('next saturday');
        } else {
            $hoy = new DateTimeImmutable('today');
            $proximo = ((int) $hoy->format('N') === 6) ? $hoy : $hoy->modify('next saturday');
        }

        return [$proximo, $proximo];
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

    /** "09/03 al 13/03/2026" para un ciclo semanal, "Sábado 14/03/2026" para uno de sabados. */
    public static function rotulo(array $ciclo): string
    {
        $inicio = new DateTimeImmutable($ciclo['fecha_inicio']);
        $fin    = new DateTimeImmutable($ciclo['fecha_fin']);

        if ($ciclo['fecha_inicio'] === $ciclo['fecha_fin']) {
            return nombreDia($inicio) . ' ' . $inicio->format('d/m/Y');
        }

        return $inicio->format('d/m') . ' al ' . $fin->format('d/m/Y');
    }
}
