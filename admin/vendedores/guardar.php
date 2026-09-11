<?php
/** Handler POST del ABM de vendedores. Solo admin. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$servicio = new VendedorService(getPDO());
$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    if ($id > 0) {
        $servicio->actualizar($id, $_POST);
        setFlash('success', 'Vendedor actualizado.');
    } else {
        $resultado = $servicio->crear($_POST);
        setFlash('success', $resultado['cliente_nuevo']
            ? 'Vendedor creado. Como todavía no era cliente, también se le creó su cuenta para jugar (N° '
              . $resultado['cliente_nro'] . ', clave: su DNI).'
            : 'Vendedor creado y vinculado a su cuenta de cliente existente (N° ' . $resultado['cliente_nro'] . ').');
    }

    flushOld();
    header('Location: ' . APP_URL . '/admin/vendedores/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld($_POST);
    setFlash('danger', implode("\n", $e->errores()));
    $destino = $id > 0
        ? APP_URL . '/admin/vendedores/form.php?id=' . $id
        : APP_URL . '/admin/vendedores/form.php';
    header('Location: ' . $destino);
    exit;
}
