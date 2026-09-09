<?php
/**
 * Navegacion del portal. Tres destinos: la usa gente que entra sobre
 * todo a ver como le fue, y ahora tambien a armar una jugada nueva.
 */
?>
<nav class="navbar-abajo portal-nav" aria-label="Navegacion del portal">
    <a class="navbar-abajo__item<?= navActivo('mis-jugadas') ?>" href="<?= APP_URL ?>/portal/index.php">
        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
        <span>Mis jugadas</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('jugar') ?>" href="<?= APP_URL ?>/portal/jugar.php">
        <i class="bi bi-plus-circle-fill" aria-hidden="true"></i>
        <span>Jugar</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('historial') ?>" href="<?= APP_URL ?>/portal/historial.php">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <span>Historial</span>
    </a>
</nav>
