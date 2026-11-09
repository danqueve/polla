<?php
/**
 * Anula una liquidacion de ganancias (fue un error): el monto vuelve a
 * figurar como pendiente de su ciclo. No borra nada -- la fila queda con
 * quien la hizo y quien la anulo. Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$db = getPDO();
$id = (int) ($_POST['id'] ?? 0);
$cicloId = (int) ($_POST['ciclo'] ?? 0);
$tipo    = array_key_exists($_POST['tipo'] ?? '', CicloService::TIPOS) ? $_POST['tipo'] : null;

// Solo a pantallas que existen, nunca a una URL del navegador.
$destino = ($_POST['volver'] ?? '') === 'ciclo' && $cicloId > 0
    ? APP_URL . '/admin/ganancias/ciclo.php?id=' . $cicloId
    : APP_URL . '/admin/ganancias/index.php' . ($tipo !== null ? '?tipo=' . $tipo : '');

try {
    $r = GananciaService::crearDesde($db)->anular($id, (int) currentUserId());
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $destino);
    exit;
} catch (Throwable $e) {
    error_log('[polla] Error al anular la liquidacion de ganancias ' . $id . ': ' . $e->getMessage());
    setFlash('danger',
        'No se pudo anular por un error interno. No se guardó nada: '
      . 'la liquidación sigue vigente y podés volver a intentarlo.');
    header('Location: ' . $destino);
    exit;
}

try {
    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::GANANCIA_LIQUIDACION_ANULADA, 'ganancias_liquidadas', $id,
        'usuario', currentUserId(), ['ciclo_id' => $r['ciclo_id'], 'monto' => $r['monto']]
    );
} catch (Throwable $e) {
    error_log('[polla] Liquidacion de ganancias ' . $id . ' anulada, pero no se pudo auditar: ' . $e->getMessage());
}

setFlash('success',
    'Se anuló la liquidación de ' . formatPesosDec($r['monto']) . '. Ese monto vuelve a figurar como pendiente del ciclo.'
);

header('Location: ' . $destino);
exit;
