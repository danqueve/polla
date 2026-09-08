<?php
/**
 * Navegacion principal, anclada abajo para que caiga bajo el pulgar.
 * Cinco destinos es el maximo que entra comodo en un celular angosto;
 * lo que no esta aca (ciclos, usuarios) cuelga del tablero y del menu
 * de la barra de arriba.
 * En >=768px el CSS la reubica arriba del contenido.
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
    <a class="navbar-abajo__item<?= navActivo('clientes') ?>" href="<?= APP_URL ?>/admin/clientes/index.php">
        <i class="bi bi-people" aria-hidden="true"></i>
        <span>Clientes</span>
    </a>
</nav>
