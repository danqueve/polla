<?php
/** Vendedores [Fase 11]. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;

requireAdmin();

$vendedores = (new VendedorService(getPDO()))->listar();

$pageTitle        = 'Vendedores · ' . APP_NAME;
$navSeccion       = 'vendedores';
$pageSectionTitle = 'Vendedores';
$breadcrumb       = [
    ['label' => 'Vendedores', 'url' => '']
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
            <h1 class="g-page-title">Vendedores</h1>
            <p class="g-page-subtitle">Quienes captan referidos con su link personal</p>
        </div>
        <a href="<?= APP_URL ?>/admin/vendedores/form.php" class="g-btn g-btn--primary">
            <i class="bi bi-person-plus"></i> Nuevo
        </a>
    </div>

    <!-- Lista de Vendedores -->
    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-person-badge"></i>
                Vendedores (<?= count($vendedores) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$vendedores): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-person-badge fs-1 d-block mb-3 text-muted"></i>
                    Todavía no hay vendedores cargados.
                </div>
            <?php else: ?>
                <?php foreach ($vendedores as $v): ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/vendedores/form.php?id=<?= (int) $v['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title"><?= e($v['nombre']) ?></h3>
                                <div class="g-list-item__meta mt-1">
                                    DNI <?= e($v['dni']) ?>
                                    <?php if ($v['telefono']): ?>
                                        · <?= e($v['telefono']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1 text-nowrap">
                                <span class="fw-bold"><?= e($v['codigo_referido']) ?></span>
                                <?php if ((int) $v['activo'] !== 1): ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <a href="<?= APP_URL ?>/admin/referidos/index.php" class="g-btn g-btn--outline w-100 mt-4">
        <i class="bi bi-diagram-3"></i> Ver todos los referidos
    </a>
</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
