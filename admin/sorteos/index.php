<?php
/** Sorteos cargados, por ciclo con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

$cicloId = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
$ciclo   = $cicloId > 0 ? $ciclos->buscarPorId($cicloId) : $ciclos->obtenerCicloActivo();

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php');
    exit;
}

$listaCiclos = $ciclos->listar(20);
$lista       = $sorteos->listarPorCiclo((int) $ciclo['id']);
$abierto     = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;

$pageTitle        = 'Sorteos · ' . APP_NAME;
$navSeccion       = 'sorteos';
$pageSectionTitle = 'Extractos de Sorteos';
$breadcrumb       = [
    ['label' => 'Sorteos', 'url' => '']
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
                    <?= $abierto ? 'Ciclo Abierto' : 'Ciclo Cerrado' ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2">Sorteos de la Semana</h1>
            <p class="g-page-subtitle">
                <?= count($lista) ?> de 5 extractos cargados · Semana del <?= e(CicloService::rotulo($ciclo)) ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <?php if ($abierto): ?>
                <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="g-btn g-btn--primary">
                    <i class="bi bi-plus-lg"></i> Cargar Extracto
                </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/ciclos/ver.php?id=<?= (int) $ciclo['id'] ?>" class="g-btn g-btn--outline">
                <i class="bi bi-clipboard-data"></i> Resumen del Ciclo
            </a>
        </div>
    </div>

    <!-- Filtro de Ciclo -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <form method="get">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label fw-semibold small text-muted mb-1" for="ciclo">Ver sorteos de otro ciclo:</label>
                        <select class="form-select" id="ciclo" name="ciclo" onchange="this.form.submit()">
                            <?php foreach ($listaCiclos as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                        <?= (int) $c['id'] === (int) $ciclo['id'] ? 'selected' : '' ?>>
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

    <!-- Listado de Extractos -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-dice-5"></i>
                Extractos Cargados (<?= count($lista) ?> / 5)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if (!$lista): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-dice-5 fs-1 d-block mb-3 text-muted"></i>
                    <p class="mb-3">Todavía no hay sorteos cargados en este ciclo.</p>
                    <?php if ($abierto): ?>
                        <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="g-btn g-btn--primary g-btn--sm">
                            <i class="bi bi-plus-lg"></i> Cargar el extracto de hoy
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($lista as $sorteo): ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <h3 class="g-list-item__title">
                                    <i class="bi bi-calendar-event me-1 text-primary"></i>
                                    <?= e(formatFechaDia($sorteo['fecha'])) ?>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <i class="bi bi-clock me-1"></i>Cargado el <?= e(formatFechaHora($sorteo['creado_en'])) ?>
                                    <?php if ($sorteo['cargado_por_nombre']): ?>
                                        · por <?= e($sorteo['cargado_por_nombre']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <?php if ((int) $sorteo['ganadores_total'] > 0): ?>
                                    <span class="g-badge g-badge--warning">
                                        <i class="bi bi-trophy-fill me-1"></i>
                                        <?= (int) $sorteo['ganadores_total'] ?> <?= (int) $sorteo['ganadores_total'] === 1 ? 'ganador' : 'ganadores' ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (isAdmin()): ?>
                                    <a href="<?= APP_URL ?>/admin/sorteos/editar.php?id=<?= (int) $sorteo['id'] ?>"
                                       class="g-btn g-btn--outline g-btn--sm">
                                        <i class="bi bi-pencil"></i> Editar
                                    </a>
                                    <?php if ($abierto): ?>
                                        <form method="post" action="<?= APP_URL ?>/admin/sorteos/eliminar.php"
                                              class="d-inline"
                                              onsubmit="return confirm('¿Borrar el sorteo del <?= e($sorteo['fecha']) ?>?')">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $sorteo['id'] ?>">
                                            <input type="hidden" name="volver_a" value="<?= (int) $ciclo['id'] ?>">
                                            <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Bolillas del extracto -->
                        <div class="bolillas mt-3">
                            <?php foreach ($sorteo['numeros'] as $numero): ?>
                                <span class="bolilla"><?= e(num2($numero)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
