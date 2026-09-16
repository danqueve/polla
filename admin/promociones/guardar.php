<?php
/** Handler POST del alta/edicion de promociones. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\PromocionService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$promociones = new PromocionService(getPDO());
$id          = isset($_POST['id']) ? (int) $_POST['id'] : 0;

try {
    if ($id > 0) {
        $promociones->actualizar($id, $_POST, currentUserId());
        AuditoriaService::crearDesde(getPDO())->registrar(
            AuditoriaService::PROMOCION_GUARDADA, 'promociones', $id, 'usuario', currentUserId(), ['accion' => 'editar']
        );
        setFlash('success', 'Promoción actualizada.');
    } else {
        $nuevoId = $promociones->crear($_POST, currentUserId());
        AuditoriaService::crearDesde(getPDO())->registrar(
            AuditoriaService::PROMOCION_GUARDADA, 'promociones', $nuevoId, 'usuario', currentUserId(), ['accion' => 'crear']
        );
        setFlash('success', 'Promoción creada.');
    }

    flushOld();
    header('Location: ' . APP_URL . '/admin/promociones/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld($_POST);
    setFlash('danger', implode("\n", $e->errores()));
    $destino = $id > 0
        ? APP_URL . '/admin/promociones/form.php?id=' . $id
        : APP_URL . '/admin/promociones/form.php';
    header('Location: ' . $destino);
    exit;
}
