<?php
/**
 * Tablero: estado del ciclo abierto, pozo acumulado, métricas y accesos rápidos.
 */
require_once __DIR__ . '/../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PozoService;
use Polla\Services\SolicitudService;
use Polla\Services\SorteoService;

requireLogin();

$db          = getPDO();
$ciclos      = new CicloService($db);
$parametros  = new ParametroService($db);
$jugadas     = JugadaService::crearDesde($db);
$sorteos     = SorteoService::crearDesde($db);
$solicitudes = SolicitudService::crearDesde($db);

$solicitudesPendientes = $solicitudes->contarPendientes();

$ciclo   = $ciclos->obtenerCicloActivo();
$cicloId = (int) $ciclo['id'];
$resumen = $ciclos->resumen($cicloId);
// Acotado a la semana en juego, mismo criterio que el tablero de
// sabados: sin el ciclo traia las ultimas de toda la historia, que ya
// no compiten en el sorteo que viene.
$ultimas = $jugadas->ultimas(5, CicloService::TIPO_SEMANAL, $cicloId);

$extractos = $sorteos->listarPorCiclo($cicloId);
$arrastre  = (float) ($ciclo['monto_arrastrado'] ?? 0);

$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);
$premioBase   = $parametros->premioBase();
$pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
// El piso se suma siempre, no cubre un faltante: el aporte de la
// empresa es el premio base completo, no un gap variable.
$subsidio     = $premioBase;

// Dias habiles del ciclo ya pasados que todavia no tienen extracto
$sinCargar = 0;
$fechasHechas = array_column($extractos, 'fecha');
$dia = new DateTimeImmutable($ciclo['fecha_inicio']);
$fin = new DateTimeImmutable($ciclo['fecha_fin']);
$hoy = new DateTimeImmutable('today');
while ($dia <= $fin) {
    if ($dia <= $hoy && !in_array($dia->format('Y-m-d'), $fechasHechas, true)) {
        $sinCargar++;
    }
    $dia = $dia->modify('+1 day');
}

$pageTitle        = 'Tablero · ' . APP_NAME;
$navSeccion       = 'tablero';
$pageSectionTitle = 'Tablero Principal';
$breadcrumb       = [
    ['label' => 'Tablero', 'url' => '']
];

require __DIR__ . '/../includes/admin_head.php';
require __DIR__ . '/../includes/admin_sidebar.php';
require __DIR__ . '/../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue">Ciclo <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge g-badge--success">Abierto</span>
            </div>
            <h1 class="g-page-title mt-2"><?= e(CicloService::rotulo($ciclo)) ?></h1>
            <p class="g-page-subtitle">
                Desde el <?= e(formatFecha($ciclo['fecha_inicio'])) ?> hasta el <?= e(formatFecha($ciclo['fecha_fin'])) ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-btn g-btn--primary">
                <i class="bi bi-plus-lg"></i> Cargar Jugada
            </a>
            <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="g-btn g-btn--outline">
                <i class="bi bi-dice-5"></i> Cargar Sorteo
            </a>
        </div>
    </div>

    <!-- Alertas si corresponden -->
    <?php if ($solicitudesPendientes > 0): ?>
        <a href="<?= APP_URL ?>/admin/solicitudes/index.php" class="g-alert-banner g-alert-banner--warning g-animate g-animate-delay-1">
            <i class="bi bi-hourglass-split"></i>
            <div>
                <strong><?= $solicitudesPendientes ?></strong>
                <?= $solicitudesPendientes === 1 ? 'solicitud de pago pendiente de aprobación.' : 'solicitudes de pago pendientes de aprobación.' ?>
            </div>
            <i class="bi bi-chevron-right g-alert-banner__arrow"></i>
        </a>
    <?php endif; ?>

    <?php if ($sinCargar > 0): ?>
        <div class="g-alert-banner g-alert-banner--info g-animate g-animate-delay-1">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <?= $sinCargar === 1
                    ? 'Falta cargar el extracto de un sorteo de esta semana.'
                    : "Faltan cargar los extractos de $sinCargar sorteos de esta semana." ?>
                Hasta que no se carguen, no se cotejan las jugadas.
            </div>
        </div>
    <?php endif; ?>

    <!-- Layout Grid: Columna Principal + Columna Lateral -->
    <div class="row g-4">

        <!-- Columna Izquierda / Principal -->
        <div class="col-12 col-lg-8">

            <!-- Hero Card: Pozo Acumulado -->
            <div class="g-hero g-animate g-animate-delay-1">
                <div class="g-hero__label">
                    <i class="bi bi-trophy-fill me-1"></i> Pozo Acumulado Estimado
                </div>
                <div class="g-hero__amount">
                    <?= e(formatPesos($pozoMostrado)) ?>
                </div>

                <div class="g-hero__detail">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php if ($arrastre > 0): ?>
                        Incluye <?= e(formatPesos($arrastre)) ?> que arrastró de la semana anterior
                    <?php else: ?>
                        <?= (int) $parametros->porcentajePozo() ?>% de cada jugada pagada de esta semana
                    <?php endif; ?>
                </div>

                <div class="g-hero__subsidy">
                    <i class="bi bi-shield-check"></i>
                    <span>De los cuales <?= e(formatPesos($pozoReal)) ?> son recaudación real · Decena de Oro suma <?= e(formatPesos($subsidio)) ?> de piso garantizado</span>
                </div>
            </div>

            <!-- Métricas Grid (Stats) -->
            <div class="g-stat-grid g-animate g-animate-delay-2">
                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--blue">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="g-stat__value"><?= (int) $resumen['jugadas_total'] ?></div>
                    <div class="g-stat__label">Jugadas Totales</div>
                </div>

                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--purple">
                        <i class="bi bi-dice-5"></i>
                    </div>
                    <div class="g-stat__value"><?= count($extractos) ?> / 5</div>
                    <div class="g-stat__label">Sorteos Realizados</div>
                </div>

                <div class="g-stat g-stat--accent">
                    <div class="g-stat__icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="g-stat__value"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                    <div class="g-stat__label">Total Recaudado</div>
                </div>
            </div>

            <!-- Card: Últimas Jugadas -->
            <div class="g-card g-list-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-clock-history"></i>
                        Últimas Jugadas Cargadas
                    </h2>
                    <a href="<?= APP_URL ?>/admin/jugadas/index.php" class="g-btn g-btn--ghost g-btn--sm">
                        Ver todas <i class="bi bi-arrow-right"></i>
                    </a>
                </div>

                <div class="g-card__body">
                    <?php if (!$ultimas): ?>
                        <div class="p-4 text-center text-secondary">
                            <i class="bi bi-inbox fs-2 d-block mb-2 text-muted"></i>
                            Todavía no hay jugadas registradas en este ciclo.
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

        <!-- Columna Derecha / Accesos y Resumen -->
        <div class="col-12 col-lg-4">

            <!-- Acciones Rápidas -->
            <div class="g-card mb-4 g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-lightning-charge"></i>
                        Acciones Rápidas
                    </h3>
                </div>
                <div class="g-card__body p-3">
                    <div class="d-grid gap-2">
                        <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-quick-action">
                            <div class="g-quick-action__icon bg-primary-subtle text-primary">
                                <i class="bi bi-plus-circle"></i>
                            </div>
                            <div>
                                <div>Cargar Jugada</div>
                                <div class="small text-muted fw-normal">Registrar cliente y números</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="g-quick-action">
                            <div class="g-quick-action__icon bg-warning-subtle text-warning">
                                <i class="bi bi-dice-5"></i>
                            </div>
                            <div>
                                <div>Cargar Extracto</div>
                                <div class="small text-muted fw-normal">Sorteo Nocturna diario</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/admin/ciclos/ver.php?id=<?= $cicloId ?>" class="g-quick-action">
                            <div class="g-quick-action__icon bg-info-subtle text-info">
                                <i class="bi bi-clipboard-data"></i>
                            </div>
                            <div>
                                <div>Resumen del Ciclo</div>
                                <div class="small text-muted fw-normal">Estadísticas y cotejos</div>
                            </div>
                        </a>

                        <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-quick-action">
                            <div class="g-quick-action__icon bg-success-subtle text-success">
                                <i class="bi bi-bar-chart-line"></i>
                            </div>
                            <div>
                                <div><?= isAdmin() ? 'Reportes de Recaudación' : 'Mis Reportes' ?></div>
                                <div class="small text-muted fw-normal">Métricas y exportación</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sorteos de la Semana -->
            <div class="g-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-calendar-check"></i>
                        Sorteos de la Semana
                    </h3>
                    <span class="g-badge g-badge--info"><?= count($extractos) ?>/5 cargados</span>
                </div>
                <div class="g-card__body p-3">
                    <?php if (empty($extractos)): ?>
                        <div class="text-center py-3 text-muted small">
                            Aún no se cargaron extractos para esta semana.
                        </div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                            <?php foreach ($extractos as $ext): ?>
                                <li class="d-flex justify-content-between align-items-center p-2 rounded" style="background:#f9fafb;border:1px solid #f3f4f6">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-check-circle-fill text-success"></i>
                                        <span class="fw-semibold small"><?= e(formatFecha($ext['fecha'])) ?></span>
                                    </div>
                                    <span class="small text-muted">Nocturna</span>
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
require __DIR__ . '/../includes/admin_foot.php';
