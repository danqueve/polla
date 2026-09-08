<?php
/** Baja de usuario del panel. Solo admin. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\UsuarioService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    $resultado = (new UsuarioService(getPDO()))->eliminar($id, (int) currentUserId());

    setFlash('success', $resultado === 'borrado'
        ? 'Usuario borrado.'
        : 'El usuario ya tenia movimientos cargados, asi que se desactivo en vez de borrarse.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/usuarios/index.php');
exit;
