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

$montoSabado        = old('monto_jugada_sabado', (string) $configuracion->montoJugadaSabado());
$premioBaseSabado   = old('premio_base_sabado', (string) $configuracion->premioBaseSabado());
$ultimaMontoSabado  = $configuracion->ultimaActualizacion('importe_jugada_sabado');
$ultimaPremioSabado = $configuracion->ultimaActualizacion('premio_base_sabado');

$horarioSemanal      = old('horario_limite_semanal', $configuracion->horarioLimiteSemanal());
$horarioSabado       = old('horario_limite_sabado', $configuracion->horarioLimiteSabado());
$ultimoHorarioSemanal = $configuracion->ultimaActualizacion('horario_limite_semanal');
$ultimoHorarioSabado  = $configuracion->ultimaActualizacion('horario_limite_sabado');

$comisionPorcentaje = old('comision_jugada_porcentaje', (string) $configuracion->comisionJugadaPorcentaje());
$ultimaComision     = $configuracion->ultimaActualizacion('comision_jugada_porcentaje');

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

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Monto de jugada — Sábados</span>
            <p class="fila__meta mt-0 mb-3">
                Caja separada del juego semanal. Rige para las jugadas de sábados
                que se carguen de ahora en más.
            </p>

            <div class="mb-2">
                <label class="form-label" for="monto_jugada_sabado">Monto por jugada</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control cifra" id="monto_jugada_sabado" name="monto_jugada_sabado"
                           value="<?= e($montoSabado) ?>"
                           inputmode="decimal" min="1" step="1" required>
                </div>
            </div>

            <?php if ($ultimaMontoSabado): ?>
                <p class="fila__meta mb-0">
                    Último cambio: <?= e(formatFechaHora($ultimaMontoSabado['actualizado_en'])) ?>
                    <?php if ($ultimaMontoSabado['actualizado_por']): ?>
                        · por <?= e($ultimaMontoSabado['actualizado_por']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Premio base garantizado — Sábados</span>
            <p class="fila__meta mt-0 mb-3">
                Piso del pozo de sábados, independiente del piso semanal.
            </p>

            <div class="mb-2">
                <label class="form-label" for="premio_base_sabado">Piso del pozo</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control cifra" id="premio_base_sabado" name="premio_base_sabado"
                           value="<?= e($premioBaseSabado) ?>"
                           inputmode="decimal" min="0" step="1" required>
                </div>
            </div>

            <?php if ($ultimaPremioSabado): ?>
                <p class="fila__meta mb-0">
                    Último cambio: <?= e(formatFechaHora($ultimaPremioSabado['actualizado_en'])) ?>
                    <?php if ($ultimaPremioSabado['actualizado_por']): ?>
                        · por <?= e($ultimaPremioSabado['actualizado_por']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Horarios límite de carga</span>
            <p class="fila__meta mt-0 mb-3">
                Después de este horario, clientes y staff no pueden cargar más
                jugadas de ese juego hasta el otro día.
            </p>

            <div class="mb-3">
                <label class="form-label" for="horario_limite_semanal">Semanal (lunes a viernes)</label>
                <input type="time" class="form-control cifra" id="horario_limite_semanal" name="horario_limite_semanal"
                       value="<?= e($horarioSemanal) ?>" required>
                <?php if ($ultimoHorarioSemanal): ?>
                    <p class="fila__meta mb-0 mt-1">
                        Último cambio: <?= e(formatFechaHora($ultimoHorarioSemanal['actualizado_en'])) ?>
                        <?php if ($ultimoHorarioSemanal['actualizado_por']): ?>
                            · por <?= e($ultimoHorarioSemanal['actualizado_por']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>

            <div class="mb-2">
                <label class="form-label" for="horario_limite_sabado">Sábados</label>
                <input type="time" class="form-control cifra" id="horario_limite_sabado" name="horario_limite_sabado"
                       value="<?= e($horarioSabado) ?>" required>
                <?php if ($ultimoHorarioSabado): ?>
                    <p class="fila__meta mb-0 mt-1">
                        Último cambio: <?= e(formatFechaHora($ultimoHorarioSabado['actualizado_en'])) ?>
                        <?php if ($ultimoHorarioSabado['actualizado_por']): ?>
                            · por <?= e($ultimoHorarioSabado['actualizado_por']) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="tarjeta p-3 mb-3">
            <span class="rotulo d-block mb-2">Comisión por referidos</span>
            <p class="fila__meta mt-0 mb-3">
                Porcentaje global que cobra el vendedor o supervisor que
                refirió a un cliente, sobre el importe de cada jugada
                confirmada de ese cliente. Aplica igual al juego semanal y
                al de sábados. En 0%, nadie cobra nada.
            </p>

            <div class="mb-2">
                <label class="form-label" for="comision_jugada_porcentaje">Porcentaje por jugada</label>
                <div class="input-group">
                    <input type="number" class="form-control cifra" id="comision_jugada_porcentaje"
                           name="comision_jugada_porcentaje"
                           value="<?= e($comisionPorcentaje) ?>"
                           inputmode="decimal" min="0" max="100" step="0.5" required>
                    <span class="input-group-text">%</span>
                </div>
            </div>

            <?php if ($ultimaComision): ?>
                <p class="fila__meta mb-0">
                    Último cambio: <?= e(formatFechaHora($ultimaComision['actualizado_en'])) ?>
                    <?php if ($ultimaComision['actualizado_por']): ?>
                        · por <?= e($ultimaComision['actualizado_por']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </form>

    <div class="d-flex flex-column gap-2">
        <a href="<?= APP_URL ?>/admin/promociones/index.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-box-seam"></i> Promociones de paquete
        </a>
        <a href="<?= APP_URL ?>/admin/referidos/index.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-diagram-3"></i> Vendedores y referidos
        </a>
    </div>
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
require __DIR__ . '/../../includes/foot.php';
