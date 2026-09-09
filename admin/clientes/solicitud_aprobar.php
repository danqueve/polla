<?php
/** Aprueba una solicitud de autorregistro. Admin y supervisor por igual. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteRegistroService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    ClienteRegistroService::crearDesde(getPDO())->aprobar($id);
    setFlash('success', 'Solicitud aprobada. El cliente ya puede operar.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/clientes/solicitudes.php');
exit;
