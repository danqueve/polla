<?php
/** Alta y edicion de vendedores [Fase 11]. Sin ?id es alta; con ?id es edicion. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;

requireAdmin();

$servicio = new VendedorService(getPDO());

$id       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$vendedor = $id > 0 ? $servicio->buscarPorId($id) : null;

if ($id > 0 && !$vendedor) {
    setFlash('danger', 'Ese vendedor no existe.');
    header('Location: ' . APP_URL . '/admin/vendedores/index.php');
    exit;
}

$esAlta = $vendedor === null;

$valor = static function (string $campo, $default = '') use ($vendedor) {
    return old($campo, $vendedor[$campo] ?? $default);
};

$pageTitle  = ($esAlta ? 'Nuevo vendedor' : 'Editar vendedor') . ' · ' . APP_NAME;
$navSeccion = '';
$bodyClass  = 'con-accion-fija';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/vendedores/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Vendedores
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo"><?= $esAlta ? 'Nuevo vendedor' : e($vendedor['nombre']) ?></h1>

    <?php if (!$esAlta): ?>
        <p class="pantalla__bajada">
            Código <span class="cifra"><?= e($vendedor['codigo_referido']) ?></span>
            · alta del <?= e(formatFecha($vendedor['fecha_alta'])) ?>
        </p>
    <?php endif; ?>

    <form method="post" id="form-vendedor" class="tarjeta p-3 mt-3"
          action="<?= APP_URL ?>/admin/vendedores/guardar.php" novalidate>
        <?= csrfField() ?>
        <?php if (!$esAlta): ?>
            <input type="hidden" name="id" value="<?= (int) $vendedor['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="nombre">Nombre y apellido</label>
            <input type="text" class="form-control" id="nombre" name="nombre"
                   value="<?= e($valor('nombre')) ?>"
                   autocomplete="name" autocapitalize="words" maxlength="120" required autofocus>
        </div>

        <div class="mb-3">
            <label class="form-label" for="dni">DNI</label>
            <input type="text" class="form-control cifra" id="dni" name="dni"
                   value="<?= e($valor('dni')) ?>"
                   inputmode="numeric" pattern="[0-9]*" maxlength="9"
                   autocomplete="off" required>
            <div class="form-text">Sin puntos. Es también el usuario para entrar a su panel.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="telefono">Teléfono</label>
            <input type="tel" class="form-control" id="telefono" name="telefono"
                   value="<?= e($valor('telefono')) ?>"
                   inputmode="tel" autocomplete="tel" maxlength="30"
                   placeholder="381 555 1234" required>
        </div>

        <?php if (!$esAlta): ?>
            <div class="form-check form-switch py-2 mb-3">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="activo" name="activo" value="1"
                       style="width:3rem;height:1.5rem"
                       <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label ms-2" for="activo">Puede iniciar sesión</label>
            </div>
        <?php endif; ?>

        <hr>

        <div class="mb-3">
            <label class="form-label" for="password">
                <?= $esAlta ? 'Contraseña' : 'Contraseña nueva (dejar vacío para no cambiarla)' ?>
            </label>
            <input type="password" class="form-control" id="password" name="password"
                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
            <div class="form-text">Mínimo 8 caracteres.</div>
        </div>

        <div class="mb-0">
            <label class="form-label" for="password2">Repetir contraseña</label>
            <input type="password" class="form-control" id="password2" name="password2"
                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
        </div>
    </form>

    <?php if (!$esAlta): ?>
        <div class="tarjeta p-3 mt-3" style="border-color:#eec9c7">
            <span class="rotulo d-block mb-2" style="color:var(--rojo)">Zona de riesgo</span>
            <p class="fila__meta mb-3">
                Si ya tiene referidos, no se borra: se desactiva, para no
                perder el rastro de quién refirió a quién.
            </p>
            <form method="post" action="<?= APP_URL ?>/admin/vendedores/eliminar.php"
                  onsubmit="return confirm('¿Borrar el vendedor <?= e(addslashes($vendedor['nombre'])) ?>?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $vendedor['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i> Borrar vendedor
                </button>
            </form>
        </div>
    <?php endif; ?>
</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-vendedor" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Crear vendedor' : 'Guardar cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
