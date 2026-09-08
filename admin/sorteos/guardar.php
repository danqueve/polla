<?php
/**
 * Handler POST de la carga de sorteo.
 *
 * SorteoService::registrar() hace todo en una transaccion (insertar,
 * cotejar, liquidar, cerrar). Aca solo traducimos el resultado a un
 * mensaje que le sirva al supervisor.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$fecha   = trim($_POST['fecha'] ?? '');
$numeros = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];

$db = getPDO();

try {
    $resultado = SorteoService::crearDesde($db)->registrar($fecha, $numeros, currentUserId());
    flushOld();

    // ── Hubo ganador: la semana se corto ────────────────────
    if ($resultado['ganadores']) {
        $cantidad = count($resultado['ganadores']);
        $ganadores = SorteoService::crearDesde($db)->ganadoresDeCiclo($resultado['ciclo_id']);

        $detalle = [];
        foreach ($ganadores as $ganador) {
            $detalle[] = $ganador['cliente_nombre'] . ': ' . formatPesos($ganador['monto_premio']);
        }

        setFlash('success',
            ($cantidad === 1 ? 'Hay un ganador!' : "Hay $cantidad ganadores!") . "\n"
            . implode(' · ', $detalle) . "\n"
            . 'Se repartio ' . formatPesos($resultado['pozo_repartido'])
            . ' y el ciclo quedo cerrado. Ya esta abierto el de la semana que viene, en $0.'
        );

        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y era viernes: cierra y arrastra el pozo ─
    if ($resultado['estado_cierre'] === CicloService::ESTADO_SIN_GANADOR) {
        setFlash('info',
            'Sorteo cargado. Nadie acerto los 10 en toda la semana, asi que el ciclo se cerro sin ganador.' . "\n"
            . 'El pozo de ' . formatPesos($resultado['arrastre'])
            . ' pasa entero al ciclo nuevo, que ya esta abierto.'
        );
        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y todavia quedan sorteos ────────────────
    setFlash('success', 'Sorteo cargado. Ningun acierto de 10 esta vez: el ciclo sigue abierto.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld(['fecha' => $fecha, 'numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/sorteos/nuevo.php');
    exit;
}
