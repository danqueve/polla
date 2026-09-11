<?php
/** Encabezado del portal: a quien saluda y por donde sale. */
use Polla\Services\VendedorService;

$_cli = clienteActual();
// Solo el nombre de pila: "Hola Ramon" es mas humano que el nombre completo.
$_pila = explode(' ', trim($_cli['nombre']))[0];
$_venLink = (new VendedorService(getPDO()))->buscarPorClienteId((int) $_cli['id']);
?>
<header class="cabecera-cliente">
    <div class="cabecera-cliente__interior d-flex align-items-start justify-content-between gap-3">
        <div class="min-w-0">
            <p class="cabecera-cliente__saludo">Hola, <?= e($_pila) ?></p>
            <span class="cabecera-cliente__nro">
                Cliente N° <?= e($_cli['nro_cliente']) ?> · Decena de Oro
            </span>
        </div>

        <div class="dropdown flex-shrink-0">
            <button class="topbar__usuario border-0" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false"
                    aria-label="Menu de tu cuenta">
                <i class="bi bi-three-dots-vertical"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <?php if ($_venLink && (int) $_venLink['activo'] === 1): ?>
                    <li>
                        <a class="dropdown-item py-2" href="<?= APP_URL ?>/portal/panel_vendedor.php">
                            <i class="bi bi-people me-2"></i>Mi panel de vendedor
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="<?= APP_URL ?>/portal/logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Salir
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
