<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Parametros configurables del juego (importe de la jugada, reparto
 * pozo/gastos). Solo el admin los edita; el resto del sistema los lee
 * desde aca en vez de tener numeros magicos desparramados.
 */
class ParametroService
{
    private PDO $db;

    /** Cache por request: los parametros se leen muchas veces por pantalla. */
    private ?array $cache = null;

    /** Valores de respaldo si la tabla todavia no fue sembrada. */
    private const DEFECTOS = [
        'importe_jugada'         => '2000',
        'porcentaje_pozo'        => '60',
        'porcentaje_gastos'      => '40',
        'numeros_por_jugada'     => '10',
        'numeros_por_sorteo'     => '20',
        'premio_base'            => '25000',
        'importe_jugada_sabado'  => '2000',
        'premio_base_sabado'     => '25000',
        'horario_limite_semanal' => '18:00',
        'horario_limite_sabado'  => '11:00',
        'comision_jugada_porcentaje' => '0',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string,string> */
    public function todos(): array
    {
        if ($this->cache === null) {
            $filas = $this->db->query('SELECT clave, valor FROM parametros')->fetchAll();
            $this->cache = self::DEFECTOS;
            foreach ($filas as $fila) {
                $this->cache[$fila['clave']] = $fila['valor'];
            }
        }
        return $this->cache;
    }

    public function get(string $clave, ?string $default = null): ?string
    {
        $todos = $this->todos();
        return $todos[$clave] ?? $default ?? self::DEFECTOS[$clave] ?? null;
    }

    public function getInt(string $clave): int
    {
        return (int) $this->get($clave);
    }

    public function getFloat(string $clave): float
    {
        return (float) $this->get($clave);
    }

    public function importeJugada(): float
    {
        return $this->getFloat('importe_jugada');
    }

    public function porcentajePozo(): float
    {
        return $this->getFloat('porcentaje_pozo');
    }

    public function numerosPorJugada(): int
    {
        return $this->getInt('numeros_por_jugada');
    }

    /**
     * Piso garantizado del pozo [Fase 7]. El pozo que se muestra y que
     * efectivamente se paga es siempre MAX(premioBase(), monto real
     * acumulado) — nunca el monto real solo.
     */
    public function premioBase(): float
    {
        return $this->getFloat('premio_base');
    }

    /** Monto por jugada del juego de sabados — caja separada del semanal. */
    public function importeJugadaSabado(): float
    {
        return $this->getFloat('importe_jugada_sabado');
    }

    /** Piso garantizado del pozo de sabados — caja separada del semanal. */
    public function premioBaseSabado(): float
    {
        return $this->getFloat('premio_base_sabado');
    }

    /** Hora limite (HH:MM, Argentina) para cargar jugadas del juego semanal (lunes a viernes). */
    public function horarioLimiteSemanal(): string
    {
        return (string) $this->get('horario_limite_semanal');
    }

    /** Hora limite (HH:MM, Argentina) para cargar jugadas del juego de sabados, el mismo sabado. */
    public function horarioLimiteSabado(): string
    {
        return (string) $this->get('horario_limite_sabado');
    }

    /**
     * Porcentaje global de comision [Fase 11] para el vendedor/supervisor
     * que refirio al cliente, sobre el importe de cada jugada confirmada.
     * Arranca en 0 (nadie cobra) hasta que el admin lo suba.
     */
    public function comisionJugadaPorcentaje(): float
    {
        return $this->getFloat('comision_jugada_porcentaje');
    }

    /**
     * Parte un importe en la porcion que va al pozo y la que va a gastos.
     * Redondea el pozo a 2 decimales y le da el resto a gastos, para que
     * pozo + gastos sea siempre exactamente el importe.
     *
     * @return array{pozo: float, gastos: float}
     */
    public function repartir(float $importe): array
    {
        $pozo = round($importe * $this->porcentajePozo() / 100, 2);
        return ['pozo' => $pozo, 'gastos' => round($importe - $pozo, 2)];
    }

    /**
     * @param array<string,string> $valores
     * @throws ValidacionException
     */
    public function actualizar(array $valores, ?int $actualizadoPor = null): void
    {
        $errores = [];

        if (isset($valores['importe_jugada']) && (float) $valores['importe_jugada'] <= 0) {
            $errores[] = 'El importe de la jugada tiene que ser mayor a cero.';
        }
        if (isset($valores['premio_base']) && (float) $valores['premio_base'] < 0) {
            $errores[] = 'El premio base no puede ser negativo.';
        }
        if (isset($valores['importe_jugada_sabado']) && (float) $valores['importe_jugada_sabado'] <= 0) {
            $errores[] = 'El importe de la jugada de sábados tiene que ser mayor a cero.';
        }
        if (isset($valores['premio_base_sabado']) && (float) $valores['premio_base_sabado'] < 0) {
            $errores[] = 'El premio base de sábados no puede ser negativo.';
        }
        foreach (['horario_limite_semanal', 'horario_limite_sabado'] as $clave) {
            if (isset($valores[$clave]) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $valores[$clave])) {
                $errores[] = 'El horario tiene que tener formato HH:MM (24hs).';
            }
        }
        if (isset($valores['comision_jugada_porcentaje'])) {
            $comision = (float) $valores['comision_jugada_porcentaje'];
            if ($comision < 0 || $comision > 100) {
                $errores[] = 'El porcentaje de comisión tiene que estar entre 0 y 100.';
            }
        }
        if (isset($valores['porcentaje_pozo'])) {
            $pozo = (float) $valores['porcentaje_pozo'];
            if ($pozo < 0 || $pozo > 100) {
                $errores[] = 'El porcentaje del pozo tiene que estar entre 0 y 100.';
            }
            // Gastos es siempre el complemento: evita que queden desfasados.
            $valores['porcentaje_gastos'] = (string) (100 - $pozo);
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        $sql = 'INSERT INTO parametros (clave, valor, actualizado_por) VALUES (:clave, :valor, :actualizado_por)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por)';
        $stmt = $this->db->prepare($sql);
        foreach ($valores as $clave => $valor) {
            $stmt->execute([
                ':clave'           => $clave,
                ':valor'           => (string) $valor,
                ':actualizado_por' => $actualizadoPor,
            ]);
        }

        $this->cache = null;
    }
}
