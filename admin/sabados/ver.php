<?php
/**
 * Resumen de un ciclo de sábado con diseño Gentelella: pozo, ranking, turnos y jugadas.
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
$ciclo = $id > 0 ? $ciclos->buscarPorId($id) : $ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);

if (!$ciclo || $ciclo['tipo'] !== CicloService::TIPO_SABADO) {
    setFlash('danger', 'Ese sábado no existe.');
    header('Location: ' . APP_URL . '/admin/sabados/ciclos.php');
    exit;
}

$cicloId   = (int) $ciclo['id'];
$resumen   = $ciclos->resumen($cicloId);
$lista     = $sorteos->listarPorCiclo($cicloId);
$ganadores = $sorteos->ganadoresDeCiclo($cicloId);
$jugadasSvc   = JugadaService::crearDesde($db);
$jugadas      = $jugadasSvc->listarPorCiclo($cicloId);
// El total real, no count($jugadas): listarPorCiclo() corta en 200 sin
// avisar, asi que contar la lista mentia en un sabado grande.
$jugadasTotal = $jugadasSvc->contarPorCiclo($cicloId);

$abierto      = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;
$conGanador   = $ciclo['estado'] === CicloService::ESTADO_CON_GANADOR;
$esProgramado = $ciclo['estado'] === CicloService::ESTADO_PROGRAMADO;
$arrastre     = (float) ($ciclo['monto_arrastrado'] ?? 0);
$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);

if ($abierto) {
    $premioBase   = (new ParametroService($db))->premioBaseSabado();
    $pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
    $pisoAplicado = $premioBase;
} elseif ($conGanador) {
    $pozoMostrado = (float) ($ciclo['monto_pagado'] ?? 0);
    $pisoAplicado = $ciclo['monto_piso_aplicado'] !== null ? (float) $ciclo['monto_piso_aplicado'] : 0.0;
} else {
    $pozoMostrado = $pozoReal;
    $pisoAplicado = 0.0;
}
// El piso se suma siempre, no cubre un faltante -- pero para un ciclo
// YA liquidado (rama conGanador), monto_pagado puede venir de un
// pago viejo hecho todavia bajo la regla anterior (el mayor entre
// real y piso, no la suma). Derivar el subsidio como "lo pagado menos
// lo real" -- en vez de asumir directamente $pisoAplicado -- hace que
// el desglose siga sumando exacto (real + subsidio = pozoMostrado)
// sin importar bajo que regla se liquido ese ciclo puntual.
$subsidio = $pozoMostrado - $pozoReal;

$salidos = PortalService::numerosSalidos($lista);
$ranking = PortalService::ranking($jugadas, $salidos);

$pageTitle        = 'Sábado ' . (int) $ciclo['numero'] . ' · ' . APP_NAME;
$navSeccion       = 'sabados';
$pageSectionTitle = 'Detalle de Sábado';
$breadcrumb       = [
    ['label' => 'Sábados', 'url' => APP_URL . '/admin/sabados/index.php'],
    ['label' => 'Sábado ' . (int) $ciclo['numero'], 'url' => '']
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
                <?php if ($esProgramado): ?>
                    <span class="g-badge g-badge--info">
                        <i class="bi bi-clock me-1"></i> Próximo Sábado (En Formación)
                    </span>
                <?php elseif ($abierto): ?>
                    <span class="g-badge g-badge--success">Abierto</span>
                <?php elseif ($conGanador): ?>
                    <span class="g-badge g-badge--warning">
                        <i class="bi bi-trophy-fill me-1"></i> Finalizado con Ganador
                    </span>
                <?php else: ?>
                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Cerrado Sin Ganador</span>
                <?php endif; ?>
            </div>
            <h1 class="g-page-title mt-2"><?= e(CicloService::rotulo($ciclo)) ?></h1>
            <p class="g-page-subtitle">Edición Especial · 5 números por jugada</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Historial de Sábados
            </a>
            <?php if ($abierto): ?>
                <a href="<?= APP_URL ?>/admin/sabados/sorteo_nuevo.php" class="g-btn g-btn--primary">
                    <i class="bi bi-plus-lg"></i> Cargar Turno
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Layout Grid -->
    <div class="row g-4">

        <!-- Columna Izquierda: Pozo y Jugadas -->
        <div class="col-12 col-lg-8">

            <!-- Hero Card Pozo Sábado -->
            <div class="g-hero g-animate g-animate-delay-1">
                <div class="g-hero__label">
                    <i class="bi bi-star-fill me-1"></i>
                    <?= $abierto ? 'Pozo Acumulado de Sábados' : ($conGanador ? 'Premio Total Repartido' : 'Pozo al Cierre') ?>
                </div>
                <div class="g-hero__amount">
                    <?= e(formatPesos($pozoMostrado)) ?>
                </div>

                <div class="g-hero__detail">
                    <i class="bi bi-info-circle me-1"></i>
                    <?php if ($arrastre > 0): ?>
                        Incluye <?= e(formatPesos($arrastre)) ?> arrastrados del sábado anterior.
                    <?php else: ?>
                        Acumulado con el porcentaje de jugadas de este sábado.
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
                        <span><?= e(formatPesos($pozoReal)) ?> reales · Decena de Oro suma <?= e(formatPesos($subsidio)) ?> de piso garantizado</span>
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
                    <div class="g-stat__label">Jugadas Registradas</div>
                </div>

                <div class="g-stat">
                    <div class="g-stat__icon g-stat__icon--purple">
                        <i class="bi bi-dice-5"></i>
                    </div>
                    <div class="g-stat__value"><?= count($lista) ?> / 5</div>
                    <div class="g-stat__label">Turnos Cargados</div>
                </div>

                <div class="g-stat g-stat--accent">
                    <div class="g-stat__icon">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="g-stat__value"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                    <div class="g-stat__label">Total Recaudado</div>
                </div>
            </div>

            <!-- Ganadores -->
            <?php if ($ganadores): ?>
                <div class="g-card mb-4 g-animate g-animate-delay-2 border-warning">
                    <div class="g-card__header bg-warning-subtle">
                        <h2 class="g-card__title text-warning-emphasis">
                            <i class="bi bi-trophy-fill me-1"></i>
                            <?= count($ganadores) === 1 ? '¡Ganador del Sábado!' : '¡' . count($ganadores) . ' Ganadores del Sábado!' ?>
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

            <!-- Ranking en vivo -->
            <div class="g-card g-list-card mb-4 g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-bar-chart-line me-1"></i>
                        Ranking de Aciertos del Sábado
                    </h2>
                </div>
                <div class="g-card__body">
                    <?php if (!$lista): ?>
                        <div class="p-4 text-center text-muted small">
                            Falta cargar el primer turno del sábado para comenzar a computar el ranking.
                        </div>
                    <?php elseif (!$ranking): ?>
                        <div class="p-4 text-center text-muted small">
                            No hay jugadas registradas en este sábado.
                        </div>
                    <?php else: ?>
                        <?php foreach ($ranking as $i => $puesto): ?>
                            <div class="g-list-item">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <span class="fs-5 fw-bold text-secondary" style="width:2rem;text-align:center">
                                            <?= $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : ($i + 1) . '°')) ?>
                                        </span>
                                        <div class="min-w-0">
                                            <h4 class="g-list-item__title mb-0"><?= e($puesto['nombre']) ?></h4>
                                            <div class="g-list-item__meta mb-0">N° <?= e($puesto['nro_cliente']) ?></div>
                                        </div>
                                    </div>
                                    <span class="g-badge <?= $puesto['aciertos'] > 0 ? 'g-badge--success' : '' ?>" style="<?= $puesto['aciertos'] == 0 ? 'background:#e5e7eb;color:#4b5563' : '' ?>">
                                        <?= (int) $puesto['aciertos'] ?> / <?= (int) $puesto['total'] ?> aciertos
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Jugadas del Sábado -->
            <div class="g-card g-list-card g-animate g-animate-delay-3">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-ticket-perforated"></i>
                        Jugadas de Sábado (<?= $jugadasTotal ?>)
                    </h2>
                </div>
                <div class="g-card__body">
                    <?php if (count($jugadas) < $jugadasTotal): ?>
                        <div class="g-alert-banner g-alert-banner--warning m-3 p-3 small">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                Se muestran las <?= count($jugadas) ?> jugadas más recientes
                                de <?= $jugadasTotal ?>. Para verlas todas, usá
                                <a href="<?= APP_URL ?>/admin/reportes/jugadas.php">Reportes</a>.
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$jugadas): ?>
                        <div class="p-5 text-center text-secondary">
                            <i class="bi bi-ticket-perforated fs-1 d-block mb-3 text-muted"></i>
                            No hay jugadas cargadas en este sábado.
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
                                            <?= $aciertos ?> / <?= count($jugada['numeros']) ?> salidos
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

        <!-- Columna Derecha: Turnos y Desglose -->
        <div class="col-12 col-lg-4">

            <!-- Turnos Cargados -->
            <div class="g-card mb-4 g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <i class="bi bi-calendar-check me-1"></i>
                        Turnos del Sábado
                    </h3>
                    <span class="g-badge g-badge--info"><?= count($lista) ?>/5</span>
                </div>
                <div class="g-card__body">
                    <?php if (!$lista): ?>
                        <div class="text-center py-3 text-muted small">
                            Aún no se registró ningún turno.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($lista as $sorteo): ?>
                                <div class="p-2 rounded border" style="background:#f9fafb">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fw-semibold small">
                                            <i class="bi bi-check-circle-fill text-success me-1"></i>
                                            Turno <?= (int) $sorteo['turno'] ?> de 5
                                        </span>
                                        <?php if (isAdmin()): ?>
                                            <div class="d-flex gap-1">
                                                <a href="<?= APP_URL ?>/admin/sabados/sorteo_editar.php?id=<?= (int) $sorteo['id'] ?>"
                                                   class="g-btn g-btn--outline g-btn--sm py-0 px-2" title="Editar turno">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bolillas">
                                        <?php foreach ($sorteo['numeros'] as $numero): ?>
                                            <span class="bolilla bolilla--chica" style="width:26px;height:26px;font-size:.7rem;line-height:26px"><?= e(num2($numero)) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Desglose de Pozo (Admin) -->
            <?php if (isAdmin() && ($abierto || $conGanador)): ?>
                <div class="g-card g-animate g-animate-delay-3">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-calculator me-1"></i>
                            Desglose de Liquidación
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="d-flex justify-content-between small text-muted mb-2">
                            <span>Ventas reales acumuladas:</span>
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
                                <span>Aporte de la empresa:</span>
                                <span><?= e(formatPesos($subsidio)) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
