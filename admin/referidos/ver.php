<?php
/** Detalle de un referidor puntual (vendedor o supervisor) [Fase 11]. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;
use Polla\Services\LiquidacionService;

requireAdmin();

$tipo = $_GET['tipo'] ?? '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($tipo, ['vendedor', 'supervisor'], true) || $id <= 0) {
    setFlash('danger', 'Referidor inválido.');
    header('Location: ' . APP_URL . '/admin/referidos/index.php');
    exit;
}

$comisiones = new ComisionService(getPDO());
$nombre     = $comisiones->nombreDe($tipo, $id);

if (!$nombre) {
    setFlash('danger', 'Ese ' . $tipo . ' no existe.');
    header('Location: ' . APP_URL . '/admin/referidos/index.php');
    exit;
}

$referidos     = $comisiones->listarReferidos($tipo, $id);
$saldo         = $comisiones->saldoPendiente($tipo, $id);
$acreditado    = $comisiones->totalAcreditado($tipo, $id);
$liquidaciones = LiquidacionService::crearDesde(getPDO())->historial($tipo, $id, 20);

$pageTitle        = e($nombre) . ' · ' . APP_NAME;
$navSeccion       = 'referidos';
$pageSectionTitle = $nombre;
$breadcrumb       = [
    ['label' => 'Referidos', 'url' => APP_URL . '/admin/referidos/index.php'],
    ['label' => $nombre, 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <span class="g-badge <?= $tipo === 'vendedor' ? 'g-badge--success' : 'g-badge--warning' ?>">
                <?= $tipo === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
            </span>
            <h1 class="g-page-title mt-2"><?= e($nombre) ?></h1>
        </div>
        <a href="<?= APP_URL ?>/admin/referidos/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver a Referidos
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">

            <!-- Hero Card Saldo -->
            <div class="g-hero g-animate g-animate-delay-1">
                <div class="g-hero__label">
                    <i class="bi bi-wallet2 me-1"></i>
                    Saldo Pendiente
                </div>
                <div class="g-hero__amount">
                    <?= e(formatPesos($saldo)) ?>
                </div>
                <div class="g-hero__detail">
                    <i class="bi bi-info-circle me-1"></i>
                    <?= e(formatPesos($acreditado)) ?> acreditados en total desde siempre
                </div>
            </div>

            <?php if ($saldo > 0): ?>
                <form method="post" action="<?= APP_URL ?>/admin/liquidaciones/guardar.php" class="mb-4 g-animate g-animate-delay-1"
                      onsubmit="return confirm('¿Liquidar <?= e(formatPesos($saldo)) ?> a <?= e(addslashes($nombre)) ?>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <input type="hidden" name="volver_a" value="ver">
                    <button type="submit" class="g-btn g-btn--primary w-100">
                        <i class="bi bi-cash-coin"></i> Liquidar <?= e(formatPesos($saldo)) ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Métricas Stats -->
            <div class="g-stat-grid g-animate g-animate-delay-2">
                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--blue">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="g-stat__value"><?= count($referidos) ?></div>
                    <div class="g-stat__label">Referidos</div>
                </div>

                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--purple">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="g-stat__value">
                        <?= array_sum(array_column($referidos, 'jugadas_total')) ?>
                    </div>
                    <div class="g-stat__label">Jugadas Generadas</div>
                </div>
            </div>

            <!-- Lista de Referidos -->
            <div class="g-card g-list-card g-animate g-animate-delay-3 mb-4">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-people"></i>
                        Sus Referidos
                    </h2>
                </div>
                <div class="g-card__body">
                    <?php if (!$referidos): ?>
                        <div class="p-5 text-center text-secondary">
                            <i class="bi bi-people fs-1 d-block mb-3 text-muted"></i>
                            Todavía no tiene referidos.
                        </div>
                    <?php else: ?>
                        <?php foreach ($referidos as $r): ?>
                            <div class="g-list-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <h3 class="g-list-item__title"><?= e($r['nombre']) ?></h3>
                                        <div class="g-list-item__meta mt-1">
                                            N° <?= e($r['nro_cliente']) ?> · desde <?= e(formatFecha($r['fecha_alta'])) ?>
                                        </div>
                                    </div>
                                    <span class="g-badge <?= (int) $r['jugadas_total'] > 0 ? 'g-badge--success' : '' ?> text-nowrap" <?= (int) $r['jugadas_total'] > 0 ? '' : 'style="background:#e5e7eb;color:#4b5563"' ?>>
                                        <?= (int) $r['jugadas_total'] ?> <?= (int) $r['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($liquidaciones): ?>
                <div class="g-card g-list-card g-animate g-animate-delay-3">
                    <div class="g-card__header">
                        <h2 class="g-card__title">
                            <i class="bi bi-clock-history"></i>
                            Liquidaciones Anteriores
                        </h2>
                    </div>
                    <div class="g-card__body">
                        <?php foreach ($liquidaciones as $l): ?>
                            <div class="g-list-item">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="min-w-0">
                                        <h3 class="g-list-item__title"><?= e(formatFechaHora($l['fecha'])) ?></h3>
                                        <?php if ($l['liquidado_por_nombre']): ?>
                                            <div class="g-list-item__meta mt-1">por <?= e($l['liquidado_por_nombre']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="fw-bold text-nowrap"><?= e(formatPesos($l['monto'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
