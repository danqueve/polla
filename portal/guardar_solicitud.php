<?php
/** Handler POST de "armar jugada". Genera la solicitud pendiente de pago. */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\SolicitudService;
use Polla\Support\ValidacionException;

requireCliente();
requirePost();

$clienteId   = (int) clienteActualId();
$gruposPost  = is_array($_POST['grupos'] ?? null) ? $_POST['grupos'] : [];
$promocionId = !empty($_POST['promocion_id']) ? (int) $_POST['promocion_id'] : null;

$listasDeNumeros = [];
foreach ($gruposPost as $grupo) {
    $listasDeNumeros[] = is_array($grupo['numeros'] ?? null) ? $grupo['numeros'] : [];
}

try {
    $resultado = SolicitudService::crearDesde(getPDO())->crear($clienteId, $listasDeNumeros, $promocionId);

    flushOld();

    header('Location: ' . APP_URL . '/portal/solicitud.php?id=' . $resultado['solicitud_id']);
    exit;

} catch (ValidacionException $e) {
    setOld(['grupos' => $gruposPost]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/portal/jugar.php');
    exit;
}
