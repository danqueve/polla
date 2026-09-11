<?php
/** Handler POST de la configuracion. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ConfiguracionService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$monto       = trim($_POST['monto_jugada'] ?? '');
$premioBase  = trim($_POST['premio_base'] ?? '');
$montoSabado = trim($_POST['monto_jugada_sabado'] ?? '');
$premioBaseSabado = trim($_POST['premio_base_sabado'] ?? '');
$horarioSemanal   = trim($_POST['horario_limite_semanal'] ?? '');
$horarioSabado    = trim($_POST['horario_limite_sabado'] ?? '');
$comisionPorcentaje = trim($_POST['comision_jugada_porcentaje'] ?? '');
$cantidadSabado      = trim($_POST['numeros_por_jugada_sabado'] ?? '');

$old = [
    'monto_jugada'               => $monto,
    'premio_base'                => $premioBase,
    'monto_jugada_sabado'        => $montoSabado,
    'premio_base_sabado'         => $premioBaseSabado,
    'horario_limite_semanal'     => $horarioSemanal,
    'horario_limite_sabado'      => $horarioSabado,
    'comision_jugada_porcentaje' => $comisionPorcentaje,
    'numeros_por_jugada_sabado'  => $cantidadSabado,
];

try {
    $configuracion = new ConfiguracionService(getPDO());
    $usuarioId     = currentUserId();

    $configuracion->actualizarMontoJugada($monto, $usuarioId);
    $configuracion->actualizarPremioBase($premioBase, $usuarioId);
    $configuracion->actualizarMontoJugadaSabado($montoSabado, $usuarioId);
    $configuracion->actualizarPremioBaseSabado($premioBaseSabado, $usuarioId);
    $configuracion->actualizarHorarios($horarioSemanal, $horarioSabado, $usuarioId);
    $configuracion->actualizarComisionJugadaPorcentaje($comisionPorcentaje, $usuarioId);
    $configuracion->actualizarNumerosPorJugadaSabado($cantidadSabado, $usuarioId);

    setFlash('success',
        'Configuración actualizada: monto de jugada ' . formatPesos($monto)
        . ' · premio base ' . formatPesos($premioBase)
        . ' · sábados ' . formatPesos($montoSabado) . '/' . formatPesos($premioBaseSabado)
        . ' (' . $cantidadSabado . ' números)'
        . ' · comisión ' . $comisionPorcentaje . '%.'
    );
} catch (ValidacionException $e) {
    setOld($old);
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/configuracion/index.php');
exit;
