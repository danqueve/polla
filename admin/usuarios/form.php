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

$pageTitle        = ($esAlta ? 'Nuevo usuario' : 'Editar usuario') . ' · ' . APP_NAME;
$navSeccion       = 'usuarios';
$pageSectionTitle = $esAlta ? 'Nuevo Usuario' : 'Editar Usuario';
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Usuarios', 'url' => APP_URL . '/admin/usuarios/index.php'],
    ['label' => $esAlta ? 'Nuevo' : e($usuario['nombre']), 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <span class="g-badge g-badge--blue">Usuarios</span>
            <h1 class="g-page-title mt-2"><?= $esAlta ? 'Nuevo Usuario' : e($usuario['nombre']) ?></h1>
            <p class="g-page-subtitle">Quienes pueden entrar al panel de carga</p>
        </div>
        <a href="<?= APP_URL ?>/admin/usuarios/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver a Usuarios
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <form method="post" id="form-usuario"
                  action="<?= APP_URL ?>/admin/usuarios/guardar.php" novalidate>
                <?= csrfField() ?>
                <?php if (!$esAlta): ?>
                    <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                <?php endif; ?>

                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-person-vcard me-1"></i>
                            Datos del Usuario
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <?php if ($esAlta): ?>
                            <div class="mb-3">
                                <label class="form-label fw-semibold small text-muted" for="usuario">Nombre de acceso</label>
                                <input type="text" class="form-control form-control-lg" id="usuario" name="usuario"
                                       value="<?= e($valor('usuario')) ?>"
                                       autocapitalize="none" autocorrect="off" spellcheck="false"
                                       autocomplete="off" maxlength="50" required autofocus>
                                <div class="form-text">Minusculas, sin espacios. No se puede cambiar despues.</div>
                            </div>
                        <?php else: ?>
                            <div class="mb-3">
                                <span class="form-label fw-semibold small text-muted d-block mb-1">Nombre de acceso</span>
                                <div class="fs-5"><?= e($usuario['usuario']) ?></div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="nombre">Nombre y apellido</label>
                            <input type="text" class="form-control form-control-lg" id="nombre" name="nombre"
                                   value="<?= e($valor('nombre')) ?>"
                                   autocapitalize="words" maxlength="120" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="rol">Rol</label>
                            <select class="form-select form-select-lg" id="rol" name="rol" required>
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

                        <div class="form-check form-switch mt-4 p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                            <input class="form-check-input ms-0 me-2" type="checkbox" role="switch"
                                   id="activo" name="activo" value="1"
                                   style="width:2.5rem;height:1.25rem"
                                   <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="activo">Puede iniciar sesión</label>
                        </div>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-shield-lock me-1"></i>
                            Contraseña
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="password">
                                <?= $esAlta ? 'Contraseña' : 'Contraseña nueva (dejar vacío para no cambiarla)' ?>
                            </label>
                            <input type="password" class="form-control form-control-lg" id="password" name="password"
                                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
                            <div class="form-text">Mínimo 8 caracteres.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold small text-muted" for="password2">Repetir contraseña</label>
                            <input type="password" class="form-control form-control-lg" id="password2" name="password2"
                                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!$esAlta && (int) $usuario['id'] !== currentUserId()): ?>
                <div class="g-card border-danger g-animate g-animate-delay-3">
                    <div class="g-card__header bg-danger-subtle">
                        <h3 class="g-card__title text-danger">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                            Zona de Riesgo
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Si el usuario ya cargó clientes, jugadas o sorteos, se desactiva
                            en lugar de borrarse para no perder el rastro de quién cargó qué.
                        </p>
                        <form method="post" action="<?= APP_URL ?>/admin/usuarios/eliminar.php"
                              onsubmit="return confirm('¿Borrar el usuario <?= e(addslashes($usuario['usuario'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                            <button type="submit" class="g-btn g-btn--outline text-danger border-danger g-btn--sm">
                                <i class="bi bi-trash"></i> Borrar Usuario
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-4">
            <div class="g-card g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-info-circle me-1"></i>
                        Información Útil
                    </h3>
                </div>
                <div class="g-card__body">
                    <ul class="list-unstyled mb-0 small d-flex flex-column gap-3 text-muted">
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-shield-check text-success fs-5 flex-shrink-0"></i>
                            <div><strong>Administrador:</strong> Único rol que puede borrar clientes/jugadas, administrar usuarios y tocar la configuración.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-person-check text-primary fs-5 flex-shrink-0"></i>
                            <div><strong>Supervisor:</strong> Puede dar de alta clientes, cargar jugadas y ver el ciclo en curso.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-usuario" class="g-btn g-btn--primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Crear Usuario' : 'Guardar Cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
