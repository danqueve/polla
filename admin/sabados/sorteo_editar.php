<?php
/**
 * Corregir los números de un turno de sábado con diseño Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\SorteoService;

requireAdmin();

$db         = getPDO();
$parametros = new ParametroService($db);
$ciclos     = new CicloService($db);
$sorteos    = SorteoService::crearDesde($db);

$id     = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$sorteo = $id > 0 ? $sorteos->buscarPorId($id) : null;

if (!$sorteo) {
    setFlash('danger', 'Ese turno no existe.');
    header('Location: ' . APP_URL . '/admin/sabados/ciclos.php');
    exit;
}

$ciclo = $ciclos->buscarPorId((int) $sorteo['ciclo_id']);

$chequeo  = $sorteos->puedeCorregirse($ciclo);
$cantidad = $parametros->getInt('numeros_por_sorteo');

$numerosPrevios = old('numeros', array_map('strval', $sorteo['numeros']));

$pageTitle        = 'Corregir Turno Sábado · ' . APP_NAME;
$navSeccion       = 'sabados';
$pageSectionTitle = 'Corregir Turno Sábado';
$bodyClass        = $chequeo['permitido'] ? 'con-accion-fija' : '';
$pageScripts      = $chequeo['permitido'] ? ['numeros.js'] : [];
$breadcrumb       = [
    ['label' => 'Sábados', 'url' => APP_URL . '/admin/sabados/index.php'],
    ['label' => 'Sábado ' . (int) $ciclo['numero'], 'url' => APP_URL . '/admin/sabados/ver.php?id=' . (int) $ciclo['id']],
    ['label' => 'Corregir Turno ' . (int) $sorteo['turno'], 'url' => '']
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
                <span class="g-badge g-badge--warning">Corrección Turno <?= (int) $sorteo['turno'] ?></span>
            </div>
            <h1 class="g-page-title mt-2">Corregir Extracto de Turno</h1>
            <p class="g-page-subtitle">Turno <?= (int) $sorteo['turno'] ?> de 5 · Sábado <?= (int) $ciclo['numero'] ?></p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/sabados/ver.php?id=<?= (int) $ciclo['id'] ?>" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Sábado
            </a>
        </div>
    </div>

    <?php if (!$chequeo['permitido']): ?>

        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-exclamation-triangle fs-1 d-block mb-3 text-warning"></i>
            <h4 class="fw-bold text-dark">No es posible corregir este turno</h4>
            <p class="text-muted"><?= e($chequeo['motivo']) ?></p>
        </div>

    <?php else: ?>

        <?php if ($ciclo['estado'] !== CicloService::ESTADO_ABIERTO): ?>
            <div class="g-alert-banner g-alert-banner--warning mb-4 g-animate g-animate-delay-1">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    Este sábado ya estaba cerrado. Corregir este turno lo va a
                    <strong>reabrir</strong> y volverá a cotejar todas las jugadas con los números corregidos.
                </div>
            </div>
        <?php endif; ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sabados/sorteo_editar_guardar.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $sorteo['id'] ?>">

            <div class="g-card mb-4 g-animate g-animate-delay-1">
                <div class="g-card__header">
                    <h3 class="g-card__title">
                        <span class="g-badge g-badge--blue me-1">Turno <?= (int) $sorteo['turno'] ?></span>
                        Números Corregidos del Extracto
                    </h3>
                    <button type="button" class="js-limpiar g-btn g-btn--outline g-btn--sm">
                        <i class="bi bi-eraser"></i> Limpiar Casillas
                    </button>
                </div>
                <div class="g-card__body">
                    <p class="text-muted small mb-3">
                        En el orden del extracto (del 1° al <?= $cantidad ?>° premio). En el extracto <strong>sí</strong> se pueden repetir números.
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
                    Guardar Corrección de Turno
                </button>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
