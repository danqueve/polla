<?php
/** Alta y edición de cliente con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;
use Polla\Services\VendedorService;

requireLogin();

$clientes = new ClienteService(getPDO());

$id      = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$cliente = $id > 0 ? $clientes->buscarPorId($id) : null;

if ($id > 0 && !$cliente) {
    setFlash('danger', 'Ese cliente no existe.');
    header('Location: ' . APP_URL . '/admin/clientes/index.php');
    exit;
}

$esAlta = $cliente === null;
$vendedorVinculado = $esAlta ? null : (new VendedorService(getPDO()))->buscarPorClienteId($id);

$valor = static function (string $campo, $default = '') use ($cliente) {
    return old($campo, $cliente[$campo] ?? $default);
};

$pageTitle        = ($esAlta ? 'Nuevo Cliente' : 'Editar Cliente') . ' · ' . APP_NAME;
$navSeccion       = 'clientes';
$pageSectionTitle = $esAlta ? 'Nuevo Cliente' : 'Ficha de Cliente';
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Clientes', 'url' => APP_URL . '/admin/clientes/index.php'],
    ['label' => $esAlta ? 'Nuevo' : e($cliente['nombre']), 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue">Clientes</span>
                <?php if (!$esAlta): ?>
                    <span class="g-badge <?= (int) $cliente['activo'] === 1 ? 'g-badge--success' : 'g-badge--danger' ?>">
                        <?= (int) $cliente['activo'] === 1 ? 'Activo' : 'Inactivo' ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="g-page-title mt-2"><?= $esAlta ? 'Nuevo Cliente' : e($cliente['nombre']) ?></h1>
            <p class="g-page-subtitle">
                <?php if ($esAlta): ?>
                    El número de cliente se generará automáticamente. La clave de acceso inicial al portal será su DNI.
                <?php else: ?>
                    Cliente N° <?= e($cliente['nro_cliente']) ?> · Registrado el <?= e(formatFecha($cliente['fecha_alta'])) ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/clientes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Clientes
            </a>
            <?php if (!$esAlta && $vendedorVinculado): ?>
                <a href="<?= APP_URL ?>/admin/vendedores/form.php?id=<?= (int) $vendedorVinculado['id'] ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-person-badge me-1"></i> Perfil Vendedor (<?= e($vendedorVinculado['codigo_referido']) ?>)
                </a>
            <?php elseif (!$esAlta && isAdmin() && (int) $cliente['activo'] === 1 && $cliente['estado'] === 'aprobado'): ?>
                <a href="<?= APP_URL ?>/admin/vendedores/form.php?cliente_id=<?= (int) $cliente['id'] ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-person-badge me-1"></i> Convertir en Vendedor
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna Formulario Principal -->
        <div class="col-12 col-lg-8">
            <form method="post" id="form-cliente"
                  action="<?= APP_URL ?>/admin/clientes/guardar.php" novalidate>
                <?= csrfField() ?>
                <?php if (!$esAlta): ?>
                    <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <?php endif; ?>

                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-person-vcard me-1"></i>
                            Datos Personales
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="dni">DNI / Documento</label>
                            <input type="text" class="form-control form-control-lg" id="dni" name="dni"
                                   value="<?= e($valor('dni')) ?>"
                                   inputmode="numeric" pattern="[0-9]*" maxlength="9"
                                   autocomplete="off" required <?= $esAlta ? 'autofocus' : '' ?>>
                            <div class="form-text">Sin puntos ni espacios. Es también el usuario de ingreso al portal.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="nombre">Nombre y Apellido</label>
                            <input type="text" class="form-control form-control-lg" id="nombre" name="nombre"
                                   value="<?= e($valor('nombre')) ?>"
                                   autocomplete="name" autocapitalize="words" maxlength="120" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="telefono">
                                Teléfono / WhatsApp
                                <?php if (!$esAlta): ?>
                                    <span class="fw-normal text-muted">(opcional)</span>
                                <?php endif; ?>
                            </label>
                            <input type="tel" class="form-control form-control-lg" id="telefono" name="telefono"
                                   value="<?= e($valor('telefono')) ?>"
                                   inputmode="tel" autocomplete="tel" maxlength="30"
                                   placeholder="Ej: 381 555 1234" <?= $esAlta ? 'required' : '' ?>>
                        </div>

                        <?php if (!$esAlta): ?>
                            <div class="form-check form-switch mt-4 p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch"
                                       id="activo" name="activo" value="1"
                                       style="width:2.5rem;height:1.25rem"
                                       <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="activo">
                                    Cliente Activo
                                    <span class="d-block form-text text-muted fw-normal mt-0">
                                        Si se desactiva, dejará de figurar en el selector para nuevas jugadas.
                                    </span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <?php if (!$esAlta): ?>
                <!-- Zona de Gestión y Riesgo -->
                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-shield-lock me-1"></i>
                            Seguridad y Acceso al Portal
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            La clave del cliente es siempre su DNI actual. Último acceso registrado:
                            <strong><?= e(formatFechaHora($cliente['ultimo_acceso'])) ?></strong>.
                        </p>

                        <form method="post" action="<?= APP_URL ?>/admin/clientes/resetear_clave.php"
                              onsubmit="return confirm('¿Resincronizar la clave del portal con el DNI actual del cliente?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                            <button type="submit" class="g-btn g-btn--outline g-btn--sm">
                                <i class="bi bi-arrow-counterclockwise"></i> Resincronizar clave con el DNI
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isAdmin()): ?>
                    <div class="g-card border-danger g-animate g-animate-delay-3">
                        <div class="g-card__header bg-danger-subtle">
                            <h3 class="g-card__title text-danger">
                                <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                Zona de Riesgo
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                Si el cliente ya posee jugadas cargadas históricas, el sistema lo desactivará en vez de borrarlo para preservar la integridad del pozo.
                            </p>
                            <form method="post" action="<?= APP_URL ?>/admin/clientes/eliminar.php"
                                  onsubmit="return confirm('¿Confirmas borrar a <?= e(addslashes($cliente['nombre'])) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                                <button type="submit" class="g-btn g-btn--outline text-danger border-danger g-btn--sm">
                                    <i class="bi bi-trash"></i> Eliminar Cliente
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Columna Lateral Informativa -->
        <div class="col-12 col-lg-4">
            <div class="g-card g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-info-circle me-1"></i>
                        Información Útil
                    </h3>
                </div>
                <div class="g-card__body">
                    <ul class="list-unstyled mb-0 small d-flex flex-column gap-3 text-muted">
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-success fs-5 flex-shrink-0"></i>
                            <div><strong>DNI Único:</strong> Cada cliente se identifica unívocamente por su DNI en el sistema y en el portal.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-phone text-primary fs-5 flex-shrink-0"></i>
                            <div><strong>Acceso Móvil:</strong> El cliente puede ingresar a su portal con su DNI para ver sus jugadas y premios.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-person-badge text-warning fs-5 flex-shrink-0"></i>
                            <div><strong>Vendedores:</strong> Un cliente puede ser promocionado a vendedor manteniendo su cuenta.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-cliente" class="g-btn g-btn--primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Dar de Alta Cliente' : 'Guardar Cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
