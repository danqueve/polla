<?php
/**
 * Configuracion del juego: monto de la jugada y premio base garantizado.
 * Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ConfiguracionService;

requireAdmin();

$configuracion = new ConfiguracionService(getPDO());
$monto         = old('monto_jugada', (string) $configuracion->montoJugada());
$premioBase    = old('premio_base', (string) $configuracion->premioBase());
$ultimaMonto   = $configuracion->ultimaActualizacion('importe_jugada');
$ultimaPremio  = $configuracion->ultimaActualizacion('premio_base');

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

    <form method="post" id="form-configuracion" class="mt-3"
          action="<?= APP_URL ?>/admin/configuracion/guardar.php" novalidate>
        <?= csrfField() ?>

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Monto de la jugada</span>
            <p class="fila__meta mt-0 mb-3">
                Rige para las jugadas que se carguen de ahora en más.
                Las ya cargadas conservan el importe con el que se pagaron.
            </p>

            <div class="mb-2">
                <label class="form-label" for="monto_jugada">Monto por jugada</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control cifra" id="monto_jugada" name="monto_jugada"
                           value="<?= e($monto) ?>"
                           inputmode="decimal" min="1" step="1" required autofocus>
                </div>
            </div>

            <?php if ($ultimaMonto): ?>
                <p class="fila__meta mb-0">
                    Último cambio: <?= e(formatFechaHora($ultimaMonto['actualizado_en'])) ?>
                    <?php if ($ultimaMonto['actualizado_por']): ?>
                        · por <?= e($ultimaMonto['actualizado_por']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Premio base garantizado</span>
            <p class="fila__meta mt-0 mb-3">
                El pozo que se muestra y que se paga nunca baja de este monto,
                aunque lo vendido esa semana sea menos. Si hay que completar la
                diferencia, la cubre Decena de Oro — se aplica en cada ciclo,
                sin excepción.
            </p>

            <div class="mb-2">
                <label class="form-label" for="premio_base">Piso del pozo</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control cifra" id="premio_base" name="premio_base"
                           value="<?= e($premioBase) ?>"
                           inputmode="decimal" min="0" step="1" required>
                </div>
                <div class="form-text">
                    Se aplica con el valor vigente al momento en que se paga un
                    premio — cambiarlo no toca ciclos ya liquidados.
                </div>
            </div>

            <?php if ($ultimaPremio): ?>
                <p class="fila__meta mb-0">
                    Último cambio: <?= e(formatFechaHora($ultimaPremio['actualizado_en'])) ?>
                    <?php if ($ultimaPremio['actualizado_por']): ?>
                        · por <?= e($ultimaPremio['actualizado_por']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </form>

    <a href="<?= APP_URL ?>/admin/promociones/index.php" class="btn btn-outline-secondary w-100">
        <i class="bi bi-box-seam"></i> Promociones de paquete
    </a>
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
