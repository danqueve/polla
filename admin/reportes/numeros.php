<?php
/**
 * Estadísticas de números con mapa de calor y diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireLogin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::total((int) $usuario['id']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$stats = $reporte->estadisticasNumeros($filtro);

$claseCalor = static function (float $porcentaje): string {
    if ($porcentaje <= 0)  return '';
    if ($porcentaje <= 15) return 'tablero__celda--n1';
    if ($porcentaje <= 35) return 'tablero__celda--n2';
    return 'tablero__celda--n3';
};

$maxApariciones = $stats['numeros'] ? max(array_column($stats['numeros'], 'apariciones')) : 0;

$pageTitle        = 'Estadísticas de Números · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Frecuencia de Números';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Estadísticas Números', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Estadísticas de Números</h1>
            <p class="g-page-subtitle">Frecuencia, mapa de calor, números calientes, fríos y atrasos</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <?php if ($stats['total_sorteos'] > 0): ?>
                <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'numeros'])) ?>"
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
            $ocultarFiltrosPersonales = true;
            $accion = APP_URL . '/admin/reportes/numeros.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <?php if ($stats['total_sorteos'] === 0): ?>

        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-dice-5 fs-1 d-block mb-3 text-muted"></i>
            <h4 class="fw-bold text-dark">Sin datos disponibles</h4>
            <p class="text-muted">No hay sorteos cargados que coincidan con este filtro.</p>
        </div>

    <?php else: ?>

        <!-- Stat Grid KPIs -->
        <div class="g-stat-grid g-animate g-animate-delay-1 mb-4">
            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--blue">
                    <i class="bi bi-dice-5"></i>
                </div>
                <div class="g-stat__value"><?= $stats['total_sorteos'] ?></div>
                <div class="g-stat__label">Sorteos Analizados</div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--red">
                    <i class="bi bi-fire"></i>
                </div>
                <div class="g-stat__value text-danger"><?= num2($stats['calientes'][0]['numero']) ?></div>
                <div class="g-stat__label">Más Caliente (<?= $stats['calientes'][0]['apariciones'] ?> veces)</div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--blue">
                    <i class="bi bi-snow"></i>
                </div>
                <div class="g-stat__value text-primary"><?= num2($stats['frios'][0]['numero']) ?></div>
                <div class="g-stat__label">Más Frío (<?= $stats['frios'][0]['apariciones'] ?> veces)</div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--orange">
                    <i class="bi bi-hourglass-bottom"></i>
                </div>
                <?php if ($stats['atrasados']): ?>
                    <div class="g-stat__value text-warning"><?= num2($stats['atrasados'][0]['numero']) ?></div>
                    <div class="g-stat__label">Más Atrasado (<?= $stats['atrasados'][0]['atraso'] ?> sorteos)</div>
                <?php else: ?>
                    <div class="g-stat__value text-warning"><?= count($stats['nunca']) ?></div>
                    <div class="g-stat__label">Nunca salieron</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mapa de Calor (00 a 99) -->
        <div class="g-card mb-4 g-animate g-animate-delay-2">
            <div class="g-card__header">
                <h2 class="g-card__title">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i>
                    Mapa de Frecuencia Visual (00 - 99)
                </h2>
            </div>
            <div class="g-card__body">
                <div class="tablero mb-3" role="img"
                     aria-label="Mapa de frecuencia de los números 00 a 99">
                    <?php foreach ($stats['numeros'] as $f): ?>
                        <?php
                        $clases = trim($claseCalor($f['porcentaje']) . ($f['apariciones'] === $maxApariciones && $maxApariciones > 0 ? ' tablero__celda--record' : ''));
                        ?>
                        <div class="tablero__celda <?= e($clases) ?>"
                             title="<?= e(num2($f['numero'])) ?>: <?= $f['apariciones'] ?> <?= $f['apariciones'] === 1 ? 'vez' : 'veces' ?> (<?= number_format($f['porcentaje'], 1) ?>%)<?= $f['atraso'] === null ? ' · nunca en este período' : ' · ' . $f['atraso'] . ' sorteos sin salir' ?>">
                            <?= num2($f['numero']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex flex-wrap gap-3 small text-muted pt-2 border-top">
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#eceae3"></span> Nunca</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:var(--g-primary-light)"></span> Frío (≤15%)</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:var(--g-primary)"></span> Frecuente (15%-35%)</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:var(--g-primary-dark)"></span> Muy Caliente (&gt;35%)</span>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Top 10 Calientes -->
            <div class="col-12 col-md-6">
                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title text-danger">
                            <i class="bi bi-fire me-1"></i> Top 10 Números Más Salidores
                        </h3>
                    </div>
                    <div class="g-card__body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($stats['calientes'] as $f): ?>
                                <?php $ancho = $maxApariciones > 0 ? max(2.0, $f['apariciones'] / $maxApariciones * 100) : 0; ?>
                                <li class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="bolilla bolilla--acertada"><?= num2($f['numero']) ?></span>
                                        <span class="fw-bold fs-6"><?= $f['apariciones'] ?> veces (<?= number_format($f['porcentaje'], 1) ?>%)</span>
                                    </div>
                                    <div class="progress" style="height:6px">
                                        <div class="progress-bar bg-danger" style="width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Top 10 Fríos -->
            <div class="col-12 col-md-6">
                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title text-primary">
                            <i class="bi bi-snow me-1"></i> Top 10 Números Menos Salidores
                        </h3>
                    </div>
                    <div class="g-card__body p-0">
                        <ul class="list-group list-group-flush">
                            <?php foreach ($stats['frios'] as $f): ?>
                                <?php $ancho = $maxApariciones > 0 ? max(2.0, $f['apariciones'] / $maxApariciones * 100) : 0; ?>
                                <li class="list-group-item p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="bolilla"><?= num2($f['numero']) ?></span>
                                        <span class="fw-bold fs-6"><?= $f['apariciones'] ?> veces (<?= number_format($f['porcentaje'], 1) ?>%)</span>
                                    </div>
                                    <div class="progress" style="height:6px">
                                        <div class="progress-bar bg-primary" style="width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
