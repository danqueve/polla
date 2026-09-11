<?php
/** Baja de vendedor. Solo admin. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id = (int) ($_POST['id'] ?? 0);

try {
    $resultado = (new VendedorService(getPDO()))->eliminar($id);
    setFlash('success', $resultado === 'borrado' ? 'Vendedor borrado.' : 'El vendedor ya tenía referidos: se desactivó en vez de borrarse.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/vendedores/index.php');
exit;
