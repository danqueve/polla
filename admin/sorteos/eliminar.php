<?php
/** Borrado de sorteo. Exclusivo del admin y solo con el ciclo abierto. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id      = (int) ($_POST['id'] ?? 0);
$volverA = (int) ($_POST['volver_a'] ?? 0);

try {
    SorteoService::crearDesde(getPDO())->eliminar($id);
    setFlash('success', 'Sorteo borrado. Podés volver a cargarlo con los numeros corregidos.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

$destino = APP_URL . '/admin/sorteos/index.php';
if ($volverA > 0) {
    $destino .= '?ciclo=' . $volverA;
}

header('Location: ' . $destino);
exit;
