<?php
/**
 * Carga del extracto de la Nocturna: fecha + 20 numeros con diseño Gentelella.
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

$ciclo    = $ciclos->obtenerCicloActivo();
$cantidad = $parametros->getInt('numeros_por_sorteo');

$yaCargados = $sorteos->listarPorCiclo((int) $ciclo['id']);
$fechasHechas = array_column($yaCargados, 'fecha');

$pendientes  = [];
$inicioCiclo = new DateTimeImmutable($ciclo['fecha_inicio']);
$fin         = new DateTimeImmutable($ciclo['fecha_fin']);
$hoy         = new DateTimeImmutable('today');
$dia         = $inicioCiclo;
while ($dia <= $fin) {
    $iso = $dia->format('Y-m-d');
    if ($dia <= $hoy && !in_array($iso, $fechasHechas, true)) {
        $pendientes[] = $dia;
    }
    $dia = $dia->modify('+1 day');
}

// La mas vieja pendiente, no la mas nueva: con un dia atrasado sin
// cargar, preseleccionar el mas nuevo empuja a saltearse el que
// realmente falta (el motor ya soporta cargar fuera de orden, pero
// el flujo natural del formulario tiene que ser el correcto).
$fechaPrevia = old('fecha', $pendientes ? $pendientes[0]->format('Y-m-d') : '');
$numerosPrevios = old('numeros', []);

$pageTitle        = 'Cargar Sorteo · ' . APP_NAME;
$navSeccion       = 'sorteos';
$pageSectionTitle = 'Cargar Extracto de Sorteo';
$bodyClass        = 'con-accion-fija';
$pageScripts      = ['numeros.js'];
$breadcrumb       = [
    ['label' => 'Sorteos', 'url' => APP_URL . '/admin/sorteos/index.php'],
    ['label' => 'Cargar Extracto', 'url' => '']
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
                <span class="g-badge g-badge--blue">Ciclo <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge g-badge--success">Nocturna</span>
            </div>
            <h1 class="g-page-title mt-2">Cargar Extracto Oficial</h1>
            <p class="g-page-subtitle">
                Extracto de la Nocturna de Tucumán · Semana del <?= e(CicloService::rotulo($ciclo)) ?>
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/sorteos/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Sorteos
            </a>
        </div>
    </div>

    <?php if (!$pendientes && $inicioCiclo > $hoy): ?>

        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-trophy fs-1 d-block mb-3 text-warning"></i>
            <h4 class="fw-bold text-dark">La semana finalizó porque hubo un ganador</h4>
            <p class="text-muted mb-3">
                Los sorteos restantes de esta semana ya no participan. El próximo ciclo comenzará el
                <strong><?= e(nombreDia($inicioCiclo)) ?> <?= e($inicioCiclo->format('d/m')) ?></strong>.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="g-btn g-btn--primary">
                    <i class="bi bi-plus-lg"></i> Cargar jugadas para próximo ciclo
                </a>
                <a href="<?= APP_URL ?>/admin/ciclos/index.php" class="g-btn g-btn--outline">
                    Ver ciclos cerrados
                </a>
            </div>
        </div>

    <?php elseif (!$pendientes): ?>

        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-calendar-check fs-1 d-block mb-3 text-success"></i>
            <h4 class="fw-bold text-dark">¡Todos los extractos cargados!</h4>
            <p class="text-muted mb-3">Ya se cargaron todos los sorteos ocurridos en este ciclo.</p>
            <div>
                <a href="<?= APP_URL ?>/admin/sorteos/index.php" class="g-btn g-btn--outline">
                    <i class="bi bi-eye"></i> Ver sorteos registrados
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if (count($pendientes) > 1): ?>
            <div class="g-alert-banner g-alert-banner--warning mb-4 g-animate g-animate-delay-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Quedan <strong><?= count($pendientes) ?> sorteos</strong> sin cargar en esta semana.
                    Cárgalos en orden correlativo (el del viernes cierra el ciclo).
                </div>
            </div>
        <?php endif; ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sorteos/guardar.php" novalidate>
            <?= csrfField() ?>

            <div class="row g-4">
                <!-- Columna Principal -->
                <div class="col-12 col-lg-8">

                    <!-- Paso 1: Fecha -->
                    <div class="g-card mb-4 g-animate g-animate-delay-1">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 1</span>
                                Fecha del Sorteo
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <label class="form-label fw-semibold small text-muted" for="fecha">Día del Extracto</label>
                            <select class="form-select form-select-lg" id="fecha" name="fecha" data-requerido required>
                                <?php foreach ($pendientes as $pendiente): ?>
                                    <?php $iso = $pendiente->format('Y-m-d'); ?>
                                    <option value="<?= e($iso) ?>" <?= $fechaPrevia === $iso ? 'selected' : '' ?>>
                                        <?= e(nombreDia($pendiente)) ?> <?= e($pendiente->format('d/m/Y')) ?><?=
                                            $iso === $hoy->format('Y-m-d') ? ' — hoy' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Solo se listan los días pendientes de la semana actual.</div>
                        </div>
                    </div>

                    <!-- Paso 2: Casillas del extracto -->
                    <div class="g-card mb-4 g-animate g-animate-delay-2">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 2</span>
                                Los <?= $cantidad ?> Números del Extracto
                            </h3>
                            <button type="button" class="js-limpiar g-btn g-btn--outline g-btn--sm">
                                <i class="bi bi-eraser"></i> Limpiar Casillas
                            </button>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                En el orden del extracto oficial (del 1° al <?= $cantidad ?>° premio). En el extracto <strong>sí</strong> se pueden repetir números.
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

                <!-- Columna Lateral -->
                <div class="col-12 col-lg-4">
                    <div class="g-card g-animate g-animate-delay-3">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 3</span>
                                Cotejo Automático
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                Al guardar este extracto, el sistema cotejará inmediatamente los <?= $cantidad ?> números contra todas las jugadas activas del ciclo.
                            </p>
                            <div class="g-alert-banner g-alert-banner--warning mb-0 p-3 small">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div>
                                    Si alguna jugada acierta sus 10 números, se consagrará ganadora y el ciclo cerrará automáticamente.
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
                    Guardar y Cotejar Extracto
                </button>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
