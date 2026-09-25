<?php
/** Jugadas del ciclo, con buscador por cliente y filtros estilo Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;

requireLogin();

$db     = getPDO();
$ciclos = new CicloService($db);

$cicloId     = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
$ciclo       = $cicloId > 0 ? $ciclos->buscarPorId($cicloId) : $ciclos->obtenerCicloActivo();
$listaCiclos = $ciclos->listar(20);

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/jugadas/index.php');
    exit;
}

$busqueda     = trim($_GET['q'] ?? '');
$jugadasSvc   = JugadaService::crearDesde($db);
$jugadas      = $jugadasSvc->listarPorCiclo((int) $ciclo['id'], $busqueda);
// El total real, no count($jugadas): listarPorCiclo() corta en 200 sin
// avisar, asi que el titulo mentia en un ciclo con muchas jugadas.
$jugadasTotal = $jugadasSvc->contarPorCiclo((int) $ciclo['id'], $busqueda);
$resumen      = $ciclos->resumen((int) $ciclo['id']);

$pageTitle        = 'Jugadas · ' . APP_NAME;
$navSeccion       = 'jugadas';
$pageSectionTitle = 'Listado de Jugadas';
$breadcrumb       = [
    ['label' => 'Jugadas', 'url' => '']
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
                <span class="g-badge <?= $ciclo['estado'] === CicloService::ESTADO_ABIERTO ? 'g-badge--success' : 'g-badge--info' ?>">
                    <?= $ciclo['estado'] === CicloService::ESTADO_ABIERTO ? 'Abierto' : 'Cerrado' ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2">Jugadas Registradas</h1>
            <p class="g-page-subtitle">
                <?= (int) $resumen['jugadas_total'] ?> cargadas ·
                <?= e(formatPesos($resumen['recaudado'])) ?> recaudados ·
                <?= e(formatPesos($resumen['al_pozo'])) ?> al pozo
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-btn g-btn--primary">
                <i class="bi bi-plus-lg"></i> Cargar Nueva Jugada
            </a>
        </div>
    </div>

    <!-- Filtros y Buscador -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <form method="get">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold small text-muted" for="ciclo">Seleccionar Ciclo</label>
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
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold small text-muted" for="q">Buscar por Cliente</label>
                        <div class="input-group">
                            <input type="search" class="form-control" id="q" name="q"
                                   value="<?= e($busqueda) ?>"
                                   placeholder="Nombre, DNI o N° de cliente">
                            <button type="submit" class="g-btn g-btn--primary">
                                <i class="bi bi-search"></i>
                                <span class="d-none d-sm-inline">Buscar</span>
                            </button>
                            <?php if ($busqueda !== ''): ?>
                                <a href="<?= APP_URL ?>/admin/jugadas/index.php?ciclo=<?= (int) $ciclo['id'] ?>"
                                   class="g-btn g-btn--outline" title="Limpiar búsqueda">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Jugadas -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-ticket-perforated"></i>
                Listado de Jugadas (<?= $jugadasTotal ?>)
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
                    <p class="mb-3">
                        <?= $busqueda !== ''
                            ? 'No hay jugadas que coincidan con la búsqueda en este ciclo.'
                            : 'Todavía no hay jugadas registradas en este ciclo.' ?>
                    </p>
                    <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-btn g-btn--primary g-btn--sm">
                        <i class="bi bi-plus-lg"></i> Cargar una jugada ahora
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($jugadas as $jugada): ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title">
                                    <?= e($jugada['cliente_nombre']) ?>
                                    <span class="text-muted fw-normal fs-7 ms-1">(N° <?= e($jugada['nro_cliente']) ?>)</span>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                                    <?php if ($jugada['cargado_por_nombre']): ?>
                                        · <i class="bi bi-person me-1"></i>Cargado por <?= e($jugada['cargado_por_nombre']) ?>
                                    <?php elseif ($jugada['origen_carga'] === 'cliente'): ?>
                                        · <i class="bi bi-phone me-1"></i>Autoservicio portal
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-end text-nowrap d-flex flex-column align-items-end gap-1">
                                <div class="fw-bold fs-6 text-dark"><?= e(formatPesos($jugada['importe'])) ?></div>
                                <?php if ((int) $jugada['pagada'] !== 1): ?>
                                    <span class="g-badge g-badge--danger">Impaga</span>
                                <?php elseif ($jugada['estado'] === 'ganadora'): ?>
                                    <span class="g-badge g-badge--warning">Ganadora</span>
                                <?php elseif ($jugada['estado'] === 'perdedora'): ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                                <?php else: ?>
                                    <span class="g-badge g-badge--success">Activa</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Bolillas -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <div class="bolillas">
                                <?php foreach ($jugada['numeros'] as $numero): ?>
                                    <span class="bolilla"><?= e(num2($numero)) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <?php if (isAdmin()): ?>
                                <form method="post" action="<?= APP_URL ?>/admin/jugadas/eliminar.php"
                                      onsubmit="return confirm('¿Borrar la jugada de <?= e(addslashes($jugada['cliente_nombre'])) ?>? Se descontará el aporte al pozo.')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $jugada['id'] ?>">
                                    <input type="hidden" name="volver_a" value="<?= (int) $ciclo['id'] ?>">
                                    <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0"
                                            title="Eliminar jugada y descontar pozo">
                                        <i class="bi bi-trash"></i> Borrar
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
