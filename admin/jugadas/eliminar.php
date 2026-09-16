<?php
/** Borrado de jugada. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id      = (int) ($_POST['id'] ?? 0);
$volverA = (int) ($_POST['volver_a'] ?? 0);

try {
    JugadaService::crearDesde(getPDO())->eliminar($id);
    AuditoriaService::crearDesde(getPDO())->registrar(
        AuditoriaService::JUGADA_ELIMINADA, 'jugadas', $id, 'usuario', currentUserId()
    );
    setFlash('success', 'Jugada borrada. El aporte se descontó del pozo.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

$destino = APP_URL . '/admin/jugadas/index.php';
if ($volverA > 0) {
    $destino .= '?ciclo=' . $volverA;
}

header('Location: ' . $destino);
exit;
