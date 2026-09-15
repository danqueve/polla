<?php
/** Usuarios del panel. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\UsuarioService;

requireAdmin();

$usuarios = (new UsuarioService(getPDO()))->listar();

$pageTitle        = 'Usuarios · ' . APP_NAME;
$navSeccion       = 'usuarios';
$pageSectionTitle = 'Usuarios del Panel';
$breadcrumb       = [
    ['label' => 'Usuarios', 'url' => '']
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
            <h1 class="g-page-title">Usuarios</h1>
            <p class="g-page-subtitle">Quienes pueden entrar al panel de carga</p>
        </div>
        <a href="<?= APP_URL ?>/admin/usuarios/form.php" class="g-btn g-btn--primary">
            <i class="bi bi-person-plus"></i> Nuevo
        </a>
    </div>

    <!-- Lista de Usuarios -->
    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-people"></i>
                Usuarios (<?= count($usuarios) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php foreach ($usuarios as $usuario): ?>
                <a class="g-list-item" href="<?= APP_URL ?>/admin/usuarios/form.php?id=<?= (int) $usuario['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <h3 class="g-list-item__title"><?= e($usuario['nombre']) ?></h3>
                            <div class="g-list-item__meta mt-1">
                                <?= e($usuario['usuario']) ?>
                                · último acceso <?= e(formatFechaHora($usuario['ultimo_acceso'])) ?>
                            </div>
                        </div>
                        <div class="d-flex flex-column align-items-end gap-1 text-nowrap">
                            <span class="g-badge <?= $usuario['rol'] === 'admin' ? 'g-badge--warning' : 'g-badge--success' ?>">
                                <?= e(UsuarioService::ROLES[$usuario['rol']]) ?>
                            </span>
                            <?php if ((int) $usuario['activo'] !== 1): ?>
                                <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Inactivo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0" role="note">
        <strong>Permisos.</strong> El supervisor puede dar de alta clientes,
        cargar jugadas y ver el ciclo. Borrar clientes o jugadas, administrar
        usuarios y tocar los parametros del juego es solo del administrador.
    </div>
</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
