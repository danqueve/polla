<?php
/** Alta y edicion de una promocion. Sin ?id es alta; con ?id es edicion. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\PromocionService;

requireAdmin();

$promociones = new PromocionService(getPDO());

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

$pageTitle  = ($esAlta ? 'Nueva promoción' : 'Editar promoción') . ' · ' . APP_NAME;
$navSeccion = '';
$bodyClass  = 'con-accion-fija';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/promociones/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Promociones
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo"><?= $esAlta ? 'Nueva promoción' : 'Editar promoción' ?></h1>
    <p class="pantalla__bajada">
        Cada jugada del paquete se carga con el precio total dividido la
        cantidad. El pozo sigue sumando el 60% de ese importe, jugada por
        jugada, como siempre.
    </p>

    <form method="post" id="form-promocion" class="tarjeta p-3 mt-3"
          action="<?= APP_URL ?>/admin/promociones/guardar.php" novalidate>
        <?= csrfField() ?>
        <?php if (!$esAlta): ?>
            <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="cantidad_jugadas">Cantidad de jugadas del paquete</label>
            <input type="number" class="form-control cifra" id="cantidad_jugadas" name="cantidad_jugadas"
                   value="<?= e($valor('cantidad_jugadas')) ?>"
                   inputmode="numeric" min="2" max="20" step="1"
                   required <?= $esAlta ? 'autofocus' : '' ?>>
            <div class="form-text">Entre 2 y 20. Solo puede haber una promoción activa por cada cantidad.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="precio_total">Precio total del paquete</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control cifra" id="precio_total" name="precio_total"
                       value="<?= e($valor('precio_total')) ?>"
                       inputmode="decimal" min="1" step="1" required>
            </div>
        </div>

        <?php if (!$esAlta): ?>
            <div class="form-check form-switch py-2">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="activa" name="activa" value="1"
                       style="width:3rem;height:1.5rem"
                       <?= (int) $valor('activa', 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label ms-2" for="activa">
                    Promoción activa
                    <span class="d-block form-text mt-0">
                        Si la desactivás deja de sugerirse al cargar jugadas.
                    </span>
                </label>
            </div>
        <?php endif; ?>
    </form>
</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-promocion" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Crear promoción' : 'Guardar cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
