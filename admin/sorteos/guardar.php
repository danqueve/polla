<?php
/**
 * Handler POST de la carga de sorteo.
 *
 * SorteoService::registrar() hace todo en una transaccion (insertar,
 * cotejar, liquidar, cerrar). Aca solo traducimos el resultado a un
 * mensaje que le sirva al supervisor.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireLogin();
requirePost();

$fecha   = trim($_POST['fecha'] ?? '');
$numeros = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];

$db = getPDO();
$numerosPorJugada = (new ParametroService($db))->numerosPorJugada();

try {
    $resultado = SorteoService::crearDesde($db)->registrar($fecha, $numeros, currentUserId());
    flushOld();
    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::SORTEO_CARGADO, 'sorteos', (int) $resultado['sorteo_id'],
        'usuario', currentUserId(), ['fecha' => $fecha, 'ciclo_id' => $resultado['ciclo_id']]
    );

    // ── Hubo ganador: la semana se corto ────────────────────
    if ($resultado['ganadores']) {
        $cantidad = count($resultado['ganadores']);
        $ganadores = SorteoService::crearDesde($db)->ganadoresDeCiclo($resultado['ciclo_id']);

        $detalle = [];
        foreach ($ganadores as $ganador) {
            $detalle[] = $ganador['cliente_nombre'] . ': ' . formatPesos($ganador['monto_premio']);
        }

        // Sin "arranca en $0": con la carga anticipada, la semana que viene
        // normalmente ya tiene jugadas (y plata) cuando sale un ganador.
        setFlash('success',
            ($cantidad === 1 ? '¡Hay un ganador!' : "¡Hay $cantidad ganadores!") . "\n"
            . implode(' · ', $detalle) . "\n"
            . 'Se repartió ' . formatPesos($resultado['pozo_repartido'])
            . ' y el ciclo quedó cerrado. Ya está abierto el de la semana que viene.'
        );

        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y era viernes: cierra y arrastra el pozo ─
    if ($resultado['estado_cierre'] === CicloService::ESTADO_SIN_GANADOR) {
        setFlash('info',
            'Sorteo cargado. Nadie completó sus ' . $numerosPorJugada . ' números en toda la semana, así que el ciclo se cerró sin ganador.' . "\n"
            . 'El pozo de ' . formatPesos($resultado['arrastre'])
            . ' pasa entero al ciclo nuevo, que ya está abierto.'
        );
        header('Location: ' . APP_URL . '/admin/ciclos/ver.php?id=' . $resultado['ciclo_id']);
        exit;
    }

    // ── Sin ganador y todavia quedan sorteos ────────────────
    // El cotejo es acumulado (Fase 13): no se trata de "acertar los N en
    // este sorteo", sino de que nadie haya completado todavia sus numeros
    // sumando los sorteos de la semana.
    setFlash('success', 'Sorteo cargado. Nadie completó todavía sus ' . $numerosPorJugada . ' números: el ciclo sigue abierto.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php');
    exit;

} catch (ValidacionException $e) {
    setOld(['fecha' => $fecha, 'numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/sorteos/nuevo.php');
    exit;
}
