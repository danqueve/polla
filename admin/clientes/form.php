<?php
/** Alta y edicion de cliente. Sin ?id es alta; con ?id es edicion. */
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
            El numero de cliente se genera solo. La clave del portal es
            siempre el DNI, fija.
        </p>
    <?php else: ?>
        <p class="pantalla__bajada">
            <span class="cifra">N° <?= e($cliente['nro_cliente']) ?></span>
            · alta del <?= e(formatFecha($cliente['fecha_alta'])) ?>
        </p>
        <?php if ($vendedorVinculado): ?>
            <p class="pantalla__bajada">
                También es vendedor · código
                <a href="<?= APP_URL ?>/admin/vendedores/form.php?id=<?= (int) $vendedorVinculado['id'] ?>">
                    <span class="cifra"><?= e($vendedorVinculado['codigo_referido']) ?></span>
                </a>
            </p>
        <?php elseif (isAdmin() && (int) $cliente['activo'] === 1 && $cliente['estado'] === 'aprobado'): ?>
            <p class="pantalla__bajada">
                <a href="<?= APP_URL ?>/admin/vendedores/form.php?cliente_id=<?= (int) $cliente['id'] ?>"
                   class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-person-badge"></i> Convertir en vendedor
                </a>
            </p>
        <?php endif; ?>
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
            <label class="form-label" for="telefono">
                Telefono
                <?php if (!$esAlta): ?>
                    <span class="fw-normal text-secondary">(opcional)</span>
                <?php endif; ?>
            </label>
            <input type="tel" class="form-control" id="telefono" name="telefono"
                   value="<?= e($valor('telefono')) ?>"
                   inputmode="tel" autocomplete="tel" maxlength="30"
                   placeholder="381 555 1234" <?= $esAlta ? 'required' : '' ?>>
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
            <p class="fila__meta mb-3">
                Su clave es siempre su DNI.
                Ultimo ingreso: <?= e(formatFechaHora($cliente['ultimo_acceso'])) ?>.
            </p>

            <form method="post" action="<?= APP_URL ?>/admin/clientes/resetear_clave.php"
                  onsubmit="return confirm('Resincronizar la clave del portal con el DNI actual del cliente?')">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise"></i> Resincronizar clave con el DNI
                </button>
            </form>
            <p class="form-text mt-2 mb-0">
                No debería hacer falta: la clave se actualiza sola si editás
                el DNI. Es una herramienta manual por si un cliente de antes
                de este cambio quedó con una clave distinta.
            </p>
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
