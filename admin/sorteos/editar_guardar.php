<?php
/**
 * Handler POST de la correccion de un sorteo. Exclusivo del admin.
 *
 * SorteoService::corregir() hace todo en una transaccion (revertir el
 * cierre viejo si hacia falta, reemplazar los numeros, recotejar).
 * Aca solo traducimos el resultado a un mensaje para el admin.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id      = (int) ($_POST['id'] ?? 0);
$numeros = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];

$db = getPDO();

try {
    $servicio  = SorteoService::crearDesde($db);
    $resultado = $servicio->corregir($id, $numeros, currentUserId());
    flushOld();

    $prefijoReabierto = $resultado['reabierto']
        ? 'Se reabrió el ciclo y se recotejó todo de nuevo. '
        : '';

    // ── Hubo ganador: la semana/sabado se corto ─────────────
    if ($resultado['ganadores']) {
        $cantidad  = count($resultado['ganadores']);
        $ganadores = $servicio->ganadoresDeCiclo($resultado['ciclo_id']);

        $detalle = [];
        foreach ($ganadores as $ganador) {
            $detalle[] = $ganador['cliente_nombre'] . ': ' . formatPesos($ganador['monto_premio']);
        }

        setFlash('success',
            $prefijoReabierto
            . ($cantidad === 1 ? 'Hay un ganador!' : "Hay $cantidad ganadores!") . "\n"
            . implode(' · ', $detalle) . "\n"
            . 'Se repartió ' . formatPesos($resultado['pozo_repartido'])
            . ' y el ciclo quedó cerrado.'
        );

        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y era el ultimo de la secuencia ─────────
    if ($resultado['estado_cierre'] === CicloService::ESTADO_SIN_GANADOR) {
        setFlash('info',
            $prefijoReabierto
            . 'Números corregidos. Nadie acertó esta vez tampoco, así que el ciclo se cerró sin ganador.' . "\n"
            . 'El pozo de ' . formatPesos($resultado['arrastre']) . ' pasa al ciclo siguiente.'
        );
        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y todavia quedan sorteos ────────────────
    setFlash('success', $prefijoReabierto . 'Números corregidos. El ciclo sigue abierto.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php?ciclo=' . $resultado['ciclo_id']);
    exit;

} catch (ValidacionException $e) {
    setOld(['numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/sorteos/editar.php?id=' . $id);
    exit;
}
