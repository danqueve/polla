<?php
/** Usuarios del panel. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\UsuarioService;

requireAdmin();

$usuarios = (new UsuarioService(getPDO()))->listar();

$pageTitle  = 'Usuarios · ' . APP_NAME;
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
            <h1 class="pantalla__titulo">Usuarios</h1>
            <p class="pantalla__bajada">Quienes pueden entrar al panel de carga</p>
        </div>
        <a href="<?= APP_URL ?>/admin/usuarios/form.php" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-person-plus"></i> Nuevo
        </a>
    </div>

    <?php foreach ($usuarios as $usuario): ?>
        <a class="fila" href="<?= APP_URL ?>/admin/usuarios/form.php?id=<?= (int) $usuario['id'] ?>">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                    <p class="fila__titulo"><?= e($usuario['nombre']) ?></p>
                    <p class="fila__meta">
                        <?= e($usuario['usuario']) ?>
                        · ultimo acceso <?= e(formatFechaHora($usuario['ultimo_acceso'])) ?>
                    </p>
                </div>
                <div class="d-flex flex-column align-items-end gap-1 text-nowrap">
                    <span class="etiqueta <?= $usuario['rol'] === 'admin' ? 'etiqueta--oro' : 'etiqueta--verde' ?>">
                        <?= e(UsuarioService::ROLES[$usuario['rol']]) ?>
                    </span>
                    <?php if ((int) $usuario['activo'] !== 1): ?>
                        <span class="etiqueta etiqueta--gris">Inactivo</span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    <?php endforeach; ?>

    <div class="alert alert-info mt-4 mb-0" role="note">
        <strong>Permisos.</strong> El supervisor puede dar de alta clientes,
        cargar jugadas y ver el ciclo. Borrar clientes o jugadas, administrar
        usuarios y tocar los parametros del juego es solo del administrador.
    </div>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
