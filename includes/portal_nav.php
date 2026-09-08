<?php
/**
 * Navegacion del portal. Dos destinos y nada mas: la usa gente que
 * entra una vez por semana a ver como le fue.
 */
?>
<nav class="navbar-abajo portal-nav" aria-label="Navegacion del portal">
    <a class="navbar-abajo__item<?= navActivo('mis-jugadas') ?>" href="<?= APP_URL ?>/portal/index.php">
        <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
        <span>Mis jugadas</span>
    </a>
    <a class="navbar-abajo__item<?= navActivo('historial') ?>" href="<?= APP_URL ?>/portal/historial.php">
        <i class="bi bi-clock-history" aria-hidden="true"></i>
        <span>Historial</span>
    </a>
</nav>
