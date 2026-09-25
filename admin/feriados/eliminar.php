<?php
/**
 * Quita un feriado marcado. Exclusivo del administrador.
 *
 * No reabre solo ningun ciclo que ya haya cerrado por este feriado --
 * si eso pasó por error, la via es SorteoService::corregir() sobre el
 * sorteo real que corresponda, igual que cualquier otra correccion de
 * ciclo cerrado.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\FeriadoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);
$db = getPDO();

try {
    FeriadoService::crearDesde($db)->quitar($id);
    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::FERIADO_ELIMINADO, 'feriados', $id, 'usuario', currentUserId()
    );
    setFlash('success', 'Feriado quitado.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/feriados/index.php');
exit;
