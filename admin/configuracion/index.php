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

$cantidadSabado        = old('numeros_por_jugada_sabado', (string) $configuracion->numerosPorJugadaSabado());
$ultimaCantidadSabado  = $configuracion->ultimaActualizacion('numeros_por_jugada_sabado');

$pageTitle        = 'Configuración · ' . APP_NAME;
$navSeccion       = 'configuracion';
$pageSectionTitle = 'Configuración';
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Configuración', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Configuración</h1>
            <p class="g-page-subtitle">Parámetros del juego semanal y de sábados</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/promociones/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-box-seam"></i> Promociones
            </a>
            <a href="<?= APP_URL ?>/admin/referidos/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-diagram-3"></i> Referidos
            </a>
        </div>
    </div>

    <form method="post" id="form-configuracion"
          action="<?= APP_URL ?>/admin/configuracion/guardar.php" novalidate>
        <?= csrfField() ?>

        <div class="row g-4">
            <div class="col-12 col-lg-6">
                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-ticket-perforated me-1"></i>
                            Monto de la Jugada
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Rige para las jugadas que se carguen de ahora en más.
                            Las ya cargadas conservan el importe con el que se pagaron.
                        </p>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="monto_jugada">Monto por jugada</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-lg" id="monto_jugada" name="monto_jugada"
                                       value="<?= e($monto) ?>"
                                       inputmode="decimal" min="1" step="1" required autofocus>
                            </div>
                        </div>

                        <?php if ($ultimaMonto): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaMonto['actualizado_en'])) ?>
                                <?php if ($ultimaMonto['actualizado_por']): ?>
                                    · por <?= e($ultimaMonto['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-trophy me-1"></i>
                            Premio Base Garantizado
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            El pozo que se muestra y que se paga nunca baja de este monto,
                            aunque lo vendido esa semana sea menos. Si hay que completar la
                            diferencia, la cubre Decena de Oro — se aplica en cada ciclo,
                            sin excepción.
                        </p>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="premio_base">Piso del pozo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-lg" id="premio_base" name="premio_base"
                                       value="<?= e($premioBase) ?>"
                                       inputmode="decimal" min="0" step="1" required>
                            </div>
                            <div class="form-text">
                                Se aplica con el valor vigente al momento en que se paga un
                                premio — cambiarlo no toca ciclos ya liquidados.
                            </div>
                        </div>

                        <?php if ($ultimaPremio): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaPremio['actualizado_en'])) ?>
                                <?php if ($ultimaPremio['actualizado_por']): ?>
                                    · por <?= e($ultimaPremio['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-3">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-percent me-1"></i>
                            Comisión por Referidos
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Porcentaje global que cobra el vendedor o supervisor que
                            refirió a un cliente, sobre el importe de cada jugada
                            confirmada de ese cliente. Aplica igual al juego semanal y
                            al de sábados. En 0%, nadie cobra nada.
                        </p>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="comision_jugada_porcentaje">Porcentaje por jugada</label>
                            <div class="input-group">
                                <input type="number" class="form-control form-control-lg" id="comision_jugada_porcentaje"
                                       name="comision_jugada_porcentaje"
                                       value="<?= e($comisionPorcentaje) ?>"
                                       inputmode="decimal" min="0" max="100" step="0.5" required>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        <?php if ($ultimaComision): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaComision['actualizado_en'])) ?>
                                <?php if ($ultimaComision['actualizado_por']): ?>
                                    · por <?= e($ultimaComision['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-star me-1"></i>
                            Monto de Jugada — Sábados
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Caja separada del juego semanal. Rige para las jugadas de sábados
                            que se carguen de ahora en más.
                        </p>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="monto_jugada_sabado">Monto por jugada</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-lg" id="monto_jugada_sabado" name="monto_jugada_sabado"
                                       value="<?= e($montoSabado) ?>"
                                       inputmode="decimal" min="1" step="1" required>
                            </div>
                        </div>

                        <?php if ($ultimaMontoSabado): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaMontoSabado['actualizado_en'])) ?>
                                <?php if ($ultimaMontoSabado['actualizado_por']): ?>
                                    · por <?= e($ultimaMontoSabado['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        <hr>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="numeros_por_jugada_sabado">Números por jugada</label>
                            <input type="number" class="form-control form-control-lg" id="numeros_por_jugada_sabado"
                                   name="numeros_por_jugada_sabado"
                                   value="<?= e($cantidadSabado) ?>"
                                   inputmode="numeric" min="1" max="20" step="1" required>
                            <div class="form-text">
                                Cuántos números distintos elige el cliente para jugar el sábado
                                (el semanal sigue en 10, fijo). El extracto de cada turno sigue
                                sacando 20, sin cambios — bajar esto sube mucho la chance de ganar.
                            </div>
                        </div>

                        <?php if ($ultimaCantidadSabado): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaCantidadSabado['actualizado_en'])) ?>
                                <?php if ($ultimaCantidadSabado['actualizado_por']): ?>
                                    · por <?= e($ultimaCantidadSabado['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-trophy me-1"></i>
                            Premio Base Garantizado — Sábados
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <p class="text-muted small mb-3">
                            Piso del pozo de sábados, independiente del piso semanal.
                        </p>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="premio_base_sabado">Piso del pozo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control form-control-lg" id="premio_base_sabado" name="premio_base_sabado"
                                       value="<?= e($premioBaseSabado) ?>"
                                       inputmode="decimal" min="0" step="1" required>
                            </div>
                        </div>

                        <?php if ($ultimaPremioSabado): ?>
                            <p class="text-muted small mb-0">
                                Último cambio: <?= e(formatFechaHora($ultimaPremioSabado['actualizado_en'])) ?>
                                <?php if ($ultimaPremioSabado['actualizado_por']): ?>
                                    · por <?= e($ultimaPremioSabado['actualizado_por']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="g-card mb-4 g-animate g-animate-delay-3">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-clock me-1"></i>
                            Horarios Límite de Carga
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="horario_limite_semanal">Semanal (corte del lunes)</label>
                            <input type="time" class="form-control form-control-lg" id="horario_limite_semanal" name="horario_limite_semanal"
                                   value="<?= e($horarioSemanal) ?>" required>
                            <div class="form-text">
                                Hasta esta hora del lunes, lo que se cargue cuenta para el pozo
                                de esta semana. Después (martes a domingo), la carga no se
                                bloquea: lo que se cargue queda anotado automáticamente para
                                la semana que viene.
                            </div>
                            <?php if ($ultimoHorarioSemanal): ?>
                                <p class="text-muted small mb-0 mt-1">
                                    Último cambio: <?= e(formatFechaHora($ultimoHorarioSemanal['actualizado_en'])) ?>
                                    <?php if ($ultimoHorarioSemanal['actualizado_por']): ?>
                                        · por <?= e($ultimoHorarioSemanal['actualizado_por']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-semibold small text-muted" for="horario_limite_sabado">Sábados</label>
                            <input type="time" class="form-control form-control-lg" id="horario_limite_sabado" name="horario_limite_sabado"
                                   value="<?= e($horarioSabado) ?>" required>
                            <div class="form-text">
                                Después de este horario, clientes y staff no pueden cargar más
                                jugadas de sábado hasta el sábado siguiente.
                            </div>
                            <?php if ($ultimoHorarioSabado): ?>
                                <p class="text-muted small mb-0 mt-1">
                                    Último cambio: <?= e(formatFechaHora($ultimoHorarioSabado['actualizado_en'])) ?>
                                    <?php if ($ultimoHorarioSabado['actualizado_por']): ?>
                                        · por <?= e($ultimoHorarioSabado['actualizado_por']) ?>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

</main>

<div class="accion-fija">
    <div class="accion-fija__interior">
        <button type="submit" form="form-configuracion" class="g-btn g-btn--primary w-100">
            <i class="bi bi-check-lg"></i> Guardar
        </button>
    </div>
</div>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
