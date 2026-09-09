<?php
/** Rechaza una solicitud de autorregistro. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteRegistroService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    ClienteRegistroService::crearDesde(getPDO())->rechazar($id);
    setFlash('success', 'Solicitud rechazada.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/clientes/solicitudes.php');
exit;
