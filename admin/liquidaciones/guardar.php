<?php
/** Handler POST de liquidar una comision. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\LiquidacionService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$tipo    = $_POST['tipo'] ?? '';
$id      = (int) ($_POST['id'] ?? 0);
$volverA = $_POST['volver_a'] ?? '';

$destino = $volverA === 'ver' && in_array($tipo, ['vendedor', 'supervisor'], true) && $id > 0
    ? APP_URL . '/admin/referidos/ver.php?tipo=' . $tipo . '&id=' . $id
    : APP_URL . '/admin/liquidaciones/index.php';

if (!in_array($tipo, ['vendedor', 'supervisor'], true) || $id <= 0) {
    setFlash('danger', 'Referidor inválido.');
    header('Location: ' . $destino);
    exit;
}

try {
    $monto = LiquidacionService::crearDesde(getPDO())->liquidar($tipo, $id, currentUserId());
    setFlash('success', 'Se liquidó ' . formatPesos($monto) . '.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . $destino);
exit;
