<?php
/** Handler POST: borra una favorita del cliente. No toca ninguna jugada ya jugada. */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\FavoritaService;
use Polla\Support\ValidacionException;

requireCliente();
requirePost();

$clienteId = (int) clienteActualId();
$tipoJuego = array_key_exists($_POST['tipo_juego'] ?? '', CicloService::TIPOS)
    ? $_POST['tipo_juego']
    : CicloService::TIPO_SEMANAL;

try {
    FavoritaService::crearDesde(getPDO())->eliminar($clienteId, (int) ($_POST['id'] ?? 0));
    setFlash('success', 'Favorita borrada.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/portal/favoritas.php?tipo=' . $tipoJuego);
exit;
