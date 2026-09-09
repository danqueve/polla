<?php
/** Handler POST de la configuracion. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ConfiguracionService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$monto = trim($_POST['monto_jugada'] ?? '');

try {
    (new ConfiguracionService(getPDO()))->actualizarMontoJugada($monto, currentUserId());
    setFlash('success', 'Monto de la jugada actualizado a ' . formatPesos($monto) . '.');
} catch (ValidacionException $e) {
    setOld(['monto_jugada' => $monto]);
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/configuracion/index.php');
exit;
