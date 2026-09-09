<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Pantalla de configuracion del admin: por ahora, el monto de la jugada.
 *
 * No es una tabla nueva: usa `parametros`, la misma que ya lee
 * ParametroService en toda la carga de jugadas (no estaba hardcodeado,
 * solo faltaba una pantalla para editarlo). Esta clase es la capa
 * admin-facing con auditoria (quien y cuando); ParametroService sigue
 * siendo la lectura interna que usa el resto del sistema.
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

    /**
     * Quien cambio el monto por ultima vez y cuando, para mostrarlo en
     * la pantalla. Null si nunca se toco desde que existe la columna.
     */
    public function ultimaActualizacion(): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.actualizado_en, u.nombre AS actualizado_por
               FROM parametros p
               LEFT JOIN usuarios u ON u.id = p.actualizado_por
              WHERE p.clave = :clave AND p.actualizado_por IS NOT NULL
              LIMIT 1'
        );
        $stmt->execute([':clave' => 'importe_jugada']);

        return $stmt->fetch() ?: null;
    }

    /** @throws ValidacionException */
    public function actualizarMontoJugada(string $monto, int $actualizadoPor): void
    {
        $this->parametros->actualizar(['importe_jugada' => $monto], $actualizadoPor);
    }
}
