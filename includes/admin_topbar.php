<?php
/**
 * Topbar estilo Gentelella para escritorio y móvil.
 * Parámetros:
 *   $pageTitle (string) Título de la página actual
 *   $breadcrumb (array) [['label' => '...', 'url' => '...'], ...] (opcional)
 */
$_u = currentUser();
$_iniciales = mb_strtoupper(mb_substr(trim($_u['nombre']) !== '' ? $_u['nombre'] : $_u['usuario'], 0, 1));
$_rolNombre = \Polla\Services\UsuarioService::ROLES[$_u['rol']] ?? $_u['rol'];
$_solCount = $solicitudesPendientes ?? 0;
?>
<header class="g-topbar">
    <!-- Botón hamburguesa (móvil) -->
    <button class="g-topbar__toggle" id="gSidebarToggle" type="button" aria-label="Abrir menú">
        <i class="bi bi-list"></i>
    </button>

    <!-- Breadcrumb / Título en topbar -->
    <div class="g-topbar__breadcrumb d-none d-sm-flex">
        <a href="<?= APP_URL ?>/admin/index.php">
            <i class="bi bi-house-door me-1"></i> Admin
        </a>
        <?php if (!empty($breadcrumb) && is_array($breadcrumb)): ?>
            <?php foreach ($breadcrumb as $bc): ?>
                <span class="g-topbar__breadcrumb-sep">/</span>
                <?php if (!empty($bc['url'])): ?>
                    <a href="<?= e($bc['url']) ?>"><?= e($bc['label']) ?></a>
                <?php else: ?>
                    <span class="g-topbar__breadcrumb-current"><?= e($bc['label']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="g-topbar__breadcrumb-sep">/</span>
            <span class="g-topbar__breadcrumb-current"><?= e($pageSectionTitle ?? 'Tablero') ?></span>
        <?php endif; ?>
    </div>

    <!-- Acciones derechas -->
    <div class="g-topbar__actions ms-auto">
        <!-- Botón rápido Nueva Jugada -->
        <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-btn g-btn--primary g-btn--sm d-none d-md-inline-flex">
            <i class="bi bi-plus-lg"></i>
            <span>Nueva Jugada</span>
        </a>

        <!-- Notificaciones de Solicitudes -->
        <a href="<?= APP_URL ?>/admin/solicitudes/index.php"
           class="g-topbar__btn"
           title="<?= $_solCount > 0 ? $_solCount . ' solicitudes pendientes' : 'Solicitudes de pago' ?>">
            <i class="bi bi-bell"></i>
            <?php if ($_solCount > 0): ?>
                <span class="g-topbar__dot"></span>
            <?php endif; ?>
        </a>

        <!-- Dropdown Usuario -->
        <div class="dropdown">
            <div class="g-topbar__avatar"
                 role="button"
                 data-bs-toggle="dropdown"
                 aria-expanded="false"
                 title="<?= e($_u['nombre']) ?>">
                <?= e($_iniciales) ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="px-3 py-2">
                    <div class="fw-semibold text-dark"><?= e($_u['nombre']) ?></div>
                    <div class="small text-secondary"><?= e($_rolNombre) ?></div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2" href="<?= APP_URL ?>/auth/cambiar_clave.php">
                        <i class="bi bi-key me-2"></i>Cambiar contraseña
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/configuracion/index.php">
                        <i class="bi bi-gear me-2"></i>Preferencias
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= APP_URL ?>/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
