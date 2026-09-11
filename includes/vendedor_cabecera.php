<?php
/** Encabezado del panel del vendedor: a quien saluda y por donde sale. */
$_ven  = vendedorActual();
$_pila = explode(' ', trim($_ven['nombre']))[0];
?>
<header class="cabecera-cliente">
    <div class="cabecera-cliente__interior d-flex align-items-start justify-content-between gap-3">
        <div class="min-w-0">
            <p class="cabecera-cliente__saludo">Hola, <?= e($_pila) ?></p>
            <span class="cabecera-cliente__nro">
                Vendedor · Decena de Oro
            </span>
        </div>

        <div class="dropdown flex-shrink-0">
            <button class="topbar__usuario border-0" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="Menu de tu cuenta">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li>
                    <a class="dropdown-item py-2<?= navActivo('vendedor-inicio') ? ' active' : '' ?>" href="<?= APP_URL ?>/vendedor/index.php">
                        <i class="bi bi-house me-2"></i>Mi panel
                    </a>
                </li>
                <li>
                    <a class="dropdown-item py-2<?= navActivo('vendedor-historial') ? ' active' : '' ?>" href="<?= APP_URL ?>/vendedor/historial.php">
                        <i class="bi bi-clock-history me-2"></i>Historial de pagos
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= APP_URL ?>/vendedor/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
