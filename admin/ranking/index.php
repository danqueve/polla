<?php
/**
 * Ranking de aciertos, semanal y sábado con pestañas, por ciclo (el
 * activo por defecto, pero se puede elegir uno ya cerrado).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\PortalService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

// El ?ciclo= manda si viene: ciclos.id es unico entre las dos cajas y
// ya trae su propio tipo, asi que el ciclo define la pestaña activa.
$cicloId = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
if ($cicloId > 0) {
    $ciclo = $ciclos->buscarPorId($cicloId);
    if (!$ciclo) {
        setFlash('danger', 'Ese ciclo no existe.');
        header('Location: ' . APP_URL . '/admin/ranking/index.php');
        exit;
    }
    $tipo = $ciclo['tipo'];
} else {
    $tipo  = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS) ? $_GET['tipo'] : CicloService::TIPO_SEMANAL;
    $ciclo = $ciclos->obtenerCicloActivo($tipo);
}

$cicloId  = (int) $ciclo['id'];
$esSabado = $tipo === CicloService::TIPO_SABADO;
$abierto  = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;

$listaCiclos = $ciclos->listar(20, $tipo);
$lista       = $sorteos->listarPorCiclo($cicloId);

// Limite explicito: el default de listarPorCiclo() es 200 y un ciclo
// con mas jugadas que eso truncaria el ranking en silencio. Y se
// descartan las anuladas antes de rankear -- listarPorCiclo() no lo
// hace, y una jugada anulada no deberia competir.
$jugadas = array_values(array_filter(
    JugadaService::crearDesde($db)->listarPorCiclo($cicloId, '', 5000),
    static fn($j) => $j['estado'] !== 'anulada'
));

$salidos = PortalService::numerosSalidos($lista);
$ranking = PortalService::ranking($jugadas, $salidos);

$pageTitle        = 'Ranking · ' . APP_NAME;
$navSeccion       = 'ranking';
$pageSectionTitle = 'Ranking de Aciertos';
$breadcrumb       = [
    ['label' => 'Ranking', 'url' => ''],
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
                <span class="g-badge <?= $abierto ? 'g-badge--success' : 'g-badge--info' ?>">
                    <?= $abierto ? 'Abierto' : e(CicloService::ESTADOS[$ciclo['estado']] ?? $ciclo['estado']) ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2">Ranking de Aciertos</h1>
            <p class="g-page-subtitle">Quién va anotando más contra los sorteos ya cargados de <?= e(CicloService::rotulo($ciclo)) ?></p>
        </div>
    </div>

    <!-- Pestañas Semana / Sábado -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= !$esSabado ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/ranking/index.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">
                        <i class="bi bi-calendar-week me-1"></i> Semana
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $esSabado ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/ranking/index.php?tipo=<?= CicloService::TIPO_SABADO ?>">
                        <i class="bi bi-star me-1"></i> Sábado
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Selector de ciclo -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <form method="get">
                <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted mb-1" for="ciclo">Ver otro ciclo:</label>
                        <select class="form-select" id="ciclo" name="ciclo" onchange="this.form.submit()">
                            <?php foreach ($listaCiclos as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                        <?= (int) $c['id'] === $cicloId ? 'selected' : '' ?>>
                                    Ciclo <?= (int) $c['numero'] ?> · <?= e(CicloService::rotulo($c)) ?>
                                    <?= $c['estado'] === CicloService::ESTADO_ABIERTO ? '(abierto)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Ranking -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-trophy me-1"></i>
                Ranking de Aciertos
            </h2>
        </div>
        <div class="g-card__body">
            <?php if (!$lista): ?>
                <div class="p-4 text-center text-muted small">
                    Todavía no se cargó ningún <?= $esSabado ? 'turno de este sábado' : 'sorteo de esta semana' ?>.
                </div>
            <?php elseif (!$ranking): ?>
                <div class="p-4 text-center text-muted small">
                    No hay jugadas registradas en este ciclo.
                </div>
            <?php else: ?>
                <?php foreach ($ranking as $i => $puesto): ?>
                    <a class="g-list-item"
                       href="<?= APP_URL ?>/admin/ranking/cliente.php?cliente=<?= (int) $puesto['cliente_id'] ?>&ciclo=<?= $cicloId ?>">
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
                            <div class="d-flex align-items-center gap-2">
                                <span class="g-badge <?= $puesto['aciertos'] > 0 ? 'g-badge--success' : '' ?>"
                                      style="<?= $puesto['aciertos'] == 0 ? 'background:#e5e7eb;color:#4b5563' : '' ?>">
                                    <?= (int) $puesto['aciertos'] ?> / <?= (int) $puesto['total'] ?> aciertos
                                </span>
                                <i class="bi bi-chevron-right text-muted ms-2" aria-hidden="true"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
