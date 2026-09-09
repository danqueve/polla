<?php
/** Barra superior del panel. Requiere sesion iniciada. */
$_u = currentUser();
$_iniciales = mb_strtoupper(mb_substr(trim($_u['nombre']) !== '' ? $_u['nombre'] : $_u['usuario'], 0, 1));
?>
<header class="topbar">
    <div class="d-flex align-items-center justify-content-between gap-3"
         style="max-width:720px;margin:0 auto;">
        <div class="d-flex flex-column">
            <a class="topbar__marca" href="<?= APP_URL ?>/admin/index.php">Decena</a>
            <span class="topbar__sub">de Oro</span>
        </div>

        <div class="dropdown">
            <a class="topbar__usuario" href="#" role="button"
               data-bs-toggle="dropdown" aria-expanded="false"
               aria-label="Menu de <?= e($_u['nombre']) ?>">
                <?= e($_iniciales) ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li class="px-3 py-2">
                    <div class="fw-semibold"><?= e($_u['nombre']) ?></div>
                    <div class="small text-secondary">
                        <?= e(\Polla\Services\UsuarioService::ROLES[$_u['rol']] ?? $_u['rol']) ?>
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/reportes/index.php">
                        <i class="bi bi-bar-chart-line me-2"></i>Reportes
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/ciclos/index.php">
                        <i class="bi bi-calendar-week me-2"></i>Historial de ciclos
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                    <li>
                        <a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/usuarios/index.php">
                            <i class="bi bi-people me-2"></i>Usuarios del sistema
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= APP_URL ?>/admin/configuracion/index.php">
                            <i class="bi bi-sliders me-2"></i>Configuración
                        </a>
                    </li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item py-2" href="<?= APP_URL ?>/auth/cambiar_clave.php">
                        <i class="bi bi-key me-2"></i>Cambiar mi contrasena
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= APP_URL ?>/auth/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Cerrar sesion
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
