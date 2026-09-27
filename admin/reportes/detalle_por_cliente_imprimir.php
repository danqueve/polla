<?php
/**
 * Version para imprimir de "Detalle de Jugadas por Cliente": un
 * bloque por cliente con sus jugadas (numeros incluidos), sin el tema
 * del panel -- pensada para Ctrl+P / "Guardar como PDF" del navegador,
 * igual criterio que clientes_imprimir.php.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireAdmin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$jugadas = $reporte->jugadas($filtro, 5000);
$grupos  = $reporte->agruparPorCliente($jugadas);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Detalle de Jugadas por Cliente · <?= e(APP_NAME) ?></title>
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
        margin-bottom: 20px;
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
    .bloque-cliente { break-inside: avoid; margin-bottom: 22px; }
    .bloque-cliente h2 {
        font-size: 1rem;
        margin: 0 0 2px;
    }
    .bloque-cliente .subtitulo {
        font-size: 0.8rem;
        color: #6b7280;
        margin-bottom: 6px;
    }
    table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    th, td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
    th {
        border-bottom: 2px solid #d1d5db;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6b7280;
    }
    td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
    tbody tr { break-inside: avoid; }
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
            <h1>Detalle de Jugadas por Cliente</h1>
        </div>
        <div class="meta">
            Alcance: <?= e($alcance->rotulo()) ?><br>
            <?php if ($filtro->desde || $filtro->hasta): ?>
                Período: <?= e($filtro->desde ? formatFecha($filtro->desde) : 'desde el inicio') ?>
                al <?= e($filtro->hasta ? formatFecha($filtro->hasta) : 'hoy') ?><br>
            <?php endif; ?>
            <?= count($grupos) ?> clientes · <?= count($jugadas) ?> jugadas<br>
            Generado <?= e(date('d/m/Y H:i')) ?> por <?= e($usuario['nombre']) ?>
        </div>
    </div>

    <?php if (!$grupos): ?>
        <p class="vacio">No hay jugadas que coincidan con los filtros aplicados.</p>
    <?php else: ?>
        <?php foreach ($grupos as $g): ?>
            <div class="bloque-cliente">
                <h2><?= e($g['cliente']) ?></h2>
                <div class="subtitulo">
                    N° <?= e($g['nro_cliente']) ?> · DNI <?= e($g['dni']) ?> ·
                    <?= $g['cantidad'] ?> <?= $g['cantidad'] === 1 ? 'jugada' : 'jugadas' ?> ·
                    <?= e(formatPesos($g['importe'])) ?> en total
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Ciclo</th>
                            <th>Números</th>
                            <th class="num">Importe</th>
                            <th>Estado</th>
                            <th class="num">Premio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($g['jugadas'] as $j): ?>
                            <tr>
                                <td><?= e(formatFechaHora($j['fecha_carga'])) ?></td>
                                <td>#<?= (int) $j['ciclo'] ?></td>
                                <td><?= e(implode(' - ', array_map('num2', $j['numeros']))) ?></td>
                                <td class="num"><?= e(formatPesos($j['importe'])) ?></td>
                                <td><?= $j['estado'] === 'ganadora' ? 'Ganadora' : ($j['estado'] === 'perdedora' ? 'Perdió' : 'Activa') ?></td>
                                <td class="num"><?= $j['monto_premio'] !== null ? e(formatPesos($j['monto_premio'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
