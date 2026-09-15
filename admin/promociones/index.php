<?php
/** Paquetes promocionales. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\PromocionService;

requireAdmin();

$promociones = new PromocionService(getPDO());
$lista       = $promociones->listar();

$pageTitle        = 'Promociones · ' . APP_NAME;
$navSeccion       = 'configuracion';
$pageSectionTitle = 'Promociones';
$breadcrumb       = [
    ['label' => 'Configuración', 'url' => APP_URL . '/admin/configuracion/index.php'],
    ['label' => 'Promociones', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Promociones</h1>
            <p class="g-page-subtitle">Paquetes de jugadas con descuento</p>
        </div>
        <a href="<?= APP_URL ?>/admin/promociones/form.php" class="g-btn g-btn--primary">
            <i class="bi bi-plus-lg"></i> Nueva
        </a>
    </div>

    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-box-seam"></i>
                Promociones (<?= count($lista) ?>)
            </h2>
        </div>
        <div class="g-card__body">
            <?php if (!$lista): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-box-seam fs-1 d-block mb-3 text-muted"></i>
                    <p class="mb-3">Todavía no hay promociones cargadas.</p>
                    <a href="<?= APP_URL ?>/admin/promociones/form.php" class="g-btn g-btn--primary g-btn--sm">
                        Crear la primera
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($lista as $promo): ?>
                    <?php
                    $porJugada = (float) $promo['precio_total'] / max(1, (int) $promo['cantidad_jugadas']);
                    ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/promociones/form.php?id=<?= (int) $promo['id'] ?>">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title">
                                    <?= (int) $promo['cantidad_jugadas'] ?> jugadas por
                                    <?= e(formatPesos($promo['precio_total'])) ?>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <?= e(formatPesos($porJugada)) ?> por jugada
                                    · creada <?= e(formatFecha($promo['fecha_creacion'])) ?>
                                </div>
                            </div>
                            <div class="text-end text-nowrap">
                                <?php if ((int) $promo['activa'] === 1): ?>
                                    <span class="g-badge g-badge--success">Activa</span>
                                <?php else: ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Inactiva</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0" role="note">
        <strong>Cómo se aplican.</strong> Cuando la cantidad de jugadas que
        se está cargando (por el staff o por el propio cliente) coincide
        con una promoción activa, el sistema la sugiere — quien carga
        decide si la aplica o cobra precio de lista. Como mucho puede
        haber una promoción activa por cada cantidad de jugadas.
    </div>
</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
