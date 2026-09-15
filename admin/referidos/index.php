<?php
/**
 * Vista global de referidos [Fase 11]: todos los vendedores y
 * supervisores que tienen codigo, cuantos referidos captaron y cuanto
 * acumularon de comision. Exclusivo del administrador -- el supervisor
 * tiene su propia vista, mios.php, acotada a el mismo.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireAdmin();

$referidores = (new ComisionService(getPDO()))->listarTodosLosReferidores();

$pageTitle        = 'Referidos · ' . APP_NAME;
$navSeccion       = 'referidos';
$pageSectionTitle = 'Referidos';
$breadcrumb       = [
    ['label' => 'Referidos', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Referidos</h1>
            <p class="g-page-subtitle">Vendedores y supervisores, quién refirió a quién</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/vendedores/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-person-badge"></i> Vendedores
            </a>
            <a href="<?= APP_URL ?>/admin/liquidaciones/index.php" class="g-btn g-btn--primary">
                <i class="bi bi-cash-coin"></i> Liquidar Comisiones
            </a>
        </div>
    </div>

    <!-- Lista de Referidores -->
    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-diagram-3"></i>
                Referidores (<?= count($referidores) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$referidores): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-diagram-3 fs-1 d-block mb-3 text-muted"></i>
                    Todavía no hay vendedores ni supervisores con código de referido activo.
                </div>
            <?php else: ?>
                <?php foreach ($referidores as $r): ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title"><?= e($r['nombre']) ?></h3>
                                <div class="g-list-item__meta mt-1">
                                    <span class="g-badge <?= $r['tipo'] === 'vendedor' ? 'g-badge--success' : 'g-badge--warning' ?>">
                                        <?= $r['tipo'] === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
                                    </span>
                                    · <?= e($r['codigo_referido']) ?>
                                    · <?= (int) $r['referidos_total'] ?> <?= (int) $r['referidos_total'] === 1 ? 'referido' : 'referidos' ?>
                                    · <?= (int) $r['jugadas_total'] ?> <?= (int) $r['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                                </div>
                            </div>
                            <div class="text-end text-nowrap">
                                <div class="fw-bold fs-6 <?= (float) $r['saldo_pendiente'] > 0 ? 'text-dark' : 'text-secondary' ?>">
                                    <?= e(formatPesos($r['saldo_pendiente'])) ?>
                                </div>
                                <div class="g-list-item__meta">saldo</div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
