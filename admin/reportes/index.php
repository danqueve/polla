<?php
/**
 * Tablero de reportes.
 *
 * Una sola pantalla para los dos roles, pero con vistas distintas: el
 * admin ve el negocio entero con su propio detalle (recaudado, pozo,
 * gastos, clientes, sorteos, barras por ciclo, accesos a jugadas /
 * ganadores / auditoria). El supervisor ve el total recaudado del
 * negocio (no "lo suyo") junto a un desglose de cuanto va cargando
 * cada usuario del staff, y nada mas -sin detalle de jugadas ni de
 * ganadores, que quedan reservados al admin en sus propias pantallas.
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

// Escala de las barras: el ciclo que mas recaudo del conjunto mostrado.
$tope = 0.0;
foreach ($porCiclo as $c) {
    $tope = max($tope, (float) $c['recaudado']);
}

// Fase: un supervisor no ve "lo suyo" sino el total del negocio y como
// viene cargando cada uno -para eso, recaudadoGlobal()/recaudacionPorUsuario()
// ignoran el alcance a proposito (ver ReporteService).
$global     = null;
$porUsuario = [];
if (!$alcance->esAdmin()) {
    $global     = $reporte->recaudadoGlobal($filtro);
    $porUsuario = $reporte->recaudacionPorUsuario($filtro);
}

$qs = $filtro->comoQueryString();

$pageTitle  = 'Reportes · ' . APP_NAME;
$navSeccion = 'reportes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
        <h1 class="pantalla__titulo">Reportes</h1>
    </div>
    <p class="mb-3">
        <span class="chip-alcance">
            <i class="bi bi-<?= $alcance->esAdmin() ? 'globe2' : 'person' ?>"></i>
            <?= $alcance->esAdmin() ? e($alcance->rotulo()) : 'Total del negocio · por usuario' ?>
        </span>
    </p>

    <?php
    $accion = APP_URL . '/admin/reportes/index.php';
    require __DIR__ . '/../../includes/reporte_filtros.php';
    ?>

    <!-- ── Tarjetas resumen ───────────────────────────────── -->

    <?php if ($alcance->esAdmin()): ?>

        <span class="rotulo d-block mb-2">Recaudación<?= $filtro->hayAlguno() ? ' del período filtrado' : ' histórica' ?></span>

        <div class="row g-2 mb-2">
            <div class="col-12">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                    <div class="tarjeta-dato__rotulo">Recaudado en total</div>
                    <div class="tarjeta-dato__pie">
                        <?= (int) $resumen['jugadas'] ?> jugadas ·
                        <?= (int) $resumen['clientes'] ?> clientes ·
                        <?= (int) $resumen['ciclos'] ?> ciclos
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= e(formatPesos($resumen['al_pozo'])) ?></div>
                    <div class="tarjeta-dato__rotulo">Al pozo (60%)</div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor" style="color:var(--oro)">
                        <?= e(formatPesos($resumen['a_gastos'])) ?>
                    </div>
                    <div class="tarjeta-dato__rotulo">Gastos y ganancias (40%)</div>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-4">
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= $clientes ?></div>
                    <div class="tarjeta-dato__rotulo">Clientes dados de alta</div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= $sorteos ?></div>
                    <div class="tarjeta-dato__rotulo">Sorteos cargados</div>
                </div>
            </div>
        </div>

    <?php else: ?>

        <span class="rotulo d-block mb-2">Recaudación<?= $filtro->hayAlguno() ? ' del período filtrado' : ' histórica' ?></span>

        <div class="row g-2 mb-4">
            <div class="col-12">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= e(formatPesos($global['recaudado'])) ?></div>
                    <div class="tarjeta-dato__rotulo">Recaudado en total</div>
                    <div class="tarjeta-dato__pie"><?= (int) $global['jugadas'] ?> jugadas de todo el staff</div>
                </div>
            </div>
        </div>

        <span class="rotulo d-block mb-2">Cuánto va cargando cada uno</span>

        <div class="mb-4">
            <?php foreach ($porUsuario as $u): ?>
                <?php $esVos = (int) $u['id'] === (int) $usuario['id']; ?>
                <div class="fila">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <p class="fila__titulo">
                                <?= e($u['nombre']) ?>
                                <?= $esVos ? '<span class="etiqueta etiqueta--verde">Vos</span>' : '' ?>
                                <?php if (!(int) $u['activo']): ?>
                                    <span class="etiqueta etiqueta--gris">Inactivo</span>
                                <?php endif; ?>
                            </p>
                            <p class="fila__meta">
                                <?= e(UsuarioService::ROLES[$u['rol']] ?? $u['rol']) ?>
                                · <?= (int) $u['jugadas'] ?> <?= (int) $u['jugadas'] === 1 ? 'jugada' : 'jugadas' ?>
                            </p>
                        </div>
                        <div class="cifra fw-bold text-nowrap"><?= e(formatPesos($u['recaudado'])) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <?php if ($alcance->esAdmin()): ?>

        <!-- ── Barras por ciclo ───────────────────────────── -->

        <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
            <span class="rotulo">Recaudación por semana</span>
            <?php if ($tope > 0): ?>
                <span class="fila__meta">máx <?= e(formatPesos($tope)) ?></span>
            <?php endif; ?>
        </div>

        <?php if (!$porCiclo): ?>
            <div class="vacio tarjeta mb-4">
                <i class="bi bi-bar-chart" aria-hidden="true"></i>
                No hay jugadas que entren en este filtro.
            </div>
        <?php else: ?>
            <div class="mb-4">
                <?php foreach ($porCiclo as $c): ?>
                    <?php
                    $recaudado = (float) $c['recaudado'];
                    $ancho     = $tope > 0 ? max(1.5, $recaudado / $tope * 100) : 0;
                    $conGanador = $c['estado'] === CicloService::ESTADO_CON_GANADOR;
                    $abierto    = $c['estado'] === CicloService::ESTADO_ABIERTO;
                    ?>
                    <a class="barra-ciclo"
                       href="<?= APP_URL ?>/admin/reportes/jugadas.php?ciclo=<?= (int) $c['id'] ?>">

                        <div class="d-flex justify-content-between align-items-baseline gap-2">
                            <span class="fw-semibold">
                                Ciclo <?= (int) $c['numero'] ?>
                                <span class="fw-normal text-secondary">
                                    · <?= e(CicloService::rotulo($c)) ?>
                                </span>
                            </span>
                            <span class="barra-ciclo__cifra"><?= e(formatPesos($recaudado)) ?></span>
                        </div>

                        <div class="barra-ciclo__pista">
                            <div class="barra-ciclo__dato <?= $abierto || $conGanador ? '' : 'barra-ciclo__dato--tenue' ?>"
                                 style="width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                            <span class="fila__meta">
                                <?= (int) $c['jugadas'] ?> jugadas
                                <?php if ((float) $c['monto_arrastrado'] > 0): ?>
                                    · arrastró <?= e(formatPesos($c['monto_arrastrado'])) ?>
                                <?php endif; ?>
                            </span>

                            <?php if ($abierto): ?>
                                <span class="etiqueta etiqueta--verde">Abierto</span>
                            <?php elseif ($conGanador): ?>
                                <span class="etiqueta etiqueta--oro">
                                    <i class="bi bi-trophy-fill"></i>
                                    <?= (int) $c['ganadores'] ?>
                                </span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta--gris">Sin ganador</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ── Accesos al detalle ─────────────────────────── -->

        <div class="d-flex flex-column gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/jugadas.php<?= $qs ? '?' . e($qs) : '' ?>"
               class="btn btn-outline-secondary w-100">
                <i class="bi bi-ticket-perforated"></i> Detalle de jugadas
            </a>
            <a href="<?= APP_URL ?>/admin/reportes/ganadores.php<?= $qs ? '?' . e($qs) : '' ?>"
               class="btn btn-outline-secondary w-100">
                <i class="bi bi-trophy"></i> Ganadores
            </a>
            <a href="<?= APP_URL ?>/admin/reportes/auditoria.php<?= $qs ? '?' . e($qs) : '' ?>"
               class="btn btn-outline-secondary w-100">
                <i class="bi bi-clock-history"></i> Auditoría: quién cargó qué
            </a>
        </div>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
