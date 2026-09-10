<?php
/** Borrado de un turno de sabado. Exclusivo del admin y solo con el ciclo abierto. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id      = (int) ($_POST['id'] ?? 0);
$volverA = (int) ($_POST['volver_a'] ?? 0);

try {
    SorteoService::crearDesde(getPDO())->eliminar($id);
    setFlash('success', 'Turno borrado. Podés volver a cargarlo con los números corregidos.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

$destino = APP_URL . '/admin/sabados/ciclos.php';
if ($volverA > 0) {
    $destino = APP_URL . '/admin/sabados/ver.php?id=' . $volverA;
}

header('Location: ' . $destino);
exit;
