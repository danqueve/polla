<?php
/**
 * Handler POST de la carga de un turno de sabado.
 *
 * SorteoService::registrarTurnoSabado() hace todo en una transaccion
 * (insertar, cotejar, liquidar, cerrar). Aca solo traducimos el
 * resultado a un mensaje que le sirva al supervisor.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$numeros = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];

$db = getPDO();

try {
    $resultado = SorteoService::crearDesde($db)->registrarTurnoSabado($numeros, currentUserId());
    flushOld();

    // ── Hubo ganador: el dia se corto ───────────────────────
    if ($resultado['ganadores']) {
        $cantidad  = count($resultado['ganadores']);
        $ganadores = SorteoService::crearDesde($db)->ganadoresDeCiclo($resultado['ciclo_id']);

        $detalle = [];
        foreach ($ganadores as $ganador) {
            $detalle[] = $ganador['cliente_nombre'] . ': ' . formatPesos($ganador['monto_premio']);
        }

        setFlash('success',
            ($cantidad === 1 ? 'Hay un ganador!' : "Hay $cantidad ganadores!") . "\n"
            . implode(' · ', $detalle) . "\n"
            . 'Se repartió ' . formatPesos($resultado['pozo_repartido'])
            . ' y el sábado quedó cerrado. Ya está abierto el del sábado que viene, en $0.'
        );

        header('Location: ' . APP_URL . '/admin/sabados/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y era el turno 5: cierra y arrastra el pozo ─
    if ($resultado['estado_cierre'] === CicloService::ESTADO_SIN_GANADOR) {
        setFlash('info',
            'Turno cargado. Nadie acertó los 10 en los 5 turnos, así que el sábado se cerró sin ganador.' . "\n"
            . 'El pozo de ' . formatPesos($resultado['arrastre'])
            . ' pasa entero al sábado que viene, que ya está abierto.'
        );
        header('Location: ' . APP_URL . '/admin/sabados/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y todavia quedan turnos ─────────────────
    setFlash('success', 'Turno cargado. Ningún acierto de 10 esta vez: el sábado sigue abierto.');
    header('Location: ' . APP_URL . '/admin/sabados/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld(['numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/sabados/sorteo_nuevo.php');
    exit;
}
