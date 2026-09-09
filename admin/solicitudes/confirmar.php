<?php
/**
 * Confirma el pago de una solicitud. Admin y supervisor por igual: le
 * asigna el ciclo abierto en ese momento y suma el 60% de cada jugada
 * al pozo.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\SolicitudService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    $r = SolicitudService::crearDesde(getPDO())->confirmar($id, (int) currentUserId());

    setFlash('success',
        'Pago confirmado: ' . $r['cantidad'] . ' ' . ($r['cantidad'] === 1 ? 'jugada entró' : 'jugadas entraron')
        . ' al ciclo ' . $r['ciclo_numero'] . ' y el pozo sumó ' . formatPesos($r['al_pozo']) . '.'
    );
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/solicitudes/index.php');
exit;
