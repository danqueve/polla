<?php
/** Baja de cliente. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    $resultado = (new ClienteService(getPDO()))->eliminar($id);

    setFlash('success', $resultado === 'borrado'
        ? 'Cliente borrado.'
        : 'El cliente tenia jugadas cargadas o una cuenta de vendedor vinculada, asi que se desactivo en vez de borrarse.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/clientes/index.php');
exit;
