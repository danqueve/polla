<?php
/** Vendedores [Fase 11]. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;

requireAdmin();

$vendedores = (new VendedorService(getPDO()))->listar();

$pageTitle  = 'Vendedores · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pantalla__titulo">Vendedores</h1>
            <p class="pantalla__bajada">Quienes captan referidos con su link personal</p>
        </div>
        <a href="<?= APP_URL ?>/admin/vendedores/form.php" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-person-plus"></i> Nuevo
        </a>
    </div>

    <?php if (!$vendedores): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-person-badge" aria-hidden="true"></i>
            Todavía no hay vendedores cargados.
        </div>
    <?php else: ?>
        <?php foreach ($vendedores as $v): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/vendedores/form.php?id=<?= (int) $v['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($v['nombre']) ?></p>
                        <p class="fila__meta">
                            DNI <?= e($v['dni']) ?>
                            <?php if ($v['telefono']): ?>
                                · <?= e($v['telefono']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 text-nowrap">
                        <span class="cifra"><?= e($v['codigo_referido']) ?></span>
                        <?php if ((int) $v['activo'] !== 1): ?>
                            <span class="etiqueta etiqueta--gris">Inactivo</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="<?= APP_URL ?>/admin/referidos/index.php" class="btn btn-outline-secondary w-100 mt-4">
        <i class="bi bi-diagram-3"></i> Ver todos los referidos
    </a>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
