<?php
/**
 * Cola de aprobación de autorregistros con diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteRegistroService;

requireLogin();

$solicitudes = ClienteRegistroService::crearDesde(getPDO())->listarPendientes();

$pageTitle        = 'Solicitudes de Clientes · ' . APP_NAME;
$navSeccion       = 'clientes';
$pageSectionTitle = 'Autorregistros Pendientes';
$breadcrumb       = [
    ['label' => 'Clientes', 'url' => APP_URL . '/admin/clientes/index.php'],
    ['label' => 'Autorregistros', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Solicitudes de Autorregistro</h1>
            <p class="g-page-subtitle">
                Cuentas registradas desde el formulario público que esperan revisión y habilitación.
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/clientes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Clientes
            </a>
        </div>
    </div>

    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-inbox me-1"></i>
                Solicitudes Pendientes (<?= count($solicitudes) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$solicitudes): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                    <h4 class="fw-bold text-dark">Al día</h4>
                    <p class="text-muted">No hay solicitudes de autorregistro pendientes de revisión.</p>
                </div>
            <?php else: ?>
                <?php foreach ($solicitudes as $s): ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <div class="g-stat__icon g-stat__icon--orange mb-0 flex-shrink-0" style="width:42px;height:42px">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <div>
                                    <h3 class="g-list-item__title">
                                        <?= e($s['nombre']) ?>
                                        <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($s['nro_cliente']) ?></span>
                                    </h3>
                                    <div class="g-list-item__meta mt-1">
                                        DNI <?= e($s['dni']) ?>
                                        <?php if ($s['telefono']): ?>
                                            · <i class="bi bi-telephone me-1"></i><?= e($s['telefono']) ?>
                                        <?php endif; ?>
                                        · <i class="bi bi-clock me-1"></i>Registrado el <?= e(formatFechaHora($s['fecha_alta'])) ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <form method="post" action="<?= APP_URL ?>/admin/clientes/solicitud_aprobar.php"
                                      onsubmit="return confirm('¿Aprobar la solicitud de <?= e(addslashes($s['nombre'])) ?>?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                    <button type="submit" class="g-btn g-btn--primary g-btn--sm">
                                        <i class="bi bi-check-lg"></i> Aprobar
                                    </button>
                                </form>

                                <?php if (isAdmin()): ?>
                                    <form method="post" action="<?= APP_URL ?>/admin/clientes/solicitud_rechazar.php"
                                          onsubmit="return confirm('¿Rechazar la solicitud de <?= e(addslashes($s['nombre'])) ?>?')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                        <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0">
                                            <i class="bi bi-x-lg"></i> Rechazar
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
