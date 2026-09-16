<?php
/**
 * Rechaza una solicitud de pago. Exclusivo del administrador: no toca
 * ningún ciclo ni el pozo, porque mientras estuvo pendiente nunca sumó.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\SolicitudService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    SolicitudService::crearDesde(getPDO())->rechazar($id, (int) currentUserId());
    AuditoriaService::crearDesde(getPDO())->registrar(
        AuditoriaService::SOLICITUD_RECHAZADA, 'solicitudes', $id, 'usuario', currentUserId()
    );
    setFlash('success', 'Solicitud rechazada.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/solicitudes/index.php');
exit;
