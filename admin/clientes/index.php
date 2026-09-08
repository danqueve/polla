<?php
/** Listado y busqueda de clientes. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;

requireLogin();

$busqueda = trim($_GET['q'] ?? '');
$clientes = (new ClienteService(getPDO()))->listar($busqueda);

$pageTitle  = 'Clientes · ' . APP_NAME;
$navSeccion = 'clientes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pantalla__titulo">Clientes</h1>
            <p class="pantalla__bajada"><?= count($clientes) ?> en la lista</p>
        </div>
        <a href="<?= APP_URL ?>/admin/clientes/form.php" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-person-plus"></i> Nuevo
        </a>
    </div>

    <form method="get" class="mb-3" role="search">
        <div class="position-relative">
            <input type="search" class="form-control" name="q"
                   value="<?= e($busqueda) ?>"
                   placeholder="Buscar por nombre, DNI o N° de cliente"
                   aria-label="Buscar cliente"
                   style="padding-right:3rem">
            <button type="submit" class="btn btn-link position-absolute end-0 top-0 h-100 px-3"
                    aria-label="Buscar">
                <i class="bi bi-search"></i>
            </button>
        </div>
    </form>

    <?php if (!$clientes): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-people" aria-hidden="true"></i>
            <?php if ($busqueda !== ''): ?>
                No hay clientes que coincidan con &laquo;<?= e($busqueda) ?>&raquo;.
                <div class="mt-3">
                    <a href="<?= APP_URL ?>/admin/clientes/index.php" class="btn btn-sm btn-outline-secondary">
                        Ver todos
                    </a>
                </div>
            <?php else: ?>
                Todavia no hay clientes cargados.
                <div class="mt-3">
                    <a href="<?= APP_URL ?>/admin/clientes/form.php" class="btn btn-sm btn-primary">
                        Dar de alta el primero
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($clientes as $cliente): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/clientes/form.php?id=<?= (int) $cliente['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($cliente['nombre']) ?></p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($cliente['nro_cliente']) ?></span>
                            · DNI <?= e($cliente['dni']) ?>
                            <?php if ($cliente['telefono']): ?>
                                · <?= e($cliente['telefono']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="fila__meta">
                            <?= (int) $cliente['jugadas_total'] ?>
                            <?= (int) $cliente['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                        </p>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 text-nowrap">
                        <?php if ((int) $cliente['activo'] !== 1): ?>
                            <span class="etiqueta etiqueta--gris">Inactivo</span>
                        <?php elseif ((int) $cliente['debe_cambiar_clave'] === 1): ?>
                            <span class="etiqueta etiqueta--oro" title="Todavia no entro al portal">
                                Clave = DNI
                            </span>
                        <?php endif; ?>
                        <i class="bi bi-chevron-right text-secondary" aria-hidden="true"></i>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
