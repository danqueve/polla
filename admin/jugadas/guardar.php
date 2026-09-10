<?php
/** Handler POST de la carga de jugada (una o varias juntas). */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$clienteId    = (int) ($_POST['cliente_id'] ?? 0);
$gruposPost   = is_array($_POST['grupos'] ?? null) ? $_POST['grupos'] : [];
$promocionId  = !empty($_POST['promocion_id']) ? (int) $_POST['promocion_id'] : null;
$tipoJuego    = array_key_exists($_POST['tipo_juego'] ?? '', CicloService::TIPOS)
    ? $_POST['tipo_juego']
    : CicloService::TIPO_SEMANAL;
$volver       = APP_URL . '/admin/jugadas/nueva.php?tipo=' . $tipoJuego;

$listasDeNumeros = [];
foreach ($gruposPost as $grupo) {
    $listasDeNumeros[] = is_array($grupo['numeros'] ?? null) ? $grupo['numeros'] : [];
}

try {
    $jugadas = JugadaService::crearDesde(getPDO());
    $ids     = $jugadas->crearVarias($clienteId, $listasDeNumeros, currentUserId(), $promocionId, $tipoJuego);

    flushOld();

    $primero = $jugadas->buscarPorId($ids[0]);

    // Con promo, el importe puede variar en centavos entre jugadas del
    // mismo lote (repartirEnPartesIguales): sumamos cada una en vez de
    // multiplicar la primera por la cantidad.
    $totalCobrado = (float) $primero['importe'];
    for ($i = 1; $i < count($ids); $i++) {
        $totalCobrado += (float) $jugadas->buscarPorId($ids[$i])['importe'];
    }

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
    header('Location: ' . $volver);
    exit;

} catch (ValidacionException $e) {
    setOld(['cliente_id' => $clienteId, 'grupos' => $gruposPost]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $volver);
    exit;
}
