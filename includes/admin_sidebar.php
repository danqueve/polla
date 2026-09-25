<?php
/**
 * Sidebar de navegación estilo Gentelella para escritorio y drawer en móvil.
 * Parámetros:
 *   $navSeccion (string) Sección activa actual
 */
$_u = currentUser();
$_iniciales = mb_strtoupper(mb_substr(trim($_u['nombre']) !== '' ? $_u['nombre'] : $_u['usuario'], 0, 1));
$_rolNombre = \Polla\Services\UsuarioService::ROLES[$_u['rol']] ?? $_u['rol'];
$_solCount = $solicitudesPendientes ?? 0;
?>
<aside class="g-sidebar" id="gSidebar">
    <!-- Brand -->
    <a href="<?= APP_URL ?>/admin/index.php" class="g-sidebar__brand">
        <div class="g-sidebar__brand-icon">
            <i class="bi bi-trophy-fill"></i>
        </div>
        <div class="g-sidebar__brand-text">
            <span class="g-sidebar__brand-name">Decena de Oro</span>
            <span class="g-sidebar__brand-sub">Panel de Control</span>
        </div>
    </a>

    <!-- Navegación -->
    <nav class="g-sidebar__nav">
        <!-- Grupo Principal -->
        <div class="g-sidebar__group">
            <div class="g-sidebar__group-label">Principal</div>
            <a href="<?= APP_URL ?>/admin/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'tablero' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Tablero</span>
            </a>
            <a href="<?= APP_URL ?>/admin/jugadas/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'jugadas' ? 'active' : '' ?>">
                <i class="bi bi-ticket-perforated"></i>
                <span>Jugadas</span>
            </a>
            <a href="<?= APP_URL ?>/admin/sorteos/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'sorteos' ? 'active' : '' ?>">
                <i class="bi bi-dice-5"></i>
                <span>Sorteos</span>
            </a>
            <a href="<?= APP_URL ?>/admin/clientes/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'clientes' ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i>
                <span>Clientes</span>
            </a>
        </div>

        <!-- Grupo Operaciones -->
        <div class="g-sidebar__group">
            <div class="g-sidebar__group-label">Operaciones</div>
            <a href="<?= APP_URL ?>/admin/solicitudes/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'solicitudes' ? 'active' : '' ?>">
                <i class="bi bi-qr-code-scan"></i>
                <span>Solicitudes</span>
                <?php if ($_solCount > 0): ?>
                    <span class="g-sidebar__badge g-sidebar__badge--warning"><?= $_solCount ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= APP_URL ?>/admin/reportes/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'reportes' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reportes</span>
            </a>
            <a href="<?= APP_URL ?>/admin/ranking/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'ranking' ? 'active' : '' ?>">
                <i class="bi bi-trophy"></i>
                <span>Ranking</span>
            </a>
            <a href="<?= APP_URL ?>/admin/ciclos/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'ciclos' ? 'active' : '' ?>">
                <i class="bi bi-calendar-week"></i>
                <span>Ciclos</span>
            </a>
            <a href="<?= APP_URL ?>/admin/sabados/index.php"
               class="g-sidebar__link <?= ($navSeccion ?? '') === 'sabados' ? 'active' : '' ?>">
                <i class="bi bi-star"></i>
                <span>Sábados</span>
            </a>
        </div>

        <!-- Grupo Administración -->
        <div class="g-sidebar__group">
            <div class="g-sidebar__group-label">Sistema</div>
            <?php if (isAdmin()): ?>
                <a href="<?= APP_URL ?>/admin/usuarios/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'usuarios' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    <span>Usuarios</span>
                </a>
                <a href="<?= APP_URL ?>/admin/vendedores/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'vendedores' ? 'active' : '' ?>">
                    <i class="bi bi-person-badge"></i>
                    <span>Vendedores</span>
                </a>
                <a href="<?= APP_URL ?>/admin/referidos/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'referidos' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Referidos</span>
                </a>
                <a href="<?= APP_URL ?>/admin/liquidaciones/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'liquidaciones' ? 'active' : '' ?>">
                    <i class="bi bi-cash-coin"></i>
                    <span>Liquidaciones</span>
                </a>
                <a href="<?= APP_URL ?>/admin/configuracion/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'configuracion' ? 'active' : '' ?>">
                    <i class="bi bi-sliders"></i>
                    <span>Configuración</span>
                </a>
                <a href="<?= APP_URL ?>/admin/feriados/index.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'feriados' ? 'active' : '' ?>">
                    <i class="bi bi-calendar-x"></i>
                    <span>Feriados</span>
                </a>
            <?php else: ?>
                <a href="<?= APP_URL ?>/admin/referidos/mios.php"
                   class="g-sidebar__link <?= ($navSeccion ?? '') === 'referidos' ? 'active' : '' ?>">
                    <i class="bi bi-diagram-3"></i>
                    <span>Mis Referidos</span>
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- Footer Sidebar con perfil -->
    <div class="g-sidebar__footer">
        <div class="g-sidebar__user">
            <div class="g-sidebar__avatar"><?= e($_iniciales) ?></div>
            <div class="g-sidebar__user-info">
                <div class="g-sidebar__user-name"><?= e($_u['nombre']) ?></div>
                <div class="g-sidebar__user-role"><?= e($_rolNombre) ?></div>
            </div>
            <a href="<?= APP_URL ?>/auth/logout.php"
               class="text-secondary ms-auto p-1"
               title="Cerrar sesión"
               style="text-decoration:none;font-size:1.1rem">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>
