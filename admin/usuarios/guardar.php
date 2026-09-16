<?php
/** Handler POST del ABM de usuarios. Solo admin. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\UsuarioService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$servicio = new UsuarioService(getPDO());
$id       = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    if ($id > 0) {
        $servicio->actualizar($id, $_POST);
        AuditoriaService::crearDesde(getPDO())->registrar(
            AuditoriaService::USUARIO_EDITADO, 'usuarios', $id, 'usuario', currentUserId()
        );
        setFlash('success', 'Usuario actualizado.');
    } else {
        $nuevoId = $servicio->crear($_POST);
        AuditoriaService::crearDesde(getPDO())->registrar(
            AuditoriaService::USUARIO_CREADO, 'usuarios', $nuevoId, 'usuario', currentUserId()
        );
        setFlash('success', 'Usuario creado.');
    }

    flushOld();
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld($_POST);
    setFlash('danger', implode("\n", $e->errores()));
    $destino = $id > 0
        ? APP_URL . '/admin/usuarios/form.php?id=' . $id
        : APP_URL . '/admin/usuarios/form.php';
    header('Location: ' . $destino);
    exit;
}
