<?php
/**
 * Resincroniza la clave del portal del cliente con su DNI actual.
 * Red de seguridad manual: normalmente no hace falta, porque
 * ClienteService::actualizar() ya regenera la clave sola al editar el DNI.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    $dni = (new ClienteService(getPDO()))->resetearClave($id);
    setFlash('success', 'Clave resincronizada. Entra al portal con su DNI (' . $dni . ').');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/clientes/form.php?id=' . $id);
exit;
