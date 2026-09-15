<?php
/**
 * Resumen de un ciclo con diseño Gentelella: pozo, jugadas, cotejos, ganadores y desglose.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PortalService;
use Polla\Services\PozoService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ciclo = $id > 0 ? $ciclos->buscarPorId($id) : $ciclos->obtenerCicloActivo();

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/ciclos/index.php');
    exit;
}

$cicloId   = (int) $ciclo['id'];
$resumen   = $ciclos->resumen($cicloId);
$lista     = $sorteos->listarPorCiclo($cicloId);
$ganadores = $sorteos->ganadoresDeCiclo($cicloId);
$jugadas   = JugadaService::crearDesde($db)->listarPorCiclo($cicloId);

$abierto      = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;
$conGanador   = $ciclo['estado'] === CicloService::ESTADO_CON_GANADOR;
$esProgramado = $ciclo['estado'] === CicloService::ESTADO_PROGRAMADO;
$arrastre     = (float) ($ciclo['monto_arrastrado'] ?? 0);
$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);

if ($abierto) {
    $premioBase   = (new ParametroService($db))->premioBase();
    $pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
    $pisoAplicado = $premioBase;
} elseif ($conGanador) {
    $pozoMostrado = (float) ($ciclo['monto_pagado'] ?? 0);
    $pisoAplicado = $ciclo['monto_piso_aplicado'] !== null ? (float) $ciclo['monto_piso_aplicado'] : 0.0;
} else {
    $pozoMostrado = $pozoReal;
    $pisoAplicado = 0.0;
}
$subsidio = max(0.0, $pisoAplicado - $pozoReal);
$salidos  = PortalService::numerosSalidos($lista);

$pageTitle        = 'Ciclo ' . (int) $ciclo['numero'] . ' · ' . APP_NAME;
$navSeccion       = 'ciclos';
$pageSectionTitle = 'Detalle de Ciclo';
$breadcrumb       = [
    ['label' => 'Ciclos', 'url' => APP_URL . '/admin/ciclos/index.php'],
    ['label' => 'Ciclo ' . (int) $ciclo['numero'], 'url' => '']
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
                <span class="g-badge g-badge--blue">Ciclo <?= (int) $ciclo['numero'] ?></span>
                <?php if ($esProgramado): ?>
                    <span class="g-badge g-badge--info">
                        <i class="bi bi-clock me-1"></i> Próxima Semana (En Formación)
                    </span>
                <?php elseif ($abierto): ?>
                    <span class="g-badge g-badge--success">Ciclo Abierto</span>
                <?php elseif ($conGanador): ?>
                    <span class="g-badge g-badge--warning">
                        <i class="bi bi-trophy-fill me-1"></i> Finalizado con Ganador
                    </span>
                <?php else: ?>
                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Cerrado Sin Ganador</span>
                <?php endif; ?>
            </div>
            <h1 class="g-page-title mt-2"><?= e(CicloService::rotulo($ciclo)) ?></h1>
            <p class="g-page-subtitle">
                Desde <?= e(formatFecha($ciclo['fecha_inicio'])) ?> hasta <?= e(formatFecha($ciclo['fecha_fin'])) ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/ciclos/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Todos los Ciclos
            </a>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="row g-4">

        <!-- Columna Izquierda: Pozo y Desglose -->
        <div class="col-12 col-lg-8">

            <!-- Hero Card Pozo -->
            <div class="g-hero g-animate g-animate-delay-1">
                <div class="g-hero__label">
                    <i class="bi bi-trophy-fill me-1"></i>
                    <?= $abierto ? 'Pozo Acumulado Estimado' : ($conGanador ? 'Premio Total Repartido' : 'Pozo al Cierre') ?>
                </div>
                <div class="g-hero__amount">
                    <?= e(formatPesos($pozoMostrado)) ?>
                </div>

                <div class="g-hero__detail">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php if ($arrastre > 0): ?>
                        Incluye <?= e(formatPesos($arrastre)) ?> arrastrados del ciclo anterior.
                    <?php else: ?>
                        Acumulado por porcentaje de jugadas de la semana.
                    <?php endif; ?>
                </div>

                <?php if ((float) ($ciclo['monto_pagado'] ?? 0) > 0): ?>
                    <div class="g-hero__subsidy mt-2">
                        <i class="bi bi-check-circle-fill text-success"></i>
                        <span>Liquidado el <?= e(formatFechaHora($ciclo['fecha_liquidacion'])) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isAdmin() && $subsidio > 0): ?>
                    <div class="g-hero__subsidy mt-2">
                        <i class="bi bi-shield-check"></i>
                        <span><?= e(formatPesos($pozoReal)) ?> de recaudación real · Decena de Oro subsidia <?= e(formatPesos($subsidio)) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Métricas Stats -->
            <div class="g-stat-grid g-animate g-animate-delay-2">
                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--blue">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <div class="g-stat__value"><?= (int) $resumen['jugadas_total'] ?></div>
                    <div class="g-stat__label">Jugadas Cargadas</div>
                </div>

                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--purple">
                        <i class="bi bi-dice-5"></i>
                    </div>
                    <div class="g-stat__value"><?= count($lista) ?> / 5</div>
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

            <!-- Ganadores si los hubo -->
            <?php if ($ganadores): ?>
                <div class="g-card mb-4 g-animate g-animate-delay-2 border-warning">
                    <div class="g-card__header bg-warning-subtle">
                        <h2 class="g-card__title text-warning-emphasis">
                            <i class="bi bi-trophy-fill me-1"></i>
                            <?= count($ganadores) === 1 ? '¡Ganador Consagrado!' : '¡' . count($ganadores) . ' Ganadores Consagrados!' ?>
                        </h2>
                    </div>
                    <div class="g-card__body">
                        <?php foreach ($ganadores as $ganador): ?>
                            <div class="p-3 mb-2 rounded" style="background:#fffbeb;border:1px solid #fef3c7">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h3 class="fw-bold mb-1 fs-5 text-dark"><?= e($ganador['cliente_nombre']) ?></h3>
                                        <div class="text-muted small">
                                            Cliente N° <?= e($ganador['nro_cliente']) ?>
                                            <?php if ($ganador['telefono']): ?>
                                                · Tel: <?= e($ganador['telefono']) ?>
                                            <?php endif; ?>
                                            · Sorteo del <?= e(formatFecha($ganador['sorteo_fecha'])) ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <div class="small text-muted">Premio Ganado:</div>
                                        <div class="fs-3 fw-extrabold text-success"><?= e(formatPesos($ganador['monto_premio'])) ?></div>
                                    </div>
                                </div>
                                <div class="bolillas mt-3">
                                    <?php foreach ($ganador['numeros'] as $numero): ?>
                                        <span class="bolilla bolilla--acertada"><?= e(num2($numero)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Jugadas del ciclo -->
            <div class="g-card g-list-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-ticket-perforated"></i>
                        Jugadas del Ciclo (<?= count($jugadas) ?>)
                    </h2>
                    <span class="small text-muted">
                        <span class="bolilla bolilla--acertada d-inline-block" style="width:16px;height:16px;font-size:10px;line-height:16px"></span>
                        = número ya sorteado
                    </span>
                </div>

                <div class="g-card__body">
                    <?php if (!$jugadas): ?>
                        <div class="p-5 text-center text-secondary">
                            <i class="bi bi-ticket-perforated fs-1 d-block mb-3 text-muted"></i>
                            No hay jugadas cargadas en este ciclo.
                        </div>
                    <?php else: ?>
                        <?php foreach ($jugadas as $jugada): ?>
                            <?php
                            $aciertos = 0;
                            foreach ($jugada['numeros'] as $numero) {
                                if (isset($salidos[$numero])) {
                                    $aciertos++;
                                }
                            }
                            ?>
                            <div class="g-list-item">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <div>
                                        <h3 class="g-list-item__title">
                                            <?= e($jugada['cliente_nombre']) ?>
                                            <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($jugada['nro_cliente']) ?></span>
                                        </h3>
                                        <div class="g-list-item__meta mt-1">
                                            <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <?php if ($jugada['estado'] === 'ganadora'): ?>
                                            <span class="g-badge g-badge--warning">Ganadora</span>
                                        <?php elseif ($jugada['estado'] === 'perdedora'): ?>
                                            <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                                        <?php else: ?>
                                            <span class="g-badge g-badge--success">Activa</span>
                                        <?php endif; ?>
                                        <div class="small fw-semibold text-primary mt-1">
                                            <?= $aciertos ?> / <?= count($jugada['numeros']) ?> aciertos
                                        </div>
                                    </div>
                                </div>

                                <div class="bolillas mt-2">
                                    <?php foreach ($jugada['numeros'] as $numero): ?>
                                        <span class="bolilla <?= isset($salidos[$numero]) ? 'bolilla--acertada' : '' ?>">
                                            <?= e(num2($numero)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Columna Derecha: Desglose Admin y Sorteos -->
        <div class="col-12 col-lg-4">

            <!-- Desglose de Pozo (Admin) -->
            <?php if (isAdmin() && ($abierto || $conGanador)): ?>
                <div class="g-card mb-4 g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-calculator me-1"></i>
                            Desglose de Liquidación
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Ventas brutas acumuladas:</span>
                            <span class="fw-semibold text-dark"><?= e(formatPesos($pozoReal)) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Piso base garantizado:</span>
                            <span class="fw-semibold text-dark"><?= e(formatPesos($pisoAplicado)) ?></span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fw-bold mb-2">
                            <span><?= $conGanador ? 'Monto pagado:' : 'Pozo a pagar:' ?></span>
                            <span class="text-primary fs-5"><?= e(formatPesos($pozoMostrado)) ?></span>
                        </div>
                        <?php if ($subsidio > 0): ?>
                            <div class="d-flex justify-content-between small text-danger fw-semibold bg-danger-subtle p-2 rounded">
                                <span>Subsidio cubierto:</span>
                                <span><?= e(formatPesos($subsidio)) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Extractos de la Semana -->
            <div class="g-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-calendar-check me-1"></i>
                        Sorteos Semanales
                    </h3>
                    <span class="g-badge g-badge--info"><?= count($lista) ?>/5</span>
                </div>
                <div class="g-card__body">
                    <?php if (!$lista): ?>
                        <div class="text-center py-3 text-muted small">
                            Aún no se registraron extractos en este ciclo.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($lista as $sorteo): ?>
                                <div class="p-2 rounded border" style="background:#f9fafb">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold small">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            <?= e(formatFechaDia($sorteo['fecha'])) ?>
                                        </span>
                                        <?php if ((int) $sorteo['ganadores_total'] > 0): ?>
                                            <span class="g-badge g-badge--warning">Cortó ciclo</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bolillas">
                                        <?php foreach ($sorteo['numeros'] as $numero): ?>
                                            <span class="bolilla" style="width:26px;height:26px;font-size:.7rem;line-height:26px"><?= e(num2($numero)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
