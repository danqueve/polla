<?php
/**
 * Solicitudes de pago con diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SolicitudService;

requireLogin();

$db          = getPDO();
$solicitudes = SolicitudService::crearDesde($db);

$codigo    = trim($_GET['codigo'] ?? '');
$solicitud = $codigo !== '' ? $solicitudes->buscarPorCodigo($codigo) : null;

$pendientes = $solicitudes->listarPendientes();

$pageTitle        = 'Solicitudes de Pago · ' . APP_NAME;
$navSeccion       = 'solicitudes';
$pageSectionTitle = 'Gestión de Cobros y Solicitudes';
$breadcrumb       = [
    ['label' => 'Solicitudes', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Solicitudes de Pago</h1>
            <p class="g-page-subtitle">
                Confirmación de jugadas armadas por clientes desde el portal web.
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-speedometer2 me-1"></i> Ir al Tablero
            </a>
        </div>
    </div>

    <!-- Buscador por Código -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h3 class="g-card__title">
                <i class="bi bi-qr-code-scan me-1"></i>
                Validar Código de Pago
            </h3>
        </div>
        <div class="g-card__body p-3">
            <form method="get">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-8">
                        <input type="text" class="form-control form-control-lg text-uppercase fw-bold text-center letter-spacing-1"
                               id="codigo" name="codigo"
                               value="<?= e($codigo) ?>"
                               maxlength="6" autocapitalize="characters" autocomplete="off"
                               placeholder="EJEMPLO: ABC123" autofocus>
                    </div>
                    <div class="col-12 col-md-4">
                        <button type="submit" class="g-btn g-btn--primary w-100 py-2">
                            <i class="bi bi-search me-1"></i> Buscar Solicitud
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($codigo !== '' && !$solicitud): ?>
        <div class="g-alert-banner g-alert-banner--warning mb-4 g-animate">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                No se encontró ninguna solicitud con el código <strong><?= e(strtoupper($codigo)) ?></strong>. Verifique que esté correctamente tipeado.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($solicitud): ?>
        <!-- Ficha de la solicitud encontrada -->
        <div class="g-card mb-4 g-animate border-primary shadow-lg">
            <div class="g-card__header bg-primary text-white">
                <h3 class="g-card__title text-white">
                    <i class="bi bi-receipt me-1"></i>
                    Solicitud Código: <?= e($solicitud['numero_registro']) ?>
                </h3>
                <div>
                    <?php if ($solicitud['estado'] === 'pendiente'): ?>
                        <span class="g-badge g-badge--warning">Pendiente de Cobro</span>
                    <?php elseif ($solicitud['estado'] === 'confirmada'): ?>
                        <span class="g-badge g-badge--success">Pago Confirmado</span>
                    <?php else: ?>
                        <span class="g-badge g-badge--danger">Rechazada</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="g-card__body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div>
                        <h4 class="fw-bold mb-1"><?= e($solicitud['cliente_nombre']) ?></h4>
                        <div class="text-muted small">
                            Cliente N° <?= e($solicitud['nro_cliente']) ?> · DNI <?= e($solicitud['dni']) ?>
                            <?php if ($solicitud['telefono']): ?>
                                · Tel: <?= e($solicitud['telefono']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Monto Total a Cobrar:</div>
                        <div class="fs-2 fw-extrabold text-primary"><?= e(formatPesos($solicitud['monto_total'])) ?></div>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="fw-semibold small text-muted mb-2">
                        <?= (int) $solicitud['cantidad_jugadas'] ?> <?= (int) $solicitud['cantidad_jugadas'] === 1 ? 'jugada armada' : 'jugadas armadas' ?>
                        (<?= e(CicloService::TIPOS[$solicitud['tipo_juego']] ?? $solicitud['tipo_juego']) ?>):
                    </div>
                    <?php foreach ($solicitud['jugadas'] as $jugada): ?>
                        <div class="bolillas mb-2">
                            <?php foreach ($jugada['numeros'] as $numero): ?>
                                <span class="bolilla"><?= e(num2($numero)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($solicitud['estado'] === 'pendiente'): ?>
                    <div class="d-flex flex-wrap gap-2 mt-4 pt-3 border-top">
                        <form method="post" class="flex-grow-1"
                              action="<?= APP_URL ?>/admin/solicitudes/confirmar.php"
                              onsubmit="return confirm('¿Confirmar el pago de <?= e(addslashes($solicitud['cliente_nombre'])) ?> por <?= e(formatPesos($solicitud['monto_total'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">
                            <button type="submit" class="g-btn g-btn--primary w-100 py-2">
                                <i class="bi bi-check-circle-fill me-1"></i> Confirmar Pago e Ingresar Jugadas
                            </button>
                        </form>

                        <?php if (isAdmin()): ?>
                            <form method="post"
                                  action="<?= APP_URL ?>/admin/solicitudes/rechazar.php"
                                  onsubmit="return confirm('¿Rechazar esta solicitud de pago?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">
                                <button type="submit" class="g-btn g-btn--outline text-danger border-danger py-2">
                                    <i class="bi bi-x-circle me-1"></i> Rechazar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted small mt-2">
                        Estado resuelto el <?= e(formatFechaHora($solicitud['fecha_resolucion'])) ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cola de Solicitudes Pendientes -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-hourglass-split me-1"></i>
                Cola de Solicitudes Pendientes (<?= count($pendientes) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$pendientes): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-check-circle fs-1 d-block mb-3 text-success"></i>
                    <h4 class="fw-bold text-dark">No hay pagos pendientes</h4>
                    <p class="text-muted">Todas las solicitudes enviadas por los clientes han sido procesadas.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendientes as $p): ?>
                    <a class="g-list-item" href="<?= APP_URL ?>/admin/solicitudes/index.php?codigo=<?= e($p['numero_registro']) ?>">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <h3 class="g-list-item__title">
                                    <?= e($p['cliente_nombre']) ?>
                                    <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($p['nro_cliente']) ?></span>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <span class="badge bg-light text-dark border me-1"><?= (int) $p['cantidad_jugadas'] ?> jugadas</span>
                                    · <?= e(CicloService::TIPOS[$p['tipo_juego']] ?? $p['tipo_juego']) ?>
                                    · <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($p['fecha_creacion'])) ?>
                                </div>
                            </div>

                            <div class="text-end">
                                <div class="badge bg-primary fs-6 px-3 py-2 font-monospace"><?= e($p['numero_registro']) ?></div>
                                <div class="fw-bold text-dark mt-1"><?= e(formatPesos($p['monto_total'])) ?></div>
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
