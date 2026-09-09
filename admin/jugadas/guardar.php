<?php
/** Handler POST de la carga de jugada (una o varias juntas). */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$clienteId  = (int) ($_POST['cliente_id'] ?? 0);
$gruposPost = is_array($_POST['grupos'] ?? null) ? $_POST['grupos'] : [];

$listasDeNumeros = [];
foreach ($gruposPost as $grupo) {
    $listasDeNumeros[] = is_array($grupo['numeros'] ?? null) ? $grupo['numeros'] : [];
}

try {
    $jugadas = JugadaService::crearDesde(getPDO());
    $ids     = $jugadas->crearVarias($clienteId, $listasDeNumeros, currentUserId());

    flushOld();

    $primero      = $jugadas->buscarPorId($ids[0]);
    $totalCobrado = (float) $primero['importe'] * count($ids);

    if (count($ids) === 1) {
        $listaNumeros = implode(' ', array_map('num2', $primero['numeros']));
        $mensaje = 'Jugada #' . $ids[0] . ' cargada para ' . $primero['cliente_nombre'] . ".\n"
            . $listaNumeros . ' · ' . formatPesos($totalCobrado) . ' cobrados.';
    } else {
        $mensaje = count($ids) . ' jugadas cargadas para ' . $primero['cliente_nombre']
            . ' (#' . implode(', #', $ids) . ").\n"
            . formatPesos($totalCobrado) . ' cobrados en total.';
    }

    setFlash('success', $mensaje);

    // Volvemos al formulario vacio: lo normal es cargar varias tandas seguidas.
    header('Location: ' . APP_URL . '/admin/jugadas/nueva.php');
    exit;

} catch (ValidacionException $e) {
    setOld(['cliente_id' => $clienteId, 'grupos' => $gruposPost]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/jugadas/nueva.php');
    exit;
}
