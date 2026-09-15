<?php
/** Historial de ganadores con diseño Gentelella. */
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

$ganadores = $reporte->ganadores($filtro);

$totalPagado = 0.0;
foreach ($ganadores as $g) {
    $totalPagado += (float) $g['monto_premio'];
}

$pageTitle        = 'Ganadores · Reportes · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Historial de Ganadores';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Ganadores', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Historial de Ganadores</h1>
            <p class="g-page-subtitle">Premios mayores entregados y pozos liquidados</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <?php if ($ganadores): ?>
                <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'ganadores'])) ?>"
                   class="g-btn g-btn--primary">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stat Tiles -->
    <div class="g-stat-grid g-animate g-animate-delay-1 mb-4">
        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--orange">
                <i class="bi bi-trophy-fill"></i>
            </div>
            <div class="g-stat__value"><?= count($ganadores) ?></div>
            <div class="g-stat__label">Total Premios Pagados</div>
        </div>

        <div class="g-stat g-stat--accent">
            <div class="g-stat__icon">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="g-stat__value"><?= e(formatPesos($totalPagado)) ?></div>
            <div class="g-stat__label">Monto Total Liquidado</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <?php
            $accion = APP_URL . '/admin/reportes/ganadores.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <!-- Lista de Ganadores -->
    <div class="g-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-trophy-fill text-warning me-1"></i>
                Premios y Ganadores (<?= count($ganadores) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$ganadores): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-trophy fs-1 d-block mb-3 text-muted"></i>
                    No hay registros de ganadores en el período filtrado.
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($ganadores as $g): ?>
                        <div class="p-3 rounded border" style="background:#fffdfa;border-color:#fde68a !important">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                <div>
                                    <h3 class="fw-bold fs-5 text-dark mb-1">
                                        <i class="bi bi-trophy-fill text-warning me-1"></i>
                                        <?= e($g['cliente']) ?>
                                        <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($g['nro_cliente']) ?></span>
                                    </h3>
                                    <div class="text-muted small">
                                        DNI <?= e($g['dni']) ?>
                                        <?php if ($g['telefono']): ?>
                                            · Tel: <?= e($g['telefono']) ?>
                                        <?php endif; ?>
                                        · Ciclo <?= (int) $g['ciclo'] ?>
                                        · Sorteo <?= e(formatFecha($g['sorteo'])) ?>
                                        · Cargado por <?= e($g['cargado_por'] ?? '—') ?>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <div class="small text-muted">Premio Otorgado:</div>
                                    <div class="fs-3 fw-extrabold text-success"><?= e(formatPesos($g['monto_premio'])) ?></div>
                                </div>
                            </div>

                            <div class="bolillas mt-3">
                                <?php foreach ($g['numeros'] as $numero): ?>
                                    <span class="bolilla bolilla--acertada"><?= e(num2($numero)) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
