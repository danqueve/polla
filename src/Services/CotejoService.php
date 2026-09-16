<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;

/**
 * Motor de cotejo y cierre de ciclos.
 *
 * Responsabilidad unica: comparar los numeros de las jugadas activas
 * contra los extractos cargados y decidir si el ciclo se cierra (con
 * o sin ganador). Todo lo que es CRUD de sorteos (registrar, validar
 * fecha, editar, eliminar) sigue en SorteoService.
 *
 * Extraido de SorteoService para que cada clase tenga ~500 lineas y
 * una sola razon de cambio.
 */
class CotejoService
{
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

    // ── Cotejo de ciclo completo (replay) ───────────────────

    /**
     * Recoteja un ciclo entero desde cero, sorteo por sorteo en orden
     * cronologico. Lo usa SorteoService::corregir() para recalcular
     * el resultado despues de editar un extracto.
     *
     * @return array{ganadores:array<int,float>, cerro_ciclo:bool, estado_cierre:?string,
     *               pozo_repartido:float, ciclo_nuevo_id:?int, arrastre:float}
     */
    public function recotejarCiclo(array $ciclo, string $tipo): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, fecha, turno FROM sorteos WHERE ciclo_id = :ciclo ORDER BY fecha ASC, turno ASC'
        );
        $stmt->execute([':ciclo' => $ciclo['id']]);
        $sorteos = $stmt->fetchAll();

        $completa = $this->secuenciaCompleta($ciclo, $tipo, $sorteos);

        $resultado = [
            'ganadores' => [], 'cerro_ciclo' => false, 'estado_cierre' => null,
            'pozo_repartido' => 0.0, 'ciclo_nuevo_id' => null, 'arrastre' => 0.0,
        ];
        foreach ($sorteos as $i => $sorteo) {
            $esUltimo = $completa && $i === count($sorteos) - 1;
            $resultado = $this->cotejarYCerrar((int) $sorteo['id'], $ciclo, $esUltimo, $tipo);
            if ($resultado['cerro_ciclo']) {
                $this->reasignarSorteosPosteriores(array_slice($sorteos, $i + 1), $resultado['ciclo_nuevo_id']);
                break;
            }
        }

        return $resultado;
    }

    // ── Cotejo de un sorteo puntual ─────────────────────────

    /**
     * Corre el cotejo y decide que hacer con el ciclo.
     *
     * @return array{ganadores:array<int,float>, cerro_ciclo:bool, estado_cierre:?string,
     *               pozo_repartido:float, ciclo_nuevo_id:?int, arrastre:float}
     */
    public function cotejarYCerrar(
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

            $premios = $this->pozo->liquidar($cicloId, $sorteoId, $ganadoras, $premioBase);
            $repartido = array_sum($premios);

            $this->marcarRestantesPerdedoras($cicloId);
            $this->ciclos->cerrar($cicloId, CicloService::ESTADO_CON_GANADOR);

            $nuevoId = $this->ciclos->promoverOAbrirSiguiente($tipo, 0.0);

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
        $this->marcarRestantesPerdedoras($cicloId);

        $arrastre = $this->pozo->montoAcumulado($cicloId);

        $this->ciclos->cerrar($cicloId, CicloService::ESTADO_SIN_GANADOR);
        $nuevoId = $this->ciclos->promoverOAbrirSiguiente($tipo, $arrastre);

        return [
            'ganadores'      => [],
            'cerro_ciclo'    => true,
            'estado_cierre'  => CicloService::ESTADO_SIN_GANADOR,
            'pozo_repartido' => 0.0,
            'ciclo_nuevo_id' => $nuevoId,
            'arrastre'       => $arrastre,
        ];
    }

    // ── Jugadas ganadoras ───────────────────────────────────

    /**
     * Jugadas del ciclo cuyos numeros salieron TODOS, acumulados entre
     * todos los sorteos/turnos cargados hasta este (inclusive).
     *
     * @return int[] Ids de las jugadas ganadoras.
     */
    public function jugadasGanadoras(int $sorteoId, int $cicloId): array
    {
        $stmt = $this->db->prepare(
            "SELECT j.id
               FROM jugadas j
               JOIN jugada_numeros jn ON jn.jugada_id = j.id
              WHERE j.ciclo_id = :ciclo1
                AND j.estado   = 'activa'
                AND j.pagada   = 1
                AND jn.numero IN (
                    SELECT DISTINCT sn.numero
                      FROM sorteo_numeros sn
                      JOIN sorteos s   ON s.id = sn.sorteo_id
                      JOIN sorteos ref ON ref.id = :sorteo
                     WHERE s.ciclo_id = :ciclo2
                       AND (s.fecha < ref.fecha OR (s.fecha = ref.fecha AND s.turno <= ref.turno))
                )
              GROUP BY j.id
             HAVING COUNT(DISTINCT jn.numero)
                  = (SELECT COUNT(*) FROM jugada_numeros x WHERE x.jugada_id = j.id)
              ORDER BY j.id ASC"
        );
        $stmt->execute([':ciclo1' => $cicloId, ':ciclo2' => $cicloId, ':sorteo' => $sorteoId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    // ── Helpers internos ────────────────────────────────────

    /**
     * Reasigna sorteos sobrantes al ciclo siguiente o los deja sin ciclo.
     *
     * @param array<int,array{id:int,fecha:string,turno:int}> $sorteosSobrantes
     */
    private function reasignarSorteosPosteriores(array $sorteosSobrantes, ?int $cicloNuevoId): void
    {
        if (!$sorteosSobrantes) {
            return;
        }

        $nuevo = $cicloNuevoId ? $this->ciclos->buscarPorId($cicloNuevoId) : null;

        foreach ($sorteosSobrantes as $sorteo) {
            $entra = $nuevo
                && $sorteo['fecha'] >= $nuevo['fecha_inicio']
                && $sorteo['fecha'] <= $nuevo['fecha_fin'];

            $this->db->prepare('UPDATE sorteos SET ciclo_id = :ciclo WHERE id = :id')
                ->execute([':ciclo' => $entra ? $nuevo['id'] : null, ':id' => $sorteo['id']]);
        }
    }

    /**
     * Secuencia completa: ya estan cargados todos los sorteos que le
     * corresponden a este ciclo.
     *
     * @param array<int,array{fecha:string,turno:int}> $sorteosCargados
     */
    private function secuenciaCompleta(array $ciclo, string $tipo, array $sorteosCargados): bool
    {
        if ($tipo === CicloService::TIPO_SABADO) {
            return count($sorteosCargados) === 5;
        }

        $fechasCargadas = array_column($sorteosCargados, 'fecha');
        $dia = new DateTimeImmutable($ciclo['fecha_inicio']);
        $fin = new DateTimeImmutable($ciclo['fecha_fin']);
        while ($dia <= $fin) {
            if (!in_array($dia->format('Y-m-d'), $fechasCargadas, true)) {
                return false;
            }
            $dia = $dia->modify('+1 day');
        }

        return true;
    }

    /** @param int[] $jugadaIds */
    private function marcarEstado(array $jugadaIds, string $estado): void
    {
        if (!$jugadaIds) {
            return;
        }
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
}
