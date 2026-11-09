<?php
/**
 * Exportable CSV de ganancias: una fila por ciclo con su recaudacion, la
 * ganancia (el porcentaje de gastos), lo liquidado y lo pendiente, mas el
 * detalle de las liquidaciones hechas. Exclusivo del administrador.
 *
 * Usa las mismas filas que la pantalla (GananciaService::porCiclo()), asi
 * que lo que se baja suma exactamente lo que se ve.
 *
 * Mismo formato que admin/reportes/exportar.php: BOM UTF-8 y punto y
 * coma, que es lo que Excel en espanol espera.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Support\ValidacionException;

requireAdmin();

$db        = getPDO();
$usuario   = currentUser();
$ganancias = GananciaService::crearDesde($db);

$tipo = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS) ? $_GET['tipo'] : null;

try {
    $filas     = $ganancias->porCiclo($tipo);
    $resumen   = GananciaService::resumirFilas($filas);
    $historial = $ganancias->historial(null, 1000, $tipo);
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/ganancias/index.php');
    exit;
}

$nombre = 'polla-ganancias' . ($tipo !== null ? '-' . $tipo : '') . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'w');
fwrite($salida, "\xEF\xBB\xBF");   // BOM para que Excel respete los acentos

$fila = static function (array $campos) use ($salida): void {
    fputcsv($salida, $campos, ';', '"');
};
$monto = static fn($n): string => number_format((float) $n, 2, ',', '');

$fila(['Decena de Oro - Ganancias']);
$fila(['Juego', $tipo !== null ? (CicloService::TIPOS[$tipo] ?? $tipo) : 'Todos']);
$fila(['Generado', date('d/m/Y H:i'), 'por', $usuario['nombre']]);
$fila([]);

$fila(['Juego', 'Ciclo', 'Desde', 'Hasta', 'Estado', 'Jugadas', 'Recaudado', 'Al pozo', 'Ganancia', 'Liquidado', 'Pendiente']);
foreach ($filas as $f) {
    $fila([
        CicloService::TIPOS[$f['tipo']] ?? $f['tipo'],
        $f['numero'],
        formatFecha($f['fecha_inicio']),
        formatFecha($f['fecha_fin']),
        CicloService::ESTADOS[$f['estado']] ?? $f['estado'],
        $f['jugadas'],
        $monto($f['recaudado']),
        $monto($f['al_pozo']),
        $monto($f['ganancia']),
        $monto($f['liquidado']),
        // Un ciclo abierto todavia no tiene "pendiente": se junta en curso.
        $f['cerrado'] ? $monto(max(0.0, $f['pendiente'])) : '',
    ]);
}
$fila([
    'TOTAL', '', '', '', '',
    $resumen['jugadas'],
    $monto($resumen['recaudado']),
    $monto($resumen['al_pozo']),
    $monto($resumen['ganancia']),
    $monto($resumen['liquidado']),
    $monto($resumen['pendiente']),
]);
$fila(['En curso (ciclos abiertos, todavia no liquidable)', '', '', '', '', '', '', '', $monto($resumen['en_curso'])]);

$fila([]);
$fila(['Liquidaciones']);
$fila(['Fecha', 'Juego', 'Ciclo', 'Monto', 'Estado', 'Nota', 'Liquidado por', 'Anulada el', 'Anulada por']);
foreach ($historial as $h) {
    $fila([
        formatFechaHora($h['fecha']),
        CicloService::TIPOS[$h['tipo']] ?? $h['tipo'],
        $h['numero'],
        $monto($h['monto']),
        $h['anulada_en'] !== null ? 'Anulada' : 'Vigente',
        $h['nota'] ?? '',
        $h['liquidado_por_nombre'] ?? '',
        $h['anulada_en'] !== null ? formatFechaHora($h['anulada_en']) : '',
        $h['anulada_por_nombre'] ?? '',
    ]);
}

fclose($salida);
exit;
