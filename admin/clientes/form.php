<?php
/** Alta y edicion de cliente. Sin ?id es alta; con ?id es edicion. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteService;

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

// Tras un error de validacion el handler guarda lo tipeado y vuelve aca.
$valor = static function (string $campo, $default = '') use ($cliente) {
    return old($campo, $cliente[$campo] ?? $default);
};

$pageTitle  = ($esAlta ? 'Nuevo cliente' : 'Editar cliente') . ' · ' . APP_NAME;
$navSeccion = 'clientes';
$bodyClass  = 'con-accion-fija';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/clientes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Clientes
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo"><?= $esAlta ? 'Nuevo cliente' : e($cliente['nombre']) ?></h1>

    <?php if ($esAlta): ?>
        <p class="pantalla__bajada">
            El numero de cliente se genera solo. La clave del portal arranca
            siendo el DNI y el cliente la cambia en su primer ingreso.
        </p>
    <?php else: ?>
        <p class="pantalla__bajada">
            <span class="cifra">N° <?= e($cliente['nro_cliente']) ?></span>
            · alta del <?= e(formatFecha($cliente['fecha_alta'])) ?>
        </p>
    <?php endif; ?>

    <form method="post" id="form-cliente" class="tarjeta p-3 mt-3"
          action="<?= APP_URL ?>/admin/clientes/guardar.php" novalidate>
        <?= csrfField() ?>
        <?php if (!$esAlta): ?>
            <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="dni">DNI</label>
            <input type="text" class="form-control cifra" id="dni" name="dni"
                   value="<?= e($valor('dni')) ?>"
                   inputmode="numeric" pattern="[0-9]*" maxlength="9"
                   autocomplete="off" required <?= $esAlta ? 'autofocus' : '' ?>>
            <div class="form-text">Sin puntos. Es tambien el usuario del portal del cliente.</div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="nombre">Nombre y apellido</label>
            <input type="text" class="form-control" id="nombre" name="nombre"
                   value="<?= e($valor('nombre')) ?>"
                   autocomplete="name" autocapitalize="words" maxlength="120" required>
        </div>

        <div class="mb-3">
            <label class="form-label" for="telefono">Telefono <span class="fw-normal text-secondary">(opcional)</span></label>
            <input type="tel" class="form-control" id="telefono" name="telefono"
                   value="<?= e($valor('telefono')) ?>"
                   inputmode="tel" autocomplete="tel" maxlength="30"
                   placeholder="381 555 1234">
        </div>

        <?php if (!$esAlta): ?>
            <div class="form-check form-switch py-2">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="activo" name="activo" value="1"
                       style="width:3rem;height:1.5rem"
                       <?= (int) $valor('activo', 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label ms-2" for="activo">
                    Cliente activo
                    <span class="d-block form-text mt-0">
                        Si lo desactivas deja de aparecer para cargar jugadas nuevas.
                    </span>
                </label>
            </div>
        <?php endif; ?>
    </form>

    <?php if (!$esAlta): ?>
        <div class="tarjeta p-3 mt-3">
            <span class="rotulo d-block mb-2">Acceso al portal</span>

            <?php if ((int) $cliente['debe_cambiar_clave'] === 1): ?>
                <p class="fila__meta mb-3">
                    Todavia tiene la clave inicial (su DNI) y va a tener que cambiarla al entrar.
                </p>
            <?php else: ?>
                <p class="fila__meta mb-3">
                    Ya cambio su clave.
                    Ultimo ingreso: <?= e(formatFechaHora($cliente['ultimo_acceso'])) ?>.
                </p>
            <?php endif; ?>

            <form method="post" action="<?= APP_URL ?>/admin/clientes/resetear_clave.php"
                  onsubmit="return confirm('Volver la clave del portal al DNI del cliente?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Restablecer clave al DNI
                </button>
            </form>
        </div>

        <?php if (isAdmin()): ?>
            <div class="tarjeta p-3 mt-3" style="border-color:#eec9c7">
                <span class="rotulo d-block mb-2" style="color:var(--rojo)">Zona de riesgo</span>
                <p class="fila__meta mb-3">
                    Si el cliente ya tiene jugadas cargadas no se borra: se desactiva,
                    para no romper el historial del pozo.
                </p>
                <form method="post" action="<?= APP_URL ?>/admin/clientes/eliminar.php"
                      onsubmit="return confirm('Borrar a <?= e(addslashes($cliente['nombre'])) ?>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i> Borrar cliente
                    </button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-cliente" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i>
            <?= $esAlta ? 'Dar de alta' : 'Guardar cambios' ?>
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
