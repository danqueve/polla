<?php
/** Encabezado del panel del vendedor: a quien saluda y por donde sale. */
use Polla\Services\VendedorService;

$_ven      = vendedorActual();
$_pila     = explode(' ', trim($_ven['nombre']))[0];
$_venDatos = (new VendedorService(getPDO()))->buscarPorId((int) $_ven['id']);
$_puedeJugar = $_venDatos && $_venDatos['cliente_id'] !== null;
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
                <?php if ($_puedeJugar): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2" href="<?= APP_URL ?>/vendedor/jugar.php">
                            <i class="bi bi-dice-5 me-2"></i>Jugar
                        </a>
                    </li>
                <?php endif; ?>
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
