<?php
/** Handler POST: cambia (o borra) el nombre de una favorita del cliente. */
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
$nombre    = isset($_POST['nombre']) && is_string($_POST['nombre']) ? $_POST['nombre'] : null;

try {
    FavoritaService::crearDesde(getPDO())->renombrar($clienteId, (int) ($_POST['id'] ?? 0), $nombre);
    setFlash('success', 'Nombre actualizado.');
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/portal/favoritas.php?tipo=' . $tipoJuego);
exit;
