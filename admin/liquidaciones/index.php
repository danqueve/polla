<?php
/**
 * Liquidar comisiones [Fase 11]: un referidor por fila, con su saldo
 * pendiente y un boton de liquidar. Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireAdmin();

$referidores = (new ComisionService(getPDO()))->listarTodosLosReferidores();
$conSaldo    = array_filter($referidores, static fn($r) => (float) $r['saldo_pendiente'] > 0);
$sinSaldo    = array_filter($referidores, static fn($r) => (float) $r['saldo_pendiente'] <= 0);

$pageTitle        = 'Liquidaciones · ' . APP_NAME;
$navSeccion       = 'liquidaciones';
$pageSectionTitle = 'Liquidaciones';
$breadcrumb       = [
    ['label' => 'Referidos', 'url' => APP_URL . '/admin/referidos/index.php'],
    ['label' => 'Liquidaciones', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Liquidaciones</h1>
            <p class="g-page-subtitle">Pagá el saldo pendiente de cada vendedor o supervisor</p>
        </div>
        <a href="<?= APP_URL ?>/admin/referidos/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver a Referidos
        </a>
    </div>

    <div class="g-card g-list-card g-animate g-animate-delay-1 mb-4">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-cash-coin"></i>
                Con Saldo Pendiente (<?= count($conSaldo) ?>)
            </h2>
        </div>
        <div class="g-card__body">
            <?php if (!$conSaldo): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-cash-coin fs-1 d-block mb-3 text-muted"></i>
                    No hay saldo pendiente de nadie por ahora.
                </div>
            <?php else: ?>
                <?php foreach ($conSaldo as $r): ?>
                    <div class="g-list-item">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title">
                                    <a href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>"
                                       class="text-decoration-none" style="color:inherit">
                                        <?= e($r['nombre']) ?>
                                    </a>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <?= $r['tipo'] === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
                                    · <?= (int) $r['referidos_total'] ?> <?= (int) $r['referidos_total'] === 1 ? 'referido' : 'referidos' ?>
                                </div>
                            </div>
                            <span class="fw-bold fs-5 text-nowrap"><?= e(formatPesos($r['saldo_pendiente'])) ?></span>
                        </div>
                        <form method="post" action="<?= APP_URL ?>/admin/liquidaciones/guardar.php"
                              onsubmit="return confirm('¿Liquidar <?= e(formatPesos($r['saldo_pendiente'])) ?> a <?= e(addslashes($r['nombre'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="tipo" value="<?= e($r['tipo']) ?>">
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="g-btn g-btn--primary w-100">
                                <i class="bi bi-cash-coin"></i> Liquidar
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($sinSaldo): ?>
        <div class="g-card g-list-card g-animate g-animate-delay-2">
            <div class="g-card__header">
                <h2 class="g-card__title">
                    <i class="bi bi-check2-circle"></i>
                    Sin Saldo Pendiente (<?= count($sinSaldo) ?>)
                </h2>
            </div>
            <div class="g-card__body">
                <?php foreach ($sinSaldo as $r): ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <h3 class="g-list-item__title mb-0"><?= e($r['nombre']) ?></h3>
                            <span class="g-list-item__meta"><?= e(formatPesos(0)) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
