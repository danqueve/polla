<?php
/**
 * Auditoría: quién cargó qué y cuándo con diseño Gentelella.
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

$eventos = $reporte->auditoria($filtro);

$iconos = [
    'jugada'  => ['bi-ticket-perforated', 'bg-primary-subtle text-primary'],
    'sorteo'  => ['bi-dice-5',            'bg-warning-subtle text-warning'],
    'cliente' => ['bi-person-plus',       'bg-info-subtle text-info'],
];

$pageTitle        = 'Auditoría · Reportes · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Registro de Auditoría';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Auditoría', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Registro de Auditoría</h1>
            <p class="g-page-subtitle">Trazabilidad completa: quién cargó jugadas, extractos y clientes en el sistema</p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <?php
            $accion = APP_URL . '/admin/reportes/auditoria.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <!-- Timeline de Eventos -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-clock-history me-1"></i>
                Línea de Tiempo de Movimientos (<?= count($eventos) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$eventos): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-clock-history fs-1 d-block mb-3 text-muted"></i>
                    No hay movimientos que coincidan con los filtros aplicados.
                </div>
            <?php else: ?>
                <?php foreach ($eventos as $ev): ?>
                    <?php [$icono, $claseColor] = $iconos[$ev['tipo']] ?? ['bi-dot', 'bg-light text-secondary']; ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="g-stat__icon mb-0 flex-shrink-0 <?= e($claseColor) ?>" style="width:40px;height:40px">
                                    <i class="bi <?= e($icono) ?>"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="g-list-item__title">
                                        <?= e($ev['detalle']) ?>
                                    </h3>
                                    <div class="g-list-item__meta mt-1">
                                        <i class="bi bi-person me-1"></i><?= e($ev['quien'] ?? 'Usuario del sistema') ?>
                                        <?php if ($ev['rol']): ?>
                                            · <span class="badge bg-light text-dark border"><?= e(\Polla\Services\UsuarioService::ROLES[$ev['rol']] ?? $ev['rol']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <span class="small text-muted text-nowrap">
                                <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($ev['cuando'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
