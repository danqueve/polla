<?php
/**
 * Version para imprimir de "Jugadas por Cliente": misma tabla plana
 * que la pantalla y el CSV (Ciclo, Cliente, Cantidad, Importe), sin el
 * tema del panel (sidebar, topbar) -- una pagina angosta pensada para
 * Ctrl+P / "Guardar como PDF" del navegador, no para navegar.
 *
 * Mismo alcance y mismos filtros que clientes.php: un supervisor no
 * puede sacar por acá lo que la pantalla ya le esconde.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireAdmin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$filas = $reporte->jugadasPorCliente($filtro);

$totalJugadas = 0;
$totalImporte = 0.0;
foreach ($filas as $f) {
    $totalJugadas += (int) $f['cantidad'];
    $totalImporte += (float) $f['importe'];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Jugadas por Cliente · <?= e(APP_NAME) ?></title>
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
    .encabezado h1 {
        font-size: 1.25rem;
        margin: 0 0 2px;
    }
    .encabezado .marca {
        font-size: 0.8rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6b7280;
    }
    .meta {
        font-size: 0.8rem;
        color: #4b5563;
        text-align: right;
        line-height: 1.5;
    }
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
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    th, td {
        padding: 6px 8px;
        border-bottom: 1px solid #e5e7eb;
        text-align: left;
    }
    th {
        border-bottom: 2px solid #1f2937;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #4b5563;
    }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    tfoot td {
        border-top: 2px solid #1f2937;
        border-bottom: none;
        font-weight: 700;
    }
    tbody tr { break-inside: avoid; }
    .vacio {
        text-align: center;
        color: #6b7280;
        padding: 32px 0;
    }
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
            <h1>Jugadas por Cliente</h1>
        </div>
        <div class="meta">
            Alcance: <?= e($alcance->rotulo()) ?><br>
            <?php if ($filtro->desde || $filtro->hasta): ?>
                Período: <?= e($filtro->desde ? formatFecha($filtro->desde) : 'desde el inicio') ?>
                al <?= e($filtro->hasta ? formatFecha($filtro->hasta) : 'hoy') ?><br>
            <?php endif; ?>
            Generado <?= e(date('d/m/Y H:i')) ?> por <?= e($usuario['nombre']) ?>
        </div>
    </div>

    <?php if (!$filas): ?>
        <p class="vacio">No hay jugadas que coincidan con los filtros aplicados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Ciclo</th>
                    <th>Cliente</th>
                    <th class="num">Cantidad de Jugadas</th>
                    <th class="num">Importe Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $f): ?>
                    <?php $cicloRotulo = CicloService::rotulo([
                        'fecha_inicio' => $f['fecha_inicio'],
                        'fecha_fin'    => $f['fecha_fin'],
                    ]); ?>
                    <tr>
                        <td>
                            #<?= (int) $f['ciclo_numero'] ?>
                            · <?= e(CicloService::TIPOS[$f['ciclo_tipo']] ?? $f['ciclo_tipo']) ?>
                            · <?= e($cicloRotulo) ?>
                        </td>
                        <td><?= e($f['cliente']) ?> (N° <?= e($f['nro_cliente']) ?>)</td>
                        <td class="num"><?= (int) $f['cantidad'] ?></td>
                        <td class="num"><?= e(formatPesos($f['importe'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2"><?= count($filas) ?> filas (cliente x ciclo)</td>
                    <td class="num"><?= $totalJugadas ?></td>
                    <td class="num"><?= e(formatPesos($totalImporte)) ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

</body>
</html>
