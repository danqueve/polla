<?php
/** Alta y edicion de una promocion. Sin ?id es alta; con ?id es edicion. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ParametroService;
use Polla\Services\PromocionService;

requireAdmin();

$db          = getPDO();
$promociones = new PromocionService($db);
$porcentajePozo = (new ParametroService($db))->porcentajePozo();

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$promo = $id > 0 ? $promociones->buscarPorId($id) : null;

if ($id > 0 && !$promo) {
    setFlash('danger', 'Esa promoción no existe.');
    header('Location: ' . APP_URL . '/admin/promociones/index.php');
    exit;
}

$esAlta = $promo === null;

$valor = static function (string $campo, $default = '') use ($promo) {
    return old($campo, $promo[$campo] ?? $default);
};

$pageTitle        = ($esAlta ? 'Nueva promoción' : 'Editar promoción') . ' · ' . APP_NAME;
$navSeccion       = 'configuracion';
$pageSectionTitle = $esAlta ? 'Nueva Promoción' : 'Editar Promoción';
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Configuración', 'url' => APP_URL . '/admin/configuracion/index.php'],
    ['label' => 'Promociones', 'url' => APP_URL . '/admin/promociones/index.php'],
    ['label' => $esAlta ? 'Nueva' : 'Editar', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <span class="g-badge g-badge--blue">Promociones</span>
            <h1 class="g-page-title mt-2"><?= $esAlta ? 'Nueva Promoción' : 'Editar Promoción' ?></h1>
            <p class="g-page-subtitle">
                Cada jugada del paquete se carga con el precio total dividido la
                cantidad. El pozo sigue sumando el <?= $porcentajePozo ?>% de ese importe,
                jugada por jugada, como siempre.
            </p>
        </div>
        <a href="<?= APP_URL ?>/admin/promociones/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver a Promociones
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <form method="post" id="form-promocion"
                  action="<?= APP_URL ?>/admin/promociones/guardar.php" novalidate>
                <?= csrfField() ?>
                <?php if (!$esAlta): ?>
                    <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                <?php endif; ?>

                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-box-seam me-1"></i>
                            Datos del Paquete
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="cantidad_jugadas">Cantidad de jugadas del paquete</label>
                            <input type="number" class="form-control form-control-lg" id="cantidad_jugadas" name="cantidad_jugadas"
                                   value="<?= e($valor('cantidad_jugadas')) ?>"
                                   inputmode="numeric" min="2" max="20" step="1"
                                   required <?= $esAlta ? 'autofocus' : '' ?>>
                            <div class="form-text">Entre 2 y 20. Solo puede haber una promoción activa por cada cantidad.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="precio_total">Precio total del paquete</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-lg" id="precio_total" name="precio_total"
                                       value="<?= e($valor('precio_total')) ?>"
                                       inputmode="decimal" min="1" step="1" required>
                            </div>
                        </div>

                        <?php if (!$esAlta): ?>
                            <div class="form-check form-switch mt-4 p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch"
                                       id="activa" name="activa" value="1"
                                       style="width:2.5rem;height:1.25rem"
                                       <?= (int) $valor('activa', 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="activa">
                                    Promoción activa
                                    <span class="d-block form-text text-muted fw-normal mt-0">
                                        Si la desactivás deja de sugerirse al cargar jugadas.
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-promocion" class="g-btn g-btn--primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Crear Promoción' : 'Guardar Cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
