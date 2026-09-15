<?php
/** Tablero del juego de sábados con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PozoService;
use Polla\Services\SorteoService;

requireLogin();

$db         = getPDO();
$ciclos     = new CicloService($db);
$parametros = new ParametroService($db);
$jugadas    = JugadaService::crearDesde($db);
$sorteos    = SorteoService::crearDesde($db);

$ciclo   = $ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);
$cicloId = (int) $ciclo['id'];
$resumen = $ciclos->resumen($cicloId);
$ultimas = $jugadas->ultimas(5, CicloService::TIPO_SABADO);

$turnos   = $sorteos->listarPorCiclo($cicloId);
$arrastre = (float) ($ciclo['monto_arrastrado'] ?? 0);

$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);
$premioBase   = $parametros->premioBaseSabado();
$pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
$subsidio     = max(0.0, $premioBase - $pozoReal);

$faltan = 5 - count($turnos);

$pageTitle        = 'Sábados · ' . APP_NAME;
$navSeccion       = 'sabados';
$pageSectionTitle = 'Edición Especial de Sábados';
$breadcrumb       = [
    ['label' => 'Sábados', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue">Sábado <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge g-badge--success">Abierto</span>
            </div>
            <h1 class="g-page-title mt-2"><?= e(CicloService::rotulo($ciclo)) ?></h1>
            <p class="g-page-subtitle">Modalidad 5 números por jugada · 5 turnos de sorteo en el día</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>" class="g-btn g-btn--primary">
                <i class="bi bi-plus-lg"></i> Cargar Jugada Sábado
            </a>
            <a href="<?= APP_URL ?>/admin/sabados/sorteo_nuevo.php" class="g-btn g-btn--outline">
                <i class="bi bi-dice-5"></i> Cargar Turno
            </a>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="row g-4">

        <!-- Columna Izquierda: Pozo y Jugadas -->
        <div class="col-12 col-lg-8">

            <!-- Hero Card: Pozo Sábados -->
            <div class="g-hero g-animate g-animate-delay-1">
                <div class="g-hero__label">
                    <i class="bi bi-star-fill me-1"></i> Pozo Acumulado de Sábados
                </div>
                <div class="g-hero__amount">
                    <?= e(formatPesos($pozoMostrado)) ?>
                </div>

                <div class="g-hero__detail">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php if ($arrastre > 0): ?>
                        Incluye <?= e(formatPesos($arrastre)) ?> arrastrados del sábado anterior
                    <?php else: ?>
                        <?= (int) $parametros->porcentajePozo() ?>% de cada jugada de sábado pagada
                    <?php endif; ?>
                </div>

                <?php if ($subsidio > 0): ?>
                    <div class="g-hero__subsidy">
                        <i class="bi bi-shield-check"></i>
                        <span>De los cuales <?= e(formatPesos($pozoReal)) ?> son reales · Decena de Oro subsidia <?= e(formatPesos($subsidio)) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="g-stat-grid g-animate g-animate-delay-2">
                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--blue">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="g-stat__value"><?= (int) $resumen['jugadas_total'] ?></div>
                    <div class="g-stat__label">Jugadas Sábado</div>
                </div>

                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--purple">
                        <i class="bi bi-dice-5"></i>
                    </div>
                    <div class="g-stat__value"><?= count($turnos) ?> / 5</div>
                    <div class="g-stat__label">Turnos Cargados</div>
                </div>

                <div class="g-stat g-stat--accent">
                    <div class="g-stat__icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="g-stat__value"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                    <div class="g-stat__label">Recaudación Sábado</div>
                </div>
            </div>

            <!-- Últimas Jugadas Sábado -->
            <div class="g-card g-list-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-clock-history"></i>
                        Últimas Jugadas de Sábado
                    </h2>
                    <a href="<?= APP_URL ?>/admin/sabados/ver.php?id=<?= $cicloId ?>" class="g-btn g-btn--ghost g-btn--sm">
                        Ver ciclo completo <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="g-card__body">
                    <?php if (!$ultimas): ?>
                        <div class="p-5 text-center text-secondary">
                            <i class="bi bi-ticket-perforated fs-1 d-block mb-3 text-muted"></i>
                            Todavía no hay jugadas cargadas para este sábado.
                        </div>
                    <?php else: ?>
                        <?php foreach ($ultimas as $jugada): ?>
                            <div class="g-list-item">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                                    <div class="g-list-item__title">
                                        <?= e($jugada['cliente_nombre']) ?>
                                        <span class="text-muted fw-normal fs-7 ms-1">(N° <?= e($jugada['nro_cliente']) ?>)</span>
                                    </div>
                                    <div class="g-badge g-badge--blue">
                                        <?= e(formatPesos($jugada['importe'])) ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                                    <div class="bolillas">
                                        <?php foreach ($jugada['numeros'] as $numero): ?>
                                            <span class="bolilla"><?= e(num2($numero)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="g-list-item__meta">
                                        <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Columna Derecha: Acciones y Turnos -->
        <div class="col-12 col-lg-4">

            <!-- Acciones Rápidas -->
            <div class="g-card mb-4 g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-lightning-charge"></i>
                        Acciones Sábados
                    </h3>
                </div>
                <div class="g-card__body p-3">
                    <div class="d-grid gap-2">
                        <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>" class="g-quick-action">
                            <div class="g-quick-action__icon bg-primary-subtle text-primary">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            <div>
                                <div>Cargar Jugada Sábado</div>
                                <div class="small text-muted fw-normal">Modalidad 5 números</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/admin/sabados/sorteo_nuevo.php" class="g-quick-action">
                            <div class="g-quick-action__icon bg-warning-subtle text-warning">
                                <i class="bi bi-dice-5"></i>
                            </div>
                            <div>
                                <div>Cargar Turno</div>
                                <div class="small text-muted fw-normal">Extracto de turno</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="g-quick-action">
                            <div class="g-quick-action__icon bg-info-subtle text-info">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                            <div>
                                <div>Historial de Sábados</div>
                                <div class="small text-muted fw-normal">Ver ediciones anteriores</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Turnos Cargados -->
            <div class="g-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-calendar-check"></i>
                        Turnos del Sábado
                    </h3>
                    <span class="g-badge g-badge--info"><?= count($turnos) ?>/5 turnos</span>
                </div>
                <div class="g-card__body p-3">
                    <?php if (empty($turnos)): ?>
                        <div class="text-center py-3 text-muted small">
                            Aún no se cargaron turnos para este sábado.
                        </div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                            <?php foreach ($turnos as $t): ?>
                                <li class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <span class="fw-semibold small">Turno <?= e(formatFecha($t['fecha'])) ?></span>
                                    </div>
                                    <span class="small text-muted">20 números</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
