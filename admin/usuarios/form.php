<?php
/** Alta y edicion de usuarios del panel. Solo admin. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\UsuarioService;

requireAdmin();

$servicio = new UsuarioService(getPDO());

$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$usuario = $id > 0 ? $servicio->buscarPorId($id) : null;

if ($id > 0 && !$usuario) {
    setFlash('danger', 'Ese usuario no existe.');
    header('Location: ' . APP_URL . '/admin/usuarios/index.php');
    exit;
}

$esAlta = $usuario === null;

$valor = static function (string $campo, $default = '') use ($usuario) {
    return old($campo, $usuario[$campo] ?? $default);
};

$pageTitle  = ($esAlta ? 'Nuevo usuario' : 'Editar usuario') . ' · ' . APP_NAME;
$navSeccion = '';
$bodyClass  = 'con-accion-fija';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/usuarios/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Usuarios
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo"><?= $esAlta ? 'Nuevo usuario' : e($usuario['nombre']) ?></h1>

    <form method="post" id="form-usuario" class="tarjeta p-3 mt-3"
          action="<?= APP_URL ?>/admin/usuarios/guardar.php" novalidate>
        <?= csrfField() ?>
        <?php if (!$esAlta): ?>
            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
        <?php endif; ?>

        <?php if ($esAlta): ?>
            <div class="mb-3">
                <label class="form-label" for="usuario">Nombre de acceso</label>
                <input type="text" class="form-control" id="usuario" name="usuario"
                       value="<?= e($valor('usuario')) ?>"
                       autocapitalize="none" autocorrect="off" spellcheck="false"
                       autocomplete="off" maxlength="50" required autofocus>
                <div class="form-text">Minusculas, sin espacios. No se puede cambiar despues.</div>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <span class="form-label d-block mb-1">Nombre de acceso</span>
                <div class="cifra fs-5"><?= e($usuario['usuario']) ?></div>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="nombre">Nombre y apellido</label>
            <input type="text" class="form-control" id="nombre" name="nombre"
                   value="<?= e($valor('nombre')) ?>"
                   autocapitalize="words" maxlength="120" required>
        </div>

        <div class="mb-3">
            <label class="form-label" for="rol">Rol</label>
            <select class="form-select" id="rol" name="rol" required>
                <?php foreach (UsuarioService::ROLES as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>"
                            <?= $valor('rol', 'supervisor') === $clave ? 'selected' : '' ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text">
                El supervisor carga clientes y jugadas, pero no borra ni administra usuarios.
            </div>
        </div>

        <div class="form-check form-switch py-2 mb-3">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="activo" name="activo" value="1"
                   style="width:3rem;height:1.5rem"
                   <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label ms-2" for="activo">Puede iniciar sesion</label>
        </div>

        <hr>

        <div class="mb-3">
            <label class="form-label" for="password">
                <?= $esAlta ? 'Contrasena' : 'Contrasena nueva (dejar vacio para no cambiarla)' ?>
            </label>
            <input type="password" class="form-control" id="password" name="password"
                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
            <div class="form-text">Minimo 8 caracteres.</div>
        </div>

        <div class="mb-0">
            <label class="form-label" for="password2">Repetir contrasena</label>
            <input type="password" class="form-control" id="password2" name="password2"
                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
        </div>
    </form>

    <?php if (!$esAlta && (int) $usuario['id'] !== currentUserId()): ?>
        <div class="tarjeta p-3 mt-3" style="border-color:#eec9c7">
            <span class="rotulo d-block mb-2" style="color:var(--rojo)">Zona de riesgo</span>
            <p class="fila__meta mb-3">
                Si el usuario ya cargo clientes, jugadas o sorteos, se desactiva
                en lugar de borrarse para no perder el rastro de quien cargo que.
            </p>
            <form method="post" action="<?= APP_URL ?>/admin/usuarios/eliminar.php"
                  onsubmit="return confirm('Borrar el usuario <?= e(addslashes($usuario['usuario'])) ?>?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-trash"></i> Borrar usuario
                </button>
            </form>
        </div>
    <?php endif; ?>
</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-usuario" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Crear usuario' : 'Guardar cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
