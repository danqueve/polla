<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Pantalla de configuracion del admin: monto de la jugada y, desde la
 * Fase 7, el premio base garantizado.
 *
 * No son tablas nuevas: usa `parametros`, la misma que ya lee
 * ParametroService en toda la carga de jugadas y en la liquidacion del
 * pozo (no estaba hardcodeado, solo faltaba una pantalla para editarlo).
 * Esta clase es la capa admin-facing con auditoria (quien y cuando);
 * ParametroService sigue siendo la lectura interna que usa el resto
 * del sistema.
 */
class ConfiguracionService
{
    private PDO $db;
    private ParametroService $parametros;

    public function __construct(PDO $db)
    {
        $this->db         = $db;
        $this->parametros = new ParametroService($db);
    }

    public function montoJugada(): float
    {
        return $this->parametros->importeJugada();
    }

    public function premioBase(): float
    {
        return $this->parametros->premioBase();
    }

    public function montoJugadaSabado(): float
    {
        return $this->parametros->importeJugadaSabado();
    }

    public function premioBaseSabado(): float
    {
        return $this->parametros->premioBaseSabado();
    }

    public function horarioLimiteSemanal(): string
    {
        return $this->parametros->horarioLimiteSemanal();
    }

    public function horarioLimiteSabado(): string
    {
        return $this->parametros->horarioLimiteSabado();
    }

    public function comisionJugadaPorcentaje(): float
    {
        return $this->parametros->comisionJugadaPorcentaje();
    }

    public function numerosPorJugadaSabado(): int
    {
        return $this->parametros->numerosPorJugadaSabado();
    }

    /**
     * Quien cambio un parametro por ultima vez y cuando, para mostrarlo
     * en la pantalla. Null si nunca se toco desde que existe la columna.
     */
    public function ultimaActualizacion(string $clave): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.actualizado_en, u.nombre AS actualizado_por
               FROM parametros p
               LEFT JOIN usuarios u ON u.id = p.actualizado_por
              WHERE p.clave = :clave AND p.actualizado_por IS NOT NULL
              LIMIT 1'
        );
        $stmt->execute([':clave' => $clave]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Guarda la pantalla entera de una sola vez: ParametroService valida
     * todo antes de escribir y graba en una transaccion, asi que un campo
     * invalido no deja los otros a medio cambiar.
     *
     * @param array{monto_jugada:string, premio_base:string, monto_jugada_sabado:string,
     *              premio_base_sabado:string, horario_limite_semanal:string,
     *              horario_limite_sabado:string, comision_jugada_porcentaje:string,
     *              numeros_por_jugada_sabado:string} $form
     * @throws ValidacionException
     */
    public function actualizar(array $form, int $actualizadoPor): void
    {
        $this->parametros->actualizar([
            'importe_jugada'             => $form['monto_jugada'],
            'premio_base'                => $form['premio_base'],
            'importe_jugada_sabado'      => $form['monto_jugada_sabado'],
            'premio_base_sabado'         => $form['premio_base_sabado'],
            'horario_limite_semanal'     => $form['horario_limite_semanal'],
            'horario_limite_sabado'      => $form['horario_limite_sabado'],
            'comision_jugada_porcentaje' => $form['comision_jugada_porcentaje'],
            'numeros_por_jugada_sabado'  => $form['numeros_por_jugada_sabado'],
        ], $actualizadoPor);
    }
}
