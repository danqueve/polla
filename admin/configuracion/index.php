<?php
/**
 * Configuracion del juego. Por ahora, el monto de la jugada.
 * Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ConfiguracionService;

requireAdmin();

$configuracion = new ConfiguracionService(getPDO());
$monto         = old('monto_jugada', (string) $configuracion->montoJugada());
$ultima        = $configuracion->ultimaActualizacion();

$pageTitle  = 'Configuración · ' . APP_NAME;
$navSeccion = 'configuracion';
$bodyClass  = 'con-accion-fija';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Configuración</h1>
    <p class="pantalla__bajada">
        El cambio rige para las jugadas que se carguen de ahora en más.
        Las ya cargadas conservan el importe con el que se pagaron.
    </p>

    <form method="post" id="form-configuracion" class="tarjeta p-3 mt-3"
          action="<?= APP_URL ?>/admin/configuracion/guardar.php" novalidate>
        <?= csrfField() ?>

        <div class="mb-2">
            <label class="form-label" for="monto_jugada">Monto de la jugada</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" class="form-control cifra" id="monto_jugada" name="monto_jugada"
                       value="<?= e($monto) ?>"
                       inputmode="decimal" min="1" step="1" required autofocus>
            </div>
        </div>

        <?php if ($ultima): ?>
            <p class="fila__meta mb-0">
                Último cambio: <?= e(formatFechaHora($ultima['actualizado_en'])) ?>
                <?php if ($ultima['actualizado_por']): ?>
                    · por <?= e($ultima['actualizado_por']) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </form>
</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-configuracion" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Guardar
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
