<?php
/**
 * Exportable CSV de jugadas, ganadores y estadisticas de numeros.
 *
 * Usa exactamente los mismos metodos de ReporteService que las
 * pantallas, con el mismo alcance armado desde la sesion. No hay una
 * consulta paralela para exportar: si un supervisor no lo ve en
 * pantalla, tampoco sale en el archivo.
 *
 * El CSV va con BOM y separado por punto y coma, que es lo que Excel
 * en español espera: con coma abre todo en una sola columna y sin BOM
 * rompe los acentos.
 *
 * jugadas/ganadores son exclusivos del admin (jugadas.php y
 * ganadores.php, las pantallas que exportan, tambien lo son).
 * numeros es de admin y supervisor por igual -mismo criterio que
 * admin/reportes/numeros.php-, asi que el guard depende de $que.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

$que = in_array($_GET['que'] ?? '', ['jugadas', 'ganadores', 'numeros'], true)
     ? $_GET['que'] : 'jugadas';

if ($que === 'numeros') {
    requireLogin();
} else {
    requireAdmin();
}

$db      = getPDO();
$usuario = currentUser();

$alcance = $que === 'numeros'
    ? AlcanceReporte::total((int) $usuario['id'])
    : AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

// Nombre con la fecha y, si es un supervisor, con su usuario: asi no se
// confunden dos archivos bajados el mismo dia por gente distinta.
$sufijo = $alcance->esAdmin() ? '' : '-' . preg_replace('/[^a-z0-9]/i', '', $usuario['usuario']);
$nombre = 'polla-' . $que . $sufijo . '-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Cache-Control: no-store');

$salida = fopen('php://output', 'w');

// BOM UTF-8 para que Excel en Windows respete los acentos.
fwrite($salida, "\xEF\xBB\xBF");

/** Escribe una fila con ; como separador. */
$fila = static function (array $campos) use ($salida): void {
    fputcsv($salida, $campos, ';', '"');
};

// Encabezado con el alcance, para que el archivo diga de que es.
$fila(['Decena de Oro - ' . ucfirst($que)]);
$fila(['Alcance', $alcance->rotulo()]);
$fila(['Generado', date('d/m/Y H:i'), 'por', $usuario['nombre']]);
if ($filtro->desde || $filtro->hasta) {
    $fila(['Período', $filtro->desde ?: 'desde el inicio', 'hasta', $filtro->hasta ?: 'hoy']);
}
$fila([]);

if ($que === 'jugadas') {

    $fila([
        'Jugada', 'Fecha de carga', 'N cliente', 'DNI', 'Cliente', 'Ciclo',
        'Numeros', 'Importe', 'Al pozo', 'A gastos', 'Estado', 'Premio', 'Cargado por',
    ]);

    foreach ($reporte->jugadas($filtro, 5000) as $j) {
        $fila([
            $j['id'],
            formatFechaHora($j['fecha_carga']),
            $j['nro_cliente'],
            $j['dni'],
            $j['cliente'],
            $j['ciclo'],
            implode(' ', array_map('num2', $j['numeros'])),
            number_format((float) $j['importe'], 2, ',', ''),
            number_format((float) $j['aporte_pozo'], 2, ',', ''),
            number_format((float) $j['aporte_gastos'], 2, ',', ''),
            $j['estado'],
            $j['monto_premio'] !== null ? number_format((float) $j['monto_premio'], 2, ',', '') : '',
            $j['cargado_por'] ?? '',
        ]);
    }

} elseif ($que === 'ganadores') {

    $fila([
        'Premio', 'Jugada', 'N cliente', 'DNI', 'Cliente', 'Telefono',
        'Ciclo', 'Sorteo', 'Numeros', 'Monto', 'Cargado por',
    ]);

    foreach ($reporte->ganadores($filtro, 5000) as $g) {
        $fila([
            $g['id'],
            $g['jugada_id'],
            $g['nro_cliente'],
            $g['dni'],
            $g['cliente'],
            $g['telefono'] ?? '',
            $g['ciclo'],
            formatFecha($g['sorteo']),
            implode(' ', array_map('num2', $g['numeros'])),
            number_format((float) $g['monto_premio'], 2, ',', ''),
            $g['cargado_por'] ?? '',
        ]);
    }

} else {

    $stats = $reporte->estadisticasNumeros($filtro);

    $fila(['Sorteos analizados', $stats['total_sorteos']]);
    $fila([]);
    $fila(['Número', 'Apariciones', 'Porcentaje', 'Atraso (sorteos)']);

    foreach ($stats['numeros'] as $f) {
        $fila([
            num2($f['numero']),
            $f['apariciones'],
            number_format($f['porcentaje'], 1, ',', '') . '%',
            $f['atraso'] === null ? 'Nunca en este período' : $f['atraso'],
        ]);
    }
}

fclose($salida);
exit;
