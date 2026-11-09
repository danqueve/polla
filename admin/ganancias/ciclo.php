<?php
/**
 * Ganancia de UN ciclo: cuanto dejo, cuanto se retiro, el formulario de
 * liquidar (con nota opcional) y el historial de sus liquidaciones, con la
 * posibilidad de anular una hecha por error. Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Services\ParametroService;
use Polla\Support\ValidacionException;

requireAdmin();

$db        = getPDO();
$ganancias = GananciaService::crearDesde($db);
$id        = (int) ($_GET['id'] ?? 0);

try {
    $ciclo = $id > 0 ? $ganancias->deCiclo($id) : null;
    if ($ciclo) {
        $historial = $ganancias->historial($id, 50);
    }
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/reportes/index.php');
    exit;
}

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/ganancias/index.php');
    exit;
}

$porcentajePozo   = (new ParametroService($db))->porcentajePozo();
$porcentajeGastos = 100 - $porcentajePozo;
$esSabado         = $ciclo['tipo'] === CicloService::TIPO_SABADO;
$nombreCiclo      = (CicloService::TIPOS[$ciclo['tipo']] ?? $ciclo['tipo']) . ' #' . $ciclo['numero'];
$urlVerCiclo      = APP_URL . ($esSabado ? '/admin/sabados/ver.php?id=' : '/admin/ciclos/ver.php?id=') . $ciclo['id'];

$pageTitle        = $nombreCiclo . ' · Ganancias · ' . APP_NAME;
$navSeccion       = 'ganancias';
$pageSectionTitle = 'Ganancias';
$breadcrumb       = [
    ['label' => 'Ganancias', 'url' => APP_URL . '/admin/ganancias/index.php'],
    ['label' => $nombreCiclo, 'url' => '']
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
                <span class="g-badge g-badge--blue"><?= e($nombreCiclo) ?></span>
                <?php if ($ciclo['estado'] === CicloService::ESTADO_ABIERTO): ?>
                    <span class="g-badge g-badge--success">Abierto</span>
                <?php elseif ($ciclo['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
                    <span class="g-badge g-badge--info">Próximo</span>
                <?php elseif ($ciclo['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                    <span class="g-badge g-badge--warning"><i class="bi bi-trophy-fill me-1"></i>Cerrado con ganador</span>
                <?php else: ?>
                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Cerrado sin ganador</span>
                <?php endif; ?>
            </div>
            <h1 class="g-page-title mt-2">Ganancia de <?= e($nombreCiclo) ?></h1>
            <p class="g-page-subtitle"><?= e(CicloService::rotulo($ciclo)) ?></p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/ganancias/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Ganancias
            </a>
            <a href="<?= e($urlVerCiclo) ?>" class="g-btn g-btn--outline">
                <i class="bi bi-eye"></i> Ver el ciclo
            </a>
        </div>
    </div>

    <div class="g-stat-grid g-animate g-animate-delay-1">
        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--purple"><i class="bi bi-cash-stack"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($ciclo['recaudado'])) ?></div>
            <div class="g-stat__label">Recaudado</div>
            <div class="small text-muted mt-2"><?= $ciclo['jugadas'] ?> jugadas</div>
        </div>
        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--blue"><i class="bi bi-trophy"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($ciclo['al_pozo'])) ?></div>
            <div class="g-stat__label">Al pozo (<?= (float) $porcentajePozo ?>%)</div>
        </div>
        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--green"><i class="bi bi-wallet2"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($ciclo['ganancia'])) ?></div>
            <div class="g-stat__label">Ganancia (<?= (float) $porcentajeGastos ?>%)</div>
        </div>
        <div class="g-stat g-stat--accent">
            <div class="g-stat__icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="g-stat__value">
                <?= $ciclo['cerrado'] ? e(formatPesosDec(max(0.0, $ciclo['pendiente']))) : '—' ?>
            </div>
            <div class="g-stat__label">Pendiente de liquidar</div>
            <div class="small text-white-50 mt-2">Ya liquidado: <?= e(formatPesosDec($ciclo['liquidado'])) ?></div>
        </div>
    </div>

    <!-- Estado / accion -->
    <div class="g-card mb-4 g-animate g-animate-delay-2" id="liquidar">
        <div class="g-card__header">
            <h2 class="g-card__title"><i class="bi bi-cash-coin me-1"></i> Liquidar</h2>
        </div>
        <div class="g-card__body p-3">
            <?php if ($ciclo['liquidable']): ?>
                <form method="post" action="<?= APP_URL ?>/admin/ganancias/liquidar.php"
                      class="row g-2 align-items-end"
                      onsubmit="return confirm('¿Liquidar <?= e(formatPesosDec($ciclo['pendiente'])) ?> de <?= e($nombreCiclo) ?>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="ciclo" value="<?= $ciclo['id'] ?>">
                    <input type="hidden" name="volver" value="ciclo">
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold small text-muted" for="nota">Nota (opcional)</label>
                        <input type="text" class="form-control" id="nota" name="nota" maxlength="200"
                               placeholder="Ej: transferido a la cuenta X">
                    </div>
                    <div class="col-12 col-md-5">
                        <button type="submit" class="g-btn g-btn--primary w-100">
                            <i class="bi bi-cash-coin"></i> Liquidar <?= e(formatPesosDec($ciclo['pendiente'])) ?>
                        </button>
                    </div>
                </form>
                <p class="small text-muted mt-2 mb-0">
                    Se liquida siempre el total pendiente del ciclo. Si fue un error, la podés anular acá abajo.
                </p>
            <?php elseif ($ciclo['sobre_liquidado']): ?>
                <div class="g-alert-banner g-alert-banner--warning mb-0">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>
                        Se liquidó <strong><?= e(formatPesosDec($ciclo['liquidado'])) ?></strong>, pero hoy este ciclo
                        suma <strong><?= e(formatPesosDec($ciclo['ganancia'])) ?></strong>: bajó después de liquidarse
                        (por ejemplo, una jugada anulada). Si corresponde, anulá la liquidación de abajo
                        y volvé a liquidar el monto correcto.
                    </div>
                </div>
            <?php elseif (!$ciclo['cerrado']): ?>
                <p class="text-muted mb-0">
                    <i class="bi bi-activity me-1"></i>
                    Este ciclo sigue en curso: la ganancia todavía puede cambiar con nuevas jugadas.
                    Cuando cierre vas a poder liquidarla.
                </p>
            <?php elseif ($ciclo['liquidado'] > 0): ?>
                <p class="text-success fw-semibold mb-0">
                    <i class="bi bi-check2-circle me-1"></i>
                    Este ciclo ya está liquidado: no queda nada pendiente.
                </p>
            <?php else: ?>
                <p class="text-muted mb-0">Este ciclo cerró sin ganancia para liquidar.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Historial del ciclo -->
    <div class="g-card g-list-card g-animate g-animate-delay-3">
        <div class="g-card__header">
            <h2 class="g-card__title"><i class="bi bi-clock-history me-1"></i> Liquidaciones de este ciclo</h2>
        </div>
        <div class="g-card__body">
            <?php if (!$historial): ?>
                <div class="p-4 text-center text-secondary small">Todavía no se liquidó nada de este ciclo.</div>
            <?php else: ?>
                <?php foreach ($historial as $h): ?>
                    <?php $anulada = $h['anulada_en'] !== null; ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="g-list-item__title"
                                     <?= $anulada ? 'style="text-decoration:line-through"' : '' ?>>
                                    <?= e(formatPesosDec($h['monto'])) ?>
                                    <?php if ($anulada): ?>
                                        <span class="g-badge ms-1" style="background:#e5e7eb;color:#4b5563;text-decoration:none">Anulada</span>
                                    <?php endif; ?>
                                </div>
                                <div class="g-list-item__meta mt-1">
                                    <?= e(formatFechaHora($h['fecha'])) ?>
                                    <?php if ($h['liquidado_por_nombre']): ?>
                                        · liquidó <?= e($h['liquidado_por_nombre']) ?>
                                    <?php endif; ?>
                                    <?php if ($h['nota']): ?>
                                        · «<?= e($h['nota']) ?>»
                                    <?php endif; ?>
                                    <?php if ($anulada): ?>
                                        <br>Anulada el <?= e(formatFechaHora($h['anulada_en'])) ?>
                                        <?php if ($h['anulada_por_nombre']): ?>por <?= e($h['anulada_por_nombre']) ?><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$anulada): ?>
                                <form method="post" action="<?= APP_URL ?>/admin/ganancias/anular.php" class="d-inline"
                                      onsubmit="return confirm('¿Anular la liquidación de <?= e(formatPesosDec($h['monto'])) ?>? Ese monto vuelve a figurar como pendiente del ciclo.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                    <input type="hidden" name="ciclo" value="<?= $ciclo['id'] ?>">
                                    <input type="hidden" name="volver" value="ciclo">
                                    <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0">
                                        <i class="bi bi-slash-circle"></i> Anular
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
