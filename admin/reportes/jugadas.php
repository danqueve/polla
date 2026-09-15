<?php
/** Detalle de jugadas con filtros y diseño Gentelella. */
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

$jugadas = $reporte->jugadas($filtro);
$resumen = $reporte->resumen($filtro);

$pageTitle        = 'Reporte de Jugadas · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Detalle de Jugadas';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Detalle de Jugadas', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Detalle de Jugadas</h1>
            <p class="g-page-subtitle">
                <?= count($jugadas) ?> jugadas listadas · Recaudación acumulada: <?= e(formatPesos($resumen['recaudado'])) ?>
                <?php if (count($jugadas) >= 500): ?>
                    <span class="text-warning fw-semibold d-block mt-1">Se muestran las 500 más recientes. Acote el rango de fechas para ver el resto.</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <?php if ($jugadas): ?>
                <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'jugadas'])) ?>"
                   class="g-btn g-btn--primary">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <?php
            $accion = APP_URL . '/admin/reportes/jugadas.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <!-- Tabla de Jugadas -->
    <div class="g-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-table me-1"></i>
                Registros de Jugadas
            </h2>
        </div>

        <div class="g-card__body p-0">
            <?php if (!$jugadas): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-ticket-perforated fs-1 d-block mb-3 text-muted"></i>
                    No hay jugadas que coincidan con los filtros aplicados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Ciclo</th>
                                <th>Números Jugados</th>
                                <th class="text-end">Importe</th>
                                <th class="text-end">Al Pozo</th>
                                <th class="text-end">Gastos</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Premio</th>
                                <th>Cargado Por</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jugadas as $j): ?>
                                <tr>
                                    <td class="text-nowrap small text-muted"><?= e(formatFechaHora($j['fecha_carga'])) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= e($j['cliente']) ?></div>
                                        <div class="small text-muted">N° <?= e($j['nro_cliente']) ?></div>
                                    </td>
                                    <td class="fw-bold">#<?= (int) $j['ciclo'] ?></td>
                                    <td>
                                        <div class="bolillas">
                                            <?php foreach ($j['numeros'] as $num): ?>
                                                <span class="bolilla" style="width:24px;height:24px;font-size:.65rem;line-height:24px"><?= num2($num) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold"><?= e(formatPesos($j['importe'])) ?></td>
                                    <td class="text-end text-muted small"><?= e(formatPesos($j['aporte_pozo'])) ?></td>
                                    <td class="text-end text-muted small"><?= e(formatPesos($j['aporte_gastos'])) ?></td>
                                    <td class="text-center">
                                        <?php if ($j['estado'] === 'ganadora'): ?>
                                            <span class="g-badge g-badge--warning">Ganadora</span>
                                        <?php elseif ($j['estado'] === 'perdedora'): ?>
                                            <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                                        <?php else: ?>
                                            <span class="g-badge g-badge--success">Activa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold text-success">
                                        <?= $j['monto_premio'] !== null ? e(formatPesos($j['monto_premio'])) : '—' ?>
                                    </td>
                                    <td class="small text-muted"><?= e($j['cargado_por'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
