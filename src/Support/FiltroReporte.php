<?php

namespace Polla\Support;

use DateTimeImmutable;

/**
 * Filtros de las pantallas de reportes: rango de fechas, cliente, ciclo
 * y usuario que cargo.
 *
 * Se arma desde $_GET y sanea todo ahi mismo, asi que a ReporteService
 * le llegan enteros y fechas ya validadas y nunca texto del navegador.
 */
final class FiltroReporte
{
    public ?string $desde     = null;   // Y-m-d
    public ?string $hasta     = null;   // Y-m-d
    public ?int    $clienteId = null;
    public ?int    $cicloId   = null;

    /**
     * Filtro "cargado por" que elige el admin en la interfaz. No tiene
     * nada que ver con AlcanceReporte: este es opcional y sirve para
     * mirar el trabajo de alguien; el alcance es obligatorio y limita.
     * Un supervisor no puede usarlo (se ignora al construir).
     */
    public ?int $usuarioId = null;

    public static function desdeGet(array $get, AlcanceReporte $alcance): self
    {
        $filtro = new self();

        $filtro->desde     = self::fecha($get['desde'] ?? null);
        $filtro->hasta     = self::fecha($get['hasta'] ?? null);
        $filtro->clienteId = self::entero($get['cliente'] ?? null);
        $filtro->cicloId   = self::entero($get['ciclo'] ?? null);

        // Solo el admin puede filtrar por autor; para un supervisor el
        // unico autor posible ya es el mismo, via el alcance.
        if ($alcance->esAdmin()) {
            $filtro->usuarioId = self::entero($get['usuario'] ?? null);
        }

        // Rango dado vuelta: lo enderezamos en vez de devolver cero filas.
        if ($filtro->desde && $filtro->hasta && $filtro->desde > $filtro->hasta) {
            [$filtro->desde, $filtro->hasta] = [$filtro->hasta, $filtro->desde];
        }

        return $filtro;
    }

    public function hayAlguno(): bool
    {
        return $this->desde || $this->hasta || $this->clienteId || $this->cicloId || $this->usuarioId;
    }

    /** Rearma el query string para los enlaces de exportar y paginado. */
    public function comoQueryString(array $extra = []): string
    {
        $params = array_filter([
            'desde'   => $this->desde,
            'hasta'   => $this->hasta,
            'cliente' => $this->clienteId,
            'ciclo'   => $this->cicloId,
            'usuario' => $this->usuarioId,
        ]);

        return http_build_query($params + $extra);
    }

    private static function fecha($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        return $fecha ? $fecha->format('Y-m-d') : null;
    }

    private static function entero($valor): ?int
    {
        $valor = (int) $valor;
        return $valor > 0 ? $valor : null;
    }
}
