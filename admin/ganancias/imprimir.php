<?php
/**
 * Version para imprimir de Ganancias: la tabla por ciclo y las
 * liquidaciones, sin el tema del panel -- pensada para Ctrl+P / "Guardar
 * como PDF" del navegador, igual criterio que
 * admin/reportes/clientes_imprimir.php (el proyecto no tiene libreria de
 * PDF). Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Services\ParametroService;
use Polla\Support\ValidacionException;

requireAdmin();

$db        = getPDO();
$usuario   = currentUser();
$ganancias = GananciaService::crearDesde($db);

$tipo = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS) ? $_GET['tipo'] : null;

try {
    $filas     = $ganancias->porCiclo($tipo);
    $resumen   = GananciaService::resumirFilas($filas);
    $historial = array_filter(
        $ganancias->historial(null, 200, $tipo),
        static fn(array $h): bool => $h['anulada_en'] === null
    );
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/ganancias/index.php');
    exit;
}

$porcentajePozo   = (new ParametroService($db))->porcentajePozo();
$porcentajeGastos = 100 - $porcentajePozo;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ganancias · <?= e(APP_NAME) ?></title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #1f2937;
        margin: 0;
        padding: 24px;
        background: #fff;
    }
    .encabezado {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        border-bottom: 2px solid #1f2937;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .encabezado h1 { font-size: 1.25rem; margin: 0 0 2px; }
    .encabezado .marca {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .meta { font-size: 0.8rem; color: #4b5563; text-align: right; line-height: 1.5; }
    .btn-imprimir {
        font: inherit;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 8px;
        border: 1px solid #1f2937;
        background: #1f2937;
        color: #fff;
        cursor: pointer;
    }
    .btn-imprimir:hover { opacity: .9; }
    h2 { font-size: 0.95rem; margin: 22px 0 6px; }
    table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    th, td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    th {
        border-bottom: 2px solid #1f2937;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #4b5563;
    }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    tfoot td { border-top: 2px solid #1f2937; border-bottom: none; font-weight: 700; }
    tbody tr { break-inside: avoid; }
    .chico { font-size: 0.7rem; color: #6b7280; }
    .nota { margin-top: 10px; font-size: 0.72rem; color: #6b7280; }
    .vacio { text-align: center; color: #6b7280; padding: 32px 0; }
    @media print {
        body { padding: 0; }
        .no-imprimir { display: none !important; }
        @page { margin: 1.5cm; }
    }
</style>
</head>
<body>

    <div class="no-imprimir" style="text-align:right; margin-bottom:12px">
        <button type="button" class="btn-imprimir" onclick="window.print()">
            🖨️ Imprimir / Guardar como PDF
        </button>
    </div>

    <div class="encabezado">
        <div>
            <div class="marca"><?= e(APP_NAME) ?></div>
            <h1>Ganancias por ciclo</h1>
        </div>
        <div class="meta">
            Juego: <?= e($tipo !== null ? (CicloService::TIPOS[$tipo] ?? $tipo) : 'Todos') ?><br>
            Ganancia = <?= (float) $porcentajeGastos ?>% de cada jugada pagada<br>
            Generado <?= e(date('d/m/Y H:i')) ?> por <?= e($usuario['nombre']) ?>
        </div>
    </div>

    <?php if (!$filas): ?>
        <p class="vacio">Todavía no hay jugadas pagadas para mostrar.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Ciclo</th>
                    <th class="num">Jugadas</th>
                    <th class="num">Recaudado</th>
                    <th class="num">Al pozo (<?= (float) $porcentajePozo ?>%)</th>
                    <th class="num">Ganancia (<?= (float) $porcentajeGastos ?>%)</th>
                    <th class="num">Liquidado</th>
                    <th class="num">Pendiente</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <tr>
                        <td>
                            <strong><?= e((CicloService::TIPOS[$f['tipo']] ?? $f['tipo']) . ' #' . $f['numero']) ?></strong>
                            <span class="chico"> · <?= e(CicloService::rotulo($f)) ?> ·
                                <?= e(CicloService::ESTADOS[$f['estado']] ?? $f['estado']) ?></span>
                        </td>
                        <td class="num"><?= $f['jugadas'] ?></td>
                        <td class="num"><?= e(formatPesosDec($f['recaudado'])) ?></td>
                        <td class="num"><?= e(formatPesosDec($f['al_pozo'])) ?></td>
                        <td class="num"><?= e(formatPesosDec($f['ganancia'])) ?></td>
                        <td class="num"><?= $f['liquidado'] > 0 ? e(formatPesosDec($f['liquidado'])) : '—' ?></td>
                        <td class="num"><?= $f['cerrado'] ? e(formatPesosDec(max(0.0, $f['pendiente']))) : 'en curso' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Total</td>
                    <td class="num"><?= $resumen['jugadas'] ?></td>
                    <td class="num"><?= e(formatPesosDec($resumen['recaudado'])) ?></td>
                    <td class="num"><?= e(formatPesosDec($resumen['al_pozo'])) ?></td>
                    <td class="num"><?= e(formatPesosDec($resumen['ganancia'])) ?></td>
                    <td class="num"><?= e(formatPesosDec($resumen['liquidado'])) ?></td>
                    <td class="num"><?= e(formatPesosDec($resumen['pendiente'])) ?></td>
                </tr>
            </tfoot>
        </table>
        <p class="nota">
            Pendiente = ganancia de ciclos cerrados que todavía no se liquidó. Los ciclos abiertos
            ("en curso") suman <?= e(formatPesosDec($resumen['en_curso'])) ?> que se podrán liquidar cuando cierren.
            La ganancia es antes de las comisiones de referidos y del premio garantizado.
        </p>

        <?php if ($historial): ?>
            <h2>Liquidaciones realizadas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ciclo</th>
                        <th class="num">Monto</th>
                        <th>Nota</th>
                        <th>Liquidó</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $h): ?>
                        <tr>
                            <td><?= e(formatFechaHora($h['fecha'])) ?></td>
                            <td><?= e((CicloService::TIPOS[$h['tipo']] ?? $h['tipo']) . ' #' . $h['numero']) ?></td>
                            <td class="num"><?= e(formatPesosDec($h['monto'])) ?></td>
                            <td><?= e($h['nota'] ?? '') ?></td>
                            <td><?= e($h['liquidado_por_nombre'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

</body>
</html>
