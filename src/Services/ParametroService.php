<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;
use Throwable;

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
        'numeros_por_jugada_sabado'  => '5',
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
     * Cantidad de numeros por jugada del juego de sabados — caja separada
     * del semanal, así que puede (y hoy sí) valer distinto: 5 en vez de
     * los 10 del semanal. El extracto de cada turno sigue sacando la
     * misma cantidad de siempre (numeros_por_sorteo, hoy 20): esto solo
     * cambia cuantos numeros elige el CLIENTE.
     */
    public function numerosPorJugadaSabado(): int
    {
        return $this->getInt('numeros_por_jugada_sabado');
    }

    /**
     * Piso garantizado del pozo [Fase 7]. El pozo que se muestra y que
     * efectivamente se paga es siempre premioBase() + monto real
     * acumulado — el ganador se lleva el piso completo, ademas de lo
     * recaudado, no el mayor entre los dos.
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

    /**
     * Piso garantizado del pozo de sabados — caja separada del semanal,
     * mismo criterio de suma que premioBase().
     */
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
     * Valida TODOS los valores y recien despues los graba, juntos en una
     * transaccion: o se guardan todos o ninguno. Antes cada parametro se
     * grababa por separado y uno invalido a mitad de camino dejaba los
     * anteriores ya cambiados con un mensaje de error en pantalla.
     *
     * Los valores tienen que ser numeros escritos tal cual ("2000",
     * "2000.50"): (float) "2000abc" daba 2000 y se guardaba la basura,
     * y un campo vacio pasaba como 0.
     *
     * @param array<string,string> $valores
     * @throws ValidacionException
     */
    public function actualizar(array $valores, ?int $actualizadoPor = null): void
    {
        $valores = array_map(static fn($valor): string => trim((string) $valor), $valores);
        // Gastos es siempre el complemento del pozo: nunca se recibe suelto.
        unset($valores['porcentaje_gastos']);

        $montos = [
            'importe_jugada'        => ['El importe de la jugada', false],
            'premio_base'           => ['El premio base', true],
            'importe_jugada_sabado' => ['El importe de la jugada de sábados', false],
            'premio_base_sabado'    => ['El premio base de sábados', true],
        ];
        $horarios = [
            'horario_limite_semanal' => 'El horario límite semanal',
            'horario_limite_sabado'  => 'El horario límite de sábados',
        ];
        $porcentajes = [
            'comision_jugada_porcentaje' => 'El porcentaje de comisión',
            'porcentaje_pozo'            => 'El porcentaje del pozo',
        ];

        $errores = [];
        foreach ($valores as $clave => $valor) {
            if (isset($montos[$clave])) {
                [$rotulo, $admiteCero] = $montos[$clave];
                if (!self::esNumeroDecimal($valor) || (!$admiteCero && (float) $valor <= 0)) {
                    $errores[] = $rotulo . ($admiteCero
                        ? ' tiene que ser un número igual o mayor a cero.'
                        : ' tiene que ser un número mayor a cero.');
                }
            } elseif (isset($horarios[$clave])) {
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $valor)) {
                    $errores[] = $horarios[$clave] . ' tiene que tener formato HH:MM (24 hs).';
                }
            } elseif (isset($porcentajes[$clave])) {
                if (!self::esNumeroDecimal($valor) || (float) $valor > 100) {
                    $errores[] = $porcentajes[$clave] . ' tiene que ser un número entre 0 y 100.';
                }
            } elseif ($clave === 'numeros_por_jugada_sabado') {
                if (!ctype_digit($valor) || (int) $valor < 1 || (int) $valor > 20) {
                    $errores[] = 'La cantidad de números por jugada de sábados tiene que ser un número entero entre 1 y 20.';
                }
            } else {
                $errores[] = 'El parámetro "' . $clave . '" no se puede cambiar desde acá.';
            }
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        if (isset($valores['porcentaje_pozo'])) {
            $valores['porcentaje_gastos'] = (string) (100 - (float) $valores['porcentaje_pozo']);
        }

        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO parametros (clave, valor, actualizado_por) VALUES (:clave, :valor, :actualizado_por)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado_por = VALUES(actualizado_por)'
            );
            foreach ($valores as $clave => $valor) {
                $stmt->execute([
                    ':clave'           => $clave,
                    ':valor'           => $valor,
                    ':actualizado_por' => $actualizadoPor,
                ]);
            }

            if ($propia) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } finally {
            $this->cache = null;
        }
    }

    /** "2000", "2000.5" o "2000.50": sin signo, sin separador de miles, hasta 2 decimales. */
    private static function esNumeroDecimal(string $valor): bool
    {
        return preg_match('/^\d+(\.\d{1,2})?$/', $valor) === 1;
    }
}
