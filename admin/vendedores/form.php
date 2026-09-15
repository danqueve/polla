<?php
/** Alta y edicion de vendedores [Fase 11]. Sin ?id es alta; con ?id es edicion. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;
use Polla\Services\VendedorService;

requireAdmin();

$servicio = new VendedorService(getPDO());
$clienteVinculado = null;

$id       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$vendedor = $id > 0 ? $servicio->buscarPorId($id) : null;

if ($id > 0 && !$vendedor) {
    setFlash('danger', 'Ese vendedor no existe.');
    header('Location: ' . APP_URL . '/admin/vendedores/index.php');
    exit;
}

$esAlta = $vendedor === null;

if (!$esAlta && $vendedor['cliente_id'] !== null) {
    $clienteVinculado = (new ClienteService(getPDO()))->buscarPorId((int) $vendedor['cliente_id']);
}

// Alta iniciada desde "Convertir en vendedor" en la ficha del cliente.
// old() cubre el rebote por error de validacion sin perder el contexto.
$clienteOrigen = null;
if ($esAlta) {
    $clienteOrigenId = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : (int) old('cliente_id_origen', 0);
    if ($clienteOrigenId > 0) {
        $clienteOrigen = (new ClienteService(getPDO()))->buscarPorId($clienteOrigenId);
        if (!$clienteOrigen) {
            setFlash('danger', 'Ese cliente no existe.');
            header('Location: ' . APP_URL . '/admin/clientes/index.php');
            exit;
        }
    }
}

$valor = static function (string $campo, $default = '') use ($vendedor, $clienteOrigen) {
    return old($campo, $vendedor[$campo] ?? $clienteOrigen[$campo] ?? $default);
};

$tituloPagina = $esAlta
    ? ($clienteOrigen ? 'Convertir en Vendedor' : 'Nuevo Vendedor')
    : 'Editar Vendedor';

$pageTitle        = $tituloPagina . ' · ' . APP_NAME;
$navSeccion       = 'vendedores';
$pageSectionTitle = $tituloPagina;
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Vendedores', 'url' => APP_URL . '/admin/vendedores/index.php'],
    ['label' => $esAlta ? 'Nuevo' : e($vendedor['nombre']), 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <span class="g-badge g-badge--blue">Vendedores</span>
            <h1 class="g-page-title mt-2">
                <?= $esAlta ? ($clienteOrigen ? 'Convertir en Vendedor' : 'Nuevo Vendedor') : e($vendedor['nombre']) ?>
            </h1>
            <?php if ($clienteOrigen): ?>
                <p class="g-page-subtitle">
                    Convirtiendo a <?= e($clienteOrigen['nombre']) ?>
                    (cliente N° <?= e($clienteOrigen['nro_cliente']) ?>) en vendedor.
                    Sigue jugando igual que antes. Solo falta la contraseña del panel de vendedor.
                </p>
            <?php elseif (!$esAlta): ?>
                <p class="g-page-subtitle">
                    Código <strong><?= e($vendedor['codigo_referido']) ?></strong>
                    · alta del <?= e(formatFecha($vendedor['fecha_alta'])) ?>
                    <?php if ($clienteVinculado): ?>
                        · también juega como cliente
                        <a href="<?= APP_URL ?>/admin/clientes/form.php?id=<?= (int) $clienteVinculado['id'] ?>">
                            N° <?= e($clienteVinculado['nro_cliente']) ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <a href="<?= APP_URL ?>/admin/vendedores/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver a Vendedores
        </a>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <form method="post" id="form-vendedor"
                  action="<?= APP_URL ?>/admin/vendedores/guardar.php" novalidate>
                <?= csrfField() ?>
                <?php if (!$esAlta): ?>
                    <input type="hidden" name="id" value="<?= (int) $vendedor['id'] ?>">
                <?php endif; ?>
                <?php if ($clienteOrigen): ?>
                    <input type="hidden" name="cliente_id_origen" value="<?= (int) $clienteOrigen['id'] ?>">
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
                            <label class="form-label fw-semibold small text-muted" for="nombre">Nombre y apellido</label>
                            <input type="text" class="form-control form-control-lg" id="nombre" name="nombre"
                                   value="<?= e($valor('nombre')) ?>"
                                   autocomplete="name" autocapitalize="words" maxlength="120" required autofocus>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="dni">DNI</label>
                            <input type="text" class="form-control form-control-lg" id="dni" name="dni"
                                   value="<?= e($valor('dni')) ?>"
                                   inputmode="numeric" pattern="[0-9]*" maxlength="9"
                                   autocomplete="off" required <?= $clienteOrigen ? 'readonly' : '' ?>>
                            <div class="form-text">
                                <?= $clienteOrigen
                                    ? 'Tiene que coincidir con el DNI del cliente para vincularse correctamente.'
                                    : 'Sin puntos. Es también el usuario para entrar a su panel.' ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="telefono">Teléfono</label>
                            <input type="tel" class="form-control form-control-lg" id="telefono" name="telefono"
                                   value="<?= e($valor('telefono')) ?>"
                                   inputmode="tel" autocomplete="tel" maxlength="30"
                                   placeholder="381 555 1234" required>
                        </div>

                        <?php if (!$esAlta): ?>
                            <div class="form-check form-switch mt-4 p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                                <input class="form-check-input ms-0 me-2" type="checkbox" role="switch"
                                       id="activo" name="activo" value="1"
                                       style="width:2.5rem;height:1.25rem"
                                       <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
                                <label class="form-check-label fw-semibold" for="activo">Puede iniciar sesión</label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-shield-lock me-1"></i>
                            Contraseña
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="password">
                                <?= $esAlta ? 'Contraseña' : 'Contraseña nueva (dejar vacío para no cambiarla)' ?>
                            </label>
                            <input type="password" class="form-control form-control-lg" id="password" name="password"
                                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
                            <div class="form-text">Mínimo 8 caracteres.</div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label fw-semibold small text-muted" for="password2">Repetir contraseña</label>
                            <input type="password" class="form-control form-control-lg" id="password2" name="password2"
                                   autocomplete="new-password" minlength="8" <?= $esAlta ? 'required' : '' ?>>
                        </div>
                    </div>
                </div>
            </form>

            <?php if (!$esAlta): ?>
                <div class="g-card border-danger g-animate g-animate-delay-3">
                    <div class="g-card__header bg-danger-subtle">
                        <h3 class="g-card__title text-danger">
                            <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                            Zona de Riesgo
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Si ya tiene referidos, no se borra: se desactiva, para no
                            perder el rastro de quién refirió a quién.
                        </p>
                        <form method="post" action="<?= APP_URL ?>/admin/vendedores/eliminar.php"
                              onsubmit="return confirm('¿Borrar el vendedor <?= e(addslashes($vendedor['nombre'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $vendedor['id'] ?>">
                            <button type="submit" class="g-btn g-btn--outline text-danger border-danger g-btn--sm">
                                <i class="bi bi-trash"></i> Borrar Vendedor
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

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
                            <i class="bi bi-link-45deg text-primary fs-5 flex-shrink-0"></i>
                            <div><strong>Código de referido:</strong> Se genera automáticamente al crear el vendedor y arma su link personal de registro.</div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-cash-coin text-success fs-5 flex-shrink-0"></i>
                            <div><strong>Comisiones:</strong> Se acreditan sobre cada jugada confirmada de sus referidos y se liquidan desde el módulo de Liquidaciones.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-vendedor" class="g-btn g-btn--primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? ($clienteOrigen ? 'Convertir en Vendedor' : 'Crear Vendedor') : 'Guardar Cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
