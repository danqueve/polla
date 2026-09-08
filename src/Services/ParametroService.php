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
        'importe_jugada'     => '2000',
        'porcentaje_pozo'    => '60',
        'porcentaje_gastos'  => '40',
        'numeros_por_jugada' => '10',
        'numeros_por_sorteo' => '20',
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
    public function actualizar(array $valores): void
    {
        $errores = [];

        if (isset($valores['importe_jugada']) && (float) $valores['importe_jugada'] <= 0) {
            $errores[] = 'El importe de la jugada tiene que ser mayor a cero.';
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

        $sql = 'INSERT INTO parametros (clave, valor) VALUES (:clave, :valor)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)';
        $stmt = $this->db->prepare($sql);
        foreach ($valores as $clave => $valor) {
            $stmt->execute([':clave' => $clave, ':valor' => (string) $valor]);
        }

        $this->cache = null;
    }
}
