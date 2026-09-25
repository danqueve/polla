<?php
/**
 * Navegacion principal, anclada abajo para que caiga bajo el pulgar.
 *
 * Cuatro destinos directos (Tablero, Jugadas, Cargar, Sorteos) + "Más",
 * que abre una hoja inferior con el resto de las secciones (Clientes
 * incluido, que antes ocupaba el 5° lugar fijo) — el panel ya tiene mas
 * de diez secciones y no entran comodas en una sola barra. El dropdown
 * de la topbar sigue existiendo tal cual, sin tocar: esto es un acceso
 * adicional, mas a mano del pulgar, no un reemplazo.
 *
 * En >=768px el CSS reubica la barra arriba del contenido.
 */
?>
<nav class="navbar-abajo" aria-label="Navegacion principal">
    <a class="navbar-abajo__item<?= navActivo('tablero') ?>" href="<?= APP_URL ?>/admin/index.php">
        <i class="bi bi-grid-1x2" aria-hidden="true"></i>
        <span>Tablero</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('jugadas') ?>" href="<?= APP_URL ?>/admin/jugadas/index.php">
        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
        <span>Jugadas</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('nueva') ?>" href="<?= APP_URL ?>/admin/jugadas/nueva.php">
        <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>
        <span>Cargar</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('sorteos') ?>" href="<?= APP_URL ?>/admin/sorteos/index.php">
        <i class="bi bi-dice-5" aria-hidden="true"></i>
        <span>Sorteos</span>
    </a>
    <button type="button" class="navbar-abajo__item navbar-abajo__item--boton"
            data-bs-toggle="offcanvas" data-bs-target="#menuMas" aria-controls="menuMas">
        <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
        <span>Más</span>
    </button>
</nav>

<div class="offcanvas offcanvas-bottom menu-mas" tabindex="-1" id="menuMas" aria-labelledby="menuMasLabel">
    <div class="offcanvas-header menu-mas__header">
        <h2 class="offcanvas-title menu-mas__titulo" id="menuMasLabel">Más secciones</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body menu-mas__body">

        <div class="menu-mas__grupo">
            <div class="menu-mas__rotulo">Jugadas</div>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/clientes/index.php">
                <i class="bi bi-people" aria-hidden="true"></i>
                <span>Clientes</span>
            </a>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/solicitudes/index.php">
                <i class="bi bi-qr-code-scan" aria-hidden="true"></i>
                <span>Solicitudes de pago</span>
            </a>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/ciclos/index.php">
                <i class="bi bi-calendar-week" aria-hidden="true"></i>
                <span>Historial de ciclos</span>
            </a>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/sabados/index.php">
                <i class="bi bi-star" aria-hidden="true"></i>
                <span>Sábados</span>
            </a>
        </div>

        <div class="menu-mas__grupo">
            <div class="menu-mas__rotulo">Equipo</div>
            <?php if (isAdmin()): ?>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/usuarios/index.php">
                    <i class="bi bi-person-gear" aria-hidden="true"></i>
                    <span>Usuarios del sistema</span>
                </a>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/vendedores/index.php">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    <span>Vendedores</span>
                </a>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/referidos/index.php">
                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                    <span>Referidos</span>
                </a>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/liquidaciones/index.php">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                    <span>Liquidaciones</span>
                </a>
            <?php else: ?>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/referidos/mios.php">
                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                    <span>Mis referidos</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="menu-mas__grupo">
            <div class="menu-mas__rotulo">Negocio</div>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/reportes/index.php">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                <span>Reportes</span>
            </a>
            <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/ranking/index.php">
                <i class="bi bi-trophy" aria-hidden="true"></i>
                <span>Ranking</span>
            </a>
            <?php if (isAdmin()): ?>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/configuracion/index.php">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <span>Configuración</span>
                </a>
                <a class="menu-mas__fila" href="<?= APP_URL ?>/admin/feriados/index.php">
                    <i class="bi bi-calendar-x" aria-hidden="true"></i>
                    <span>Feriados</span>
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>
