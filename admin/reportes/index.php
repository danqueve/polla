<?php
/**
 * Tablero de reportes con diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ReporteService;
use Polla\Services\UsuarioService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireLogin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$resumen  = $reporte->resumen($filtro);
$porCiclo = $reporte->porCiclo($filtro);
$sorteos  = $reporte->totalSorteos($filtro);
$clientes = $reporte->totalClientes($filtro);

$tope = 0.0;
foreach ($porCiclo as $c) {
    $tope = max($tope, (float) $c['recaudado']);
}

$global     = null;
$porUsuario = [];
if (!$alcance->esAdmin()) {
    $global     = $reporte->recaudadoGlobal($filtro);
    $porUsuario = $reporte->recaudacionPorUsuario($filtro);
}

$qs = $filtro->comoQueryString();

$pageTitle        = 'Reportes · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Centro de Reportes y Métricas';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => '']
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
                <span class="g-badge g-badge--blue">
                    <i class="bi bi-<?= $alcance->esAdmin() ? 'globe2' : 'person' ?> me-1"></i>
                    <?= $alcance->esAdmin() ? e($alcance->rotulo()) : 'Total del negocio · Staff' ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2">Métricas y Rendimiento</h1>
            <p class="g-page-subtitle">Recaudación global, pozos, comisiones y estadísticas de juego</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <?php if ($alcance->esAdmin()): ?>
                <a href="<?= APP_URL ?>/admin/reportes/jugadas.php<?= $qs ? '?' . e($qs) : '' ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-ticket-perforated"></i> Detalle Jugadas
                </a>
                <a href="<?= APP_URL ?>/admin/reportes/ganadores.php<?= $qs ? '?' . e($qs) : '' ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-trophy"></i> Ganadores
                </a>
                <a href="<?= APP_URL ?>/admin/reportes/auditoria.php<?= $qs ? '?' . e($qs) : '' ?>" class="g-btn g-btn--outline">
                    <i class="bi bi-clock-history"></i> Auditoría
                </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/reportes/numeros.php<?= $qs ? '?' . e($qs) : '' ?>" class="g-btn g-btn--primary">
                <i class="bi bi-grid-3x3-gap"></i> Estadísticas Números
            </a>
        </div>
    </div>

    <!-- Filtros de Período / Búsqueda -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <?php
            $accion = APP_URL . '/admin/reportes/index.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <?php if ($alcance->esAdmin()): ?>

        <!-- Stat Grid Admin -->
        <div class="g-stat-grid g-animate g-animate-delay-2">
            <div class="g-stat g-stat--accent">
                <div class="g-stat__icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="g-stat__value"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                <div class="g-stat__label">Total Recaudado</div>
                <div class="small text-white-50 mt-2">
                    <?= (int) $resumen['jugadas'] ?> jugadas · <?= (int) $resumen['clientes'] ?> clientes
                </div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--blue">
                    <i class="bi bi-trophy"></i>
                </div>
                <div class="g-stat__value"><?= e(formatPesos($resumen['al_pozo'])) ?></div>
                <div class="g-stat__label">Al Pozo Acumulado (60%)</div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--green">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="g-stat__value"><?= e(formatPesos($resumen['a_gastos'])) ?></div>
                <div class="g-stat__label">Gastos / Ganancias (40%)</div>
            </div>

            <div class="g-stat">
                <div class="g-stat__icon g-stat__icon--purple">
                    <i class="bi bi-people"></i>
                </div>
                <div class="g-stat__value"><?= $clientes ?></div>
                <div class="g-stat__label">Clientes Registrados</div>
            </div>
        </div>

        <!-- Recaudación por semana (barras) -->
        <div class="g-card g-list-card g-animate g-animate-delay-3 mb-4">
            <div class="g-card__header">
                <h2 class="g-card__title">
                    <i class="bi bi-bar-chart-fill"></i>
                    Recaudación por Ciclo Semanal
                </h2>
                <?php if ($tope > 0): ?>
                    <span class="g-badge g-badge--info">Pico Máximo: <?= e(formatPesos($tope)) ?></span>
                <?php endif; ?>
            </div>

            <div class="g-card__body">
                <?php if (!$porCiclo): ?>
                    <div class="p-5 text-center text-secondary">
                        <i class="bi bi-bar-chart fs-1 d-block mb-3 text-muted"></i>
                        No hay registros que coincidan con los filtros aplicados.
                    </div>
                <?php else: ?>
                    <?php foreach ($porCiclo as $c): ?>
                        <?php
                        $recaudado = (float) $c['recaudado'];
                        $ancho     = $tope > 0 ? max(2.0, $recaudado / $tope * 100) : 0;
                        $conGanador = $c['estado'] === CicloService::ESTADO_CON_GANADOR;
                        $abierto    = $c['estado'] === CicloService::ESTADO_ABIERTO;
                        ?>
                        <div class="g-list-item">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <a href="<?= APP_URL ?>/admin/reportes/jugadas.php?ciclo=<?= (int) $c['id'] ?>" class="fw-bold text-dark text-decoration-none">
                                    Ciclo <?= (int) $c['numero'] ?>
                                    <span class="text-muted fw-normal fs-7 ms-1">· <?= e(CicloService::rotulo($c)) ?></span>
                                </a>
                                <div class="fw-bold fs-6 text-dark"><?= e(formatPesos($recaudado)) ?></div>
                            </div>

                            <div class="barra-ciclo__pista">
                                <div class="barra-ciclo__dato"
                                     style="background: <?= $abierto ? 'var(--g-primary)' : ($conGanador ? 'var(--g-warning)' : '#9ca3af') ?>; width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center gap-2 mt-2 small text-muted">
                                <span>
                                    <?= (int) $c['jugadas'] ?> jugadas cargadas
                                    <?php if ((float) $c['monto_arrastrado'] > 0): ?>
                                        · <i class="bi bi-arrow-return-right me-1"></i>Arrastró <?= e(formatPesos($c['monto_arrastrado'])) ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ($abierto): ?>
                                    <span class="g-badge g-badge--success">Abierto</span>
                                <?php elseif ($conGanador): ?>
                                    <span class="g-badge g-badge--warning"><i class="bi bi-trophy-fill me-1"></i> <?= (int) $c['ganadores'] ?> Ganadores</span>
                                <?php else: ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Sin Ganador</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <!-- Vista Supervisor -->
        <div class="g-stat-grid g-animate g-animate-delay-2 mb-4">
            <div class="g-stat g-stat--accent">
                <div class="g-stat__icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="g-stat__value"><?= e(formatPesos($global['recaudado'])) ?></div>
                <div class="g-stat__label">Recaudación Total del Negocio</div>
                <div class="small text-white-50 mt-2"><?= (int) $global['jugadas'] ?> jugadas totales del staff</div>
            </div>
        </div>

        <div class="g-card g-list-card g-animate g-animate-delay-3">
            <div class="g-card__header">
                <h2 class="g-card__title">
                    <i class="bi bi-people-fill"></i>
                    Rendimiento por Integrante del Staff
                </h2>
            </div>
            <div class="g-card__body">
                <?php foreach ($porUsuario as $u): ?>
                    <?php $esVos = (int) $u['id'] === (int) $usuario['id']; ?>
                    <div class="g-list-item">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <div>
                                <h3 class="g-list-item__title">
                                    <?= e($u['nombre']) ?>
                                    <?= $esVos ? '<span class="g-badge g-badge--success ms-1">Vos</span>' : '' ?>
                                    <?php if (!(int) $u['activo']): ?>
                                        <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Inactivo</span>
                                    <?php endif; ?>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <?= e(UsuarioService::ROLES[$u['rol']] ?? $u['rol']) ?>
                                    · <?= (int) $u['jugadas'] ?> <?= (int) $u['jugadas'] === 1 ? 'jugada cargada' : 'jugadas cargadas' ?>
                                </div>
                            </div>
                            <div class="fw-bold fs-5 text-dark"><?= e(formatPesos($u['recaudado'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
