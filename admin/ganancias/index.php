<?php
/**
 * Ganancias: lo que le queda a la empresa de cada ciclo (el porcentaje de
 * gastos de cada jugada, hoy 40%), cuanto ya se retiro y cuanto falta.
 * "Liquidar" registra el retiro de un ciclo cerrado.
 *
 * Una fila por ciclo -- cada semana y cada sabado por separado --, con la
 * recaudacion del ciclo. Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\GananciaService;
use Polla\Services\ParametroService;
use Polla\Support\ValidacionException;

requireAdmin();

$db        = getPDO();
$ganancias = GananciaService::crearDesde($db);

$tipo = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS) ? $_GET['tipo'] : null;

try {
    $filas     = $ganancias->porCiclo($tipo);
    $resumen   = GananciaService::resumirFilas($filas);
    $historial = $ganancias->historial(null, 12, $tipo);
} catch (ValidacionException $e) {
    // Falta aplicar la migracion: se explica en vez de mostrar una pagina en blanco.
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . APP_URL . '/admin/reportes/index.php');
    exit;
}

$porcentajePozo   = (new ParametroService($db))->porcentajePozo();
$porcentajeGastos = 100 - $porcentajePozo;
$qsTipo           = $tipo !== null ? '?tipo=' . $tipo : '';
$etiquetaFiltro   = $tipo === null
    ? ''
    : ($tipo === CicloService::TIPO_SABADO ? ' de sábados' : ' de la semana');

$pageTitle        = 'Ganancias · ' . APP_NAME;
$navSeccion       = 'ganancias';
$pageSectionTitle = 'Ganancias';
$breadcrumb       = [
    ['label' => 'Ganancias', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Ganancias</h1>
            <p class="g-page-subtitle">
                El <?= (float) $porcentajeGastos ?>% de cada jugada es de la empresa.
                Acá ves cuánto dejó cada ciclo y cuánto ya retiraste.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($filas): ?>
                <a href="<?= APP_URL ?>/admin/ganancias/imprimir.php<?= e($qsTipo) ?>"
                   class="g-btn g-btn--outline" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                </a>
                <a href="<?= APP_URL ?>/admin/ganancias/exportar.php<?= e($qsTipo) ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resumen -->
    <div class="g-stat-grid g-animate g-animate-delay-1">
        <div class="g-stat g-stat--accent">
            <div class="g-stat__icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($resumen['pendiente'])) ?></div>
            <div class="g-stat__label">Pendiente de liquidar</div>
            <div class="small text-white-50 mt-2">
                <?= $resumen['ciclos_pendientes'] ?>
                <?= $resumen['ciclos_pendientes'] === 1 ? 'ciclo cerrado' : 'ciclos cerrados' ?>
            </div>
        </div>

        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--green"><i class="bi bi-check2-circle"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($resumen['liquidado'])) ?></div>
            <div class="g-stat__label">Ya liquidado</div>
        </div>

        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--blue"><i class="bi bi-activity"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($resumen['en_curso'])) ?></div>
            <div class="g-stat__label">En curso (ciclos abiertos)</div>
        </div>

        <div class="g-stat">
            <div class="g-stat__icon g-stat__icon--purple"><i class="bi bi-cash-stack"></i></div>
            <div class="g-stat__value"><?= e(formatPesosDec($resumen['recaudado'])) ?></div>
            <div class="g-stat__label">Recaudado en total</div>
            <div class="small text-muted mt-2"><?= $resumen['jugadas'] ?> jugadas</div>
        </div>
    </div>

    <!-- Filtro de juego -->
    <div class="g-card mb-4 g-animate g-animate-delay-2">
        <div class="g-card__body p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $tipo === null ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/ganancias/index.php">
                        <i class="bi bi-collection me-1"></i> Todos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $tipo === CicloService::TIPO_SEMANAL ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/ganancias/index.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">
                        <i class="bi bi-calendar-week me-1"></i> Semana
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $tipo === CicloService::TIPO_SABADO ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/ganancias/index.php?tipo=<?= CicloService::TIPO_SABADO ?>">
                        <i class="bi bi-star me-1"></i> Sábado
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Liquidar todo lo pendiente -->
    <?php if ($resumen['pendiente'] > 0): ?>
        <div class="g-card mb-4 g-animate g-animate-delay-2">
            <div class="g-card__body p-3">
                <form method="post" action="<?= APP_URL ?>/admin/ganancias/liquidar.php"
                      class="row g-2 align-items-end"
                      onsubmit="return confirm('¿Liquidar <?= e(formatPesosDec($resumen['pendiente'])) ?> de <?= $resumen['ciclos_pendientes'] ?> <?= $resumen['ciclos_pendientes'] === 1 ? 'ciclo cerrado' : 'ciclos cerrados' ?>?')">
                    <?= csrfField() ?>
                    <input type="hidden" name="todo" value="1">
                    <input type="hidden" name="tipo" value="<?= e((string) $tipo) ?>">
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold small text-muted" for="nota-todo">
                            Nota para estas liquidaciones (opcional)
                        </label>
                        <input type="text" class="form-control" id="nota-todo" name="nota" maxlength="200"
                               placeholder="Ej: retiros hasta octubre, transferido a la cuenta X">
                    </div>
                    <div class="col-12 col-md-5">
                        <button type="submit" class="g-btn g-btn--primary w-100">
                            <i class="bi bi-cash-coin"></i>
                            Liquidar todo lo pendiente<?= e($etiquetaFiltro) ?> (<?= e(formatPesosDec($resumen['pendiente'])) ?>)
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabla por ciclo -->
    <div class="g-card g-list-card mb-4 g-animate g-animate-delay-3">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-table me-1"></i>
                Ganancia por ciclo (<?= count($filas) ?>)
            </h2>
        </div>

        <div class="g-card__body p-0" style="overflow:hidden">
            <?php if (!$filas): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-graph-up fs-1 d-block mb-3 text-muted"></i>
                    Todavía no hay jugadas pagadas<?= e($etiquetaFiltro) ?>.
                </div>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Ciclo</th>
                                <th class="text-end">Jugadas</th>
                                <th class="text-end">Recaudado</th>
                                <th class="text-end">Al pozo (<?= (float) $porcentajePozo ?>%)</th>
                                <th class="text-end">Ganancia (<?= (float) $porcentajeGastos ?>%)</th>
                                <th class="text-end">Liquidado</th>
                                <th class="text-end">Pendiente</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $f): ?>
                                <?php
                                $nombreCiclo = (CicloService::TIPOS[$f['tipo']] ?? $f['tipo']) . ' #' . $f['numero'];
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= APP_URL ?>/admin/ganancias/ciclo.php?id=<?= $f['id'] ?>"
                                           class="fw-bold text-decoration-none"><?= e($nombreCiclo) ?></a>
                                        <span class="text-muted fw-normal small d-block"><?= e(CicloService::rotulo($f)) ?></span>
                                        <?php if ($f['estado'] === CicloService::ESTADO_ABIERTO): ?>
                                            <span class="g-badge g-badge--success">Abierto</span>
                                        <?php elseif ($f['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
                                            <span class="g-badge g-badge--info">Próximo</span>
                                        <?php elseif ($f['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                                            <span class="g-badge g-badge--warning"><i class="bi bi-trophy-fill me-1"></i>Con ganador</span>
                                        <?php else: ?>
                                            <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Sin ganador</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?= $f['jugadas'] ?></td>
                                    <td class="text-end"><?= e(formatPesosDec($f['recaudado'])) ?></td>
                                    <td class="text-end text-muted"><?= e(formatPesosDec($f['al_pozo'])) ?></td>
                                    <td class="text-end fw-bold"><?= e(formatPesosDec($f['ganancia'])) ?></td>
                                    <td class="text-end text-muted">
                                        <?= $f['liquidado'] > 0 ? e(formatPesosDec($f['liquidado'])) : '—' ?>
                                    </td>
                                    <td class="text-end fw-bold <?= $f['liquidable'] ? 'texto-oro' : 'text-muted' ?>">
                                        <?php if (!$f['cerrado']): ?>
                                            —
                                        <?php else: ?>
                                            <?= e(formatPesosDec(max(0.0, $f['pendiente']))) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <?php if ($f['liquidable']): ?>
                                            <form method="post" action="<?= APP_URL ?>/admin/ganancias/liquidar.php" class="d-inline"
                                                  onsubmit="return confirm('¿Liquidar <?= e(formatPesosDec($f['pendiente'])) ?> de <?= e($nombreCiclo) ?>?')">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="ciclo" value="<?= $f['id'] ?>">
                                                <input type="hidden" name="tipo" value="<?= e((string) $tipo) ?>">
                                                <button type="submit" class="g-btn g-btn--primary g-btn--sm">
                                                    <i class="bi bi-cash-coin"></i> Liquidar
                                                </button>
                                            </form>
                                        <?php elseif ($f['sobre_liquidado']): ?>
                                            <span class="g-badge g-badge--warning"
                                                  title="Se liquidó más de lo que hoy suma este ciclo. Revisalo en el detalle.">
                                                Liquidado de más
                                            </span>
                                        <?php elseif (!$f['cerrado']): ?>
                                            <span class="g-badge g-badge--info">En curso</span>
                                        <?php elseif ($f['liquidado'] > 0): ?>
                                            <span class="g-badge g-badge--success"><i class="bi bi-check2 me-1"></i>Liquidado</span>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin ganancia</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="fw-bold">Total</td>
                                <td class="text-end fw-bold"><?= $resumen['jugadas'] ?></td>
                                <td class="text-end fw-bold"><?= e(formatPesosDec($resumen['recaudado'])) ?></td>
                                <td class="text-end fw-bold"><?= e(formatPesosDec($resumen['al_pozo'])) ?></td>
                                <td class="text-end fw-bold"><?= e(formatPesosDec($resumen['ganancia'])) ?></td>
                                <td class="text-end fw-bold"><?= e(formatPesosDec($resumen['liquidado'])) ?></td>
                                <td class="text-end fw-bold texto-oro"><?= e(formatPesosDec($resumen['pendiente'])) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="tabla-pista px-3 pb-2 mb-0 d-md-none">
                    <i class="bi bi-arrow-left-right"></i> Deslizá para ver todas las columnas.
                </p>
                <p class="small text-muted px-3 pb-3 mb-0">
                    La ganancia es lo que se separó de cada jugada pagada, <strong>antes</strong> de las comisiones
                    de referidos y del premio garantizado. Solo se liquida un ciclo cerrado; los ciclos
                    abiertos suman en "En curso" hasta que cierren.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ultimas liquidaciones -->
    <div class="g-card g-list-card g-animate g-animate-delay-3">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-clock-history me-1"></i>
                Últimas liquidaciones
            </h2>
        </div>
        <div class="g-card__body">
            <?php if (!$historial): ?>
                <div class="p-4 text-center text-secondary small">
                    Todavía no liquidaste ninguna ganancia.
                </div>
            <?php else: ?>
                <?php foreach ($historial as $h): ?>
                    <?php $anulada = $h['anulada_en'] !== null; ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="min-w-0">
                                <div class="g-list-item__title">
                                    <a href="<?= APP_URL ?>/admin/ganancias/ciclo.php?id=<?= (int) $h['ciclo_id'] ?>"
                                       class="text-decoration-none">
                                        <?= e((CicloService::TIPOS[$h['tipo']] ?? $h['tipo']) . ' #' . $h['numero']) ?>
                                    </a>
                                    <?php if ($anulada): ?>
                                        <span class="g-badge ms-1" style="background:#e5e7eb;color:#4b5563">Anulada</span>
                                    <?php endif; ?>
                                </div>
                                <div class="g-list-item__meta mt-1">
                                    <?= e(formatFechaHora($h['fecha'])) ?>
                                    <?php if ($h['liquidado_por_nombre']): ?>
                                        · <?= e($h['liquidado_por_nombre']) ?>
                                    <?php endif; ?>
                                    <?php if ($h['nota']): ?>
                                        · «<?= e($h['nota']) ?>»
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="fw-bold fs-6 <?= $anulada ? 'text-muted' : 'text-dark' ?>"
                                 <?= $anulada ? 'style="text-decoration:line-through"' : '' ?>>
                                <?= e(formatPesosDec($h['monto'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
