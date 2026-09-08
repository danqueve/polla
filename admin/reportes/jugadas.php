<?php
/** Detalle de jugadas, con filtros y exportable. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireLogin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$jugadas = $reporte->jugadas($filtro);
$resumen = $reporte->resumen($filtro);

$pageTitle  = 'Jugadas · Reportes · ' . APP_NAME;
$navSeccion = 'reportes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/reportes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Reportes
    </a>

    <h1 class="pantalla__titulo">Detalle de jugadas</h1>
    <p class="pantalla__bajada">
        <?= count($jugadas) ?> jugadas · <?= e(formatPesos($resumen['recaudado'])) ?>
        <?php if (count($jugadas) >= 500): ?>
            <br><span class="text-warning">Se muestran las 500 más recientes. Acotá el rango de fechas para ver el resto.</span>
        <?php endif; ?>
    </p>

    <div class="mt-3">
        <?php
        $accion = APP_URL . '/admin/reportes/jugadas.php';
        require __DIR__ . '/../../includes/reporte_filtros.php';
        ?>
    </div>

    <?php if (!$jugadas): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            No hay jugadas que entren en este filtro.
        </div>

    <?php else: ?>

        <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'jugadas'])) ?>"
           class="btn btn-primary w-100 mb-3">
            <i class="bi bi-download"></i> Descargar CSV (<?= count($jugadas) ?> filas)
        </a>

        <div class="tabla-scroll">
            <table class="tabla-reporte">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Ciclo</th>
                        <th>Números</th>
                        <th class="num">Importe</th>
                        <th class="num">Pozo</th>
                        <th class="num">Gastos</th>
                        <th>Estado</th>
                        <th class="num">Premio</th>
                        <th>Cargó</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jugadas as $j): ?>
                        <tr>
                            <td class="num"><?= e(formatFechaHora($j['fecha_carga'])) ?></td>
                            <td>
                                <?= e($j['cliente']) ?>
                                <div class="fila__meta">N° <?= e($j['nro_cliente']) ?></div>
                            </td>
                            <td class="num"><?= (int) $j['ciclo'] ?></td>
                            <td class="cifra">
                                <?= e(implode(' ', array_map('num2', $j['numeros']))) ?>
                            </td>
                            <td class="num"><?= e(formatPesos($j['importe'])) ?></td>
                            <td class="num"><?= e(formatPesos($j['aporte_pozo'])) ?></td>
                            <td class="num"><?= e(formatPesos($j['aporte_gastos'])) ?></td>
                            <td>
                                <?php if ($j['estado'] === 'ganadora'): ?>
                                    <span class="etiqueta etiqueta--oro">Ganadora</span>
                                <?php elseif ($j['estado'] === 'perdedora'): ?>
                                    <span class="etiqueta etiqueta--gris">Perdió</span>
                                <?php else: ?>
                                    <span class="etiqueta etiqueta--verde">Activa</span>
                                <?php endif; ?>
                            </td>
                            <td class="num">
                                <?= $j['monto_premio'] !== null ? e(formatPesos($j['monto_premio'])) : '—' ?>
                            </td>
                            <td><?= e($j['cargado_por'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="tabla-pista">
            <i class="bi bi-arrow-left-right"></i> Deslizá la tabla de costado para ver todas las columnas.
        </p>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
