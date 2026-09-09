<?php
/**
 * Handler POST del alta/edicion de clientes.
 * Valida CSRF, delega en ClienteService y redirige con flash.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$clientes = new ClienteService(getPDO());
$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    if ($id > 0) {
        $clientes->actualizar($id, $_POST);
        flushOld();
        setFlash('success', 'Cliente actualizado.');
        header('Location: ' . APP_URL . '/admin/clientes/index.php');
        exit;
    }

    $nuevoId = $clientes->crear($_POST, currentUserId());
    $cliente = $clientes->buscarPorId($nuevoId);
    flushOld();

    setFlash(
        'success',
        'Cliente dado de alta con el N° ' . $cliente['nro_cliente'] . ".\n"
        . 'Su clave del portal es su DNI (' . $cliente['dni'] . '), fija.'
    );
    header('Location: ' . APP_URL . '/admin/clientes/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld($_POST);
    setFlash('danger', implode("\n", $e->errores()));
    $destino = $id > 0
        ? APP_URL . '/admin/clientes/form.php?id=' . $id
        : APP_URL . '/admin/clientes/form.php';
    header('Location: ' . $destino);
    exit;
}
