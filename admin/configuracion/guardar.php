<?php
/** Handler POST de la configuracion. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ConfiguracionService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$monto      = trim($_POST['monto_jugada'] ?? '');
$premioBase = trim($_POST['premio_base'] ?? '');

try {
    $configuracion = new ConfiguracionService(getPDO());
    $usuarioId     = currentUserId();

    $configuracion->actualizarMontoJugada($monto, $usuarioId);
    $configuracion->actualizarPremioBase($premioBase, $usuarioId);

    setFlash('success',
        'Configuración actualizada: monto de jugada ' . formatPesos($monto)
        . ' · premio base ' . formatPesos($premioBase) . '.'
    );
} catch (ValidacionException $e) {
    setOld(['monto_jugada' => $monto, 'premio_base' => $premioBase]);
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/configuracion/index.php');
exit;
