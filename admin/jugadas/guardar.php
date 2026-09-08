<?php
/** Handler POST de la carga de jugada. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$clienteId = (int) ($_POST['cliente_id'] ?? 0);
$numeros   = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];

try {
    $jugadas  = JugadaService::crearDesde(getPDO());
    $jugadaId = $jugadas->crear($clienteId, $numeros, currentUserId());
    $jugada   = $jugadas->buscarPorId($jugadaId);

    flushOld();

    $listaNumeros = implode(' ', array_map('num2', $jugada['numeros']));
    setFlash(
        'success',
        'Jugada #' . $jugadaId . ' cargada para ' . $jugada['cliente_nombre'] . ".\n"
        . $listaNumeros . ' · ' . formatPesos($jugada['importe']) . ' cobrados.'
    );

    // Volvemos al formulario vacio: lo normal es cargar varias seguidas.
    header('Location: ' . APP_URL . '/admin/jugadas/nueva.php');
    exit;

} catch (ValidacionException $e) {
    setOld(['cliente_id' => $clienteId, 'numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/jugadas/nueva.php');
    exit;
}
