<?php
/**
 * Liquida (retira) la ganancia pendiente de un ciclo cerrado, o de todos
 * los ciclos cerrados con pendiente (todo=1). Exclusivo del administrador.
 *
 * El monto nunca viene del formulario: es siempre el pendiente que
 * GananciaService recalcula bajo candado. Lo unico que se recibe es el
 * ciclo (o "todo"), una nota opcional y a donde volver.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$db      = getPDO();
$ganancias = GananciaService::crearDesde($db);

$cicloId = (int) ($_POST['ciclo'] ?? 0);
$todo    = !empty($_POST['todo']);
$nota    = isset($_POST['nota']) && is_string($_POST['nota']) ? $_POST['nota'] : null;
$tipo    = array_key_exists($_POST['tipo'] ?? '', CicloService::TIPOS) ? $_POST['tipo'] : null;

// A donde volver: solo a las dos pantallas que existen, nunca a una URL
// que venga del navegador.
$destino = ($_POST['volver'] ?? '') === 'ciclo' && $cicloId > 0 && !$todo
    ? APP_URL . '/admin/ganancias/ciclo.php?id=' . $cicloId
    : APP_URL . '/admin/ganancias/index.php' . ($tipo !== null ? '?tipo=' . $tipo : '');

try {
    if ($todo) {
        $r = $ganancias->liquidarTodo($tipo, $nota, (int) currentUserId());
    } else {
        $monto = $ganancias->liquidar($cicloId, $nota, (int) currentUserId());
    }
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $destino);
    exit;
} catch (Throwable $e) {
    // liquidar()/liquidarTodo() hacen rollback: no quedo nada a medias.
    error_log('[polla] Error al liquidar ganancias (ciclo ' . $cicloId . ', todo=' . (int) $todo . '): ' . $e->getMessage());
    setFlash('danger',
        'No se pudo liquidar por un error interno. No se guardó nada: '
      . 'el pendiente sigue igual y podés volver a intentarlo.');
    header('Location: ' . $destino);
    exit;
}

// La liquidacion ya esta registrada: si la auditoria falla, no se le puede
// decir al administrador que no se guardo nada.
try {
    $audit = AuditoriaService::crearDesde($db);
    if ($todo) {
        $audit->registrar(
            AuditoriaService::GANANCIA_LIQUIDADA, 'ciclos', null, 'usuario', currentUserId(),
            ['masiva' => true, 'tipo' => $tipo, 'ciclos' => $r['ids'], 'monto' => $r['monto'], 'nota' => $nota]
        );
    } else {
        $audit->registrar(
            AuditoriaService::GANANCIA_LIQUIDADA, 'ciclos', $cicloId, 'usuario', currentUserId(),
            ['monto' => $monto, 'nota' => $nota]
        );
    }
} catch (Throwable $e) {
    error_log('[polla] Ganancias liquidadas, pero no se pudo auditar: ' . $e->getMessage());
}

if ($todo) {
    setFlash('success',
        'Se liquidaron ' . $r['ciclos'] . ($r['ciclos'] === 1 ? ' ciclo' : ' ciclos')
        . ' por un total de ' . formatPesosDec($r['monto']) . '.'
    );
} else {
    $ciclo = $ganancias->deCiclo($cicloId);
    $rotulo = $ciclo
        ? (CicloService::TIPOS[$ciclo['tipo']] ?? $ciclo['tipo']) . ' #' . $ciclo['numero']
        : 'el ciclo';
    setFlash('success', 'Se liquidaron ' . formatPesosDec($monto) . ' de ' . $rotulo . '.');
}

header('Location: ' . $destino);
exit;
