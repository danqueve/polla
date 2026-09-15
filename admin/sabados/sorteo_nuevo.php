<?php
/**
 * Carga de un turno del sábado con diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\SorteoService;

requireLogin();

$db         = getPDO();
$parametros = new ParametroService($db);
$ciclos     = new CicloService($db);
$sorteos    = SorteoService::crearDesde($db);

$ciclo    = $ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);
$cantidad = $parametros->getInt('numeros_por_sorteo');

$yaCargados = $sorteos->listarPorCiclo((int) $ciclo['id']);
$turno      = count($yaCargados) + 1;

$fechaCiclo = new DateTimeImmutable($ciclo['fecha_inicio']);
$hoy        = new DateTimeImmutable('today');

$numerosPrevios = old('numeros', []);

$pageTitle        = 'Cargar Turno Sábado · ' . APP_NAME;
$navSeccion       = 'sabados';
$pageSectionTitle = 'Cargar Turno de Sábado';
$bodyClass        = 'con-accion-fija';
$pageScripts      = ['numeros.js'];
$breadcrumb       = [
    ['label' => 'Sábados', 'url' => APP_URL . '/admin/sabados/index.php'],
    ['label' => 'Cargar Turno ' . $turno, 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue">Sábado <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge g-badge--warning">Turno <?= $turno ?> de 5</span>
            </div>
            <h1 class="g-page-title mt-2">Cargar Extracto de Turno</h1>
            <p class="g-page-subtitle"><?= e(CicloService::rotulo($ciclo)) ?></p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/sabados/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Sábados
            </a>
        </div>
    </div>

    <?php if ($fechaCiclo > $hoy): ?>

        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-calendar-event fs-1 d-block mb-3 text-warning"></i>
            <h4 class="fw-bold text-dark">Todavía no llegó el próximo sábado</h4>
            <p class="text-muted mb-3">
                El próximo turno que juega es el del
                <strong><?= e(nombreDia($fechaCiclo)) ?> <?= e($fechaCiclo->format('d/m')) ?></strong>.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>" class="g-btn g-btn--primary">
                    <i class="bi bi-plus-lg"></i> Cargar jugadas para próximo sábado
                </a>
                <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="g-btn g-btn--outline">
                    Ver historial de sábados
                </a>
            </div>
        </div>

    <?php else: ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sabados/sorteo_guardar.php" novalidate>
            <?= csrfField() ?>

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="g-card mb-4 g-animate g-animate-delay-1">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Turno <?= $turno ?> de 5</span>
                                Los <?= $cantidad ?> Números del Turno
                            </h3>
                            <button type="button" class="js-limpiar g-btn g-btn--outline g-btn--sm">
                                <i class="bi bi-eraser"></i> Limpiar Casillas
                            </button>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                Ingrese los números en el orden del extracto (del 1° al <?= $cantidad ?>° premio). En el extracto <strong>sí</strong> se pueden repetir.
                            </p>

                            <div class="casillas mb-4">
                                <?php for ($i = 0; $i < $cantidad; $i++): ?>
                                    <?php $previo = isset($numerosPrevios[$i]) ? trim((string) $numerosPrevios[$i]) : ''; ?>
                                    <div class="casilla">
                                        <span class="casilla__indice" aria-hidden="true"><?= $i + 1 ?></span>
                                        <input type="text"
                                               class="casilla__input"
                                               name="numeros[]"
                                               value="<?= e($previo) ?>"
                                               inputmode="numeric"
                                               pattern="[0-9]*"
                                               maxlength="2"
                                               placeholder="--"
                                               autocomplete="off"
                                               aria-label="Premio <?= $i + 1 ?> de <?= $cantidad ?>">
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <hr class="my-4">

                            <div class="fw-semibold small text-muted mb-2">Tablero de Control Visual (00 - 99):</div>
                            <div class="tablero js-tablero" aria-hidden="true">
                                <?php for ($n = 0; $n <= 99; $n++): ?>
                                    <div class="tablero__celda"><?= num2($n) ?></div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="g-card g-animate g-animate-delay-2">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <i class="bi bi-cpu me-1"></i>
                                Cotejo de Sábados
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                Al guardar, el sistema cotejará estos <?= $cantidad ?> números contra todas las jugadas activas de este sábado (modalidad 5 números).
                            </p>
                            <div class="g-alert-banner g-alert-banner--warning mb-0 p-3 small">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div>
                                    Si alguna jugada acierta sus 5 números, gana el pozo y el día sábado se cierra automáticamente.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">premios</div>
                </div>
                <button type="submit" form="form-sorteo" id="btn-confirmar"
                        class="g-btn g-btn--primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i>
                    Guardar y Cotejar Turno
                </button>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
