<?php
/** Listado y búsqueda de clientes con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteRegistroService;
use Polla\Services\ClienteService;

requireLogin();

$busqueda   = trim($_GET['q'] ?? '');
$clientes   = (new ClienteService(getPDO()))->listar($busqueda);
$pendientes = ClienteRegistroService::crearDesde(getPDO())->contarPendientes();

$pageTitle        = 'Clientes · ' . APP_NAME;
$navSeccion       = 'clientes';
$pageSectionTitle = 'Directorio de Clientes';
$breadcrumb       = [
    ['label' => 'Clientes', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Directorio de Clientes</h1>
            <p class="g-page-subtitle"><?= count($clientes) ?> clientes registrados en el sistema</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/clientes/form.php" class="g-btn g-btn--primary">
                <i class="bi bi-person-plus"></i> Nuevo Cliente
            </a>
            <?php if ($pendientes > 0): ?>
                <a href="<?= APP_URL ?>/admin/clientes/solicitudes.php" class="g-btn g-btn--outline">
                    <i class="bi bi-inbox me-1"></i> Autorregistros (<?= $pendientes ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($pendientes > 0): ?>
        <a href="<?= APP_URL ?>/admin/clientes/solicitudes.php"
           class="g-alert-banner g-alert-banner--warning mb-4 g-animate g-animate-delay-1">
            <i class="bi bi-inbox"></i>
            <div>
                Hay <strong><?= $pendientes ?> <?= $pendientes === 1 ? 'solicitud pendiente' : 'solicitudes pendientes' ?></strong> de autorregistro de clientes para revisar y aprobar.
            </div>
            <i class="bi bi-chevron-right g-alert-banner__arrow"></i>
        </a>
    <?php endif; ?>

    <!-- Buscador -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <form method="get" role="search">
                <div class="input-group">
                    <input type="search" class="form-control form-control-lg" name="q"
                           value="<?= e($busqueda) ?>"
                           placeholder="Buscar cliente por nombre, DNI o N° asignado...">
                    <button type="submit" class="g-btn g-btn--primary">
                        <i class="bi bi-search me-1"></i> Buscar
                    </button>
                    <?php if ($busqueda !== ''): ?>
                        <a href="<?= APP_URL ?>/admin/clientes/index.php" class="g-btn g-btn--outline">
                            Limpiar
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Clientes -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-people"></i>
                Clientes (<?= count($clientes) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$clientes): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-people fs-1 d-block mb-3 text-muted"></i>
                    <?php if ($busqueda !== ''): ?>
                        <p class="mb-3">No hay clientes que coincidan con &laquo;<?= e($busqueda) ?>&raquo;.</p>
                        <a href="<?= APP_URL ?>/admin/clientes/index.php" class="g-btn g-btn--outline g-btn--sm">
                            Ver todos los clientes
                        </a>
                    <?php else: ?>
                        <p class="mb-3">Todavía no hay clientes cargados en la plataforma.</p>
                        <a href="<?= APP_URL ?>/admin/clientes/form.php" class="g-btn g-btn--primary g-btn--sm">
                            <i class="bi bi-plus-lg"></i> Dar de alta el primero
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($clientes as $cliente): ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/clientes/form.php?id=<?= (int) $cliente['id'] ?>">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="g-stat__icon g-stat__icon--blue mb-0 flex-shrink-0" style="width:40px;height:40px">
                                    <i class="bi bi-person"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="g-list-item__title">
                                        <?= e($cliente['nombre']) ?>
                                        <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($cliente['nro_cliente']) ?></span>
                                    </h3>
                                    <div class="g-list-item__meta mt-1">
                                        DNI <?= e($cliente['dni']) ?>
                                        <?php if ($cliente['telefono']): ?>
                                            · <i class="bi bi-telephone me-1"></i><?= e($cliente['telefono']) ?>
                                        <?php endif; ?>
                                        · <span class="badge bg-light text-dark border"><?= (int) $cliente['jugadas_total'] ?> jugadas</span>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if ((int) $cliente['activo'] !== 1): ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Inactivo</span>
                                <?php else: ?>
                                    <span class="g-badge g-badge--success">Activo</span>
                                <?php endif; ?>
                                <i class="bi bi-chevron-right text-muted ms-2" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
