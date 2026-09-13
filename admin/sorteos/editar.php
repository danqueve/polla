<?php
/**
 * Corregir los numeros de un sorteo ya cargado (typo en el extracto).
 * Exclusivo del admin. Se puede corregir cualquier sorteo de su ciclo;
 * SorteoService vuelve a cotejar la secuencia completa.
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
    setFlash('danger', 'Ese sorteo no existe.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php');
    exit;
}

$ciclo = $ciclos->buscarPorId((int) $sorteo['ciclo_id']);

$chequeo  = $sorteos->puedeCorregirse($ciclo);
$cantidad = $parametros->getInt('numeros_por_sorteo');

$numerosPrevios = old('numeros', array_map('strval', $sorteo['numeros']));

$pageTitle   = 'Corregir sorteo · ' . APP_NAME;
$navSeccion  = 'sorteos';
$bodyClass   = $chequeo['permitido'] ? 'con-accion-fija' : '';
$pageScripts = $chequeo['permitido'] ? ['numeros.js'] : [];
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/sorteos/index.php?ciclo=<?= (int) $ciclo['id'] ?>"
       class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Sorteos
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Corregir sorteo</h1>
    <p class="pantalla__bajada">
        <?= e(formatFechaDia($sorteo['fecha'])) ?> · Ciclo <?= (int) $ciclo['numero'] ?>
    </p>

    <?php if (!$chequeo['permitido']): ?>

        <div class="vacio tarjeta mt-3">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <?= e($chequeo['motivo']) ?>
        </div>

    <?php else: ?>

        <?php if ($ciclo['estado'] !== CicloService::ESTADO_ABIERTO): ?>
            <div class="alert alert-warning mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Este ciclo ya estaba cerrado. Corregir este sorteo lo va a
                <strong>reabrir</strong> y a recotejar todo de nuevo con los números corregidos
                (deshaciendo el ganador o el cierre que haya quedado marcado por error).
            </div>
        <?php endif; ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sorteos/editar_guardar.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $sorteo['id'] ?>">

            <section class="tarjeta p-3 mt-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="rotulo">Los <?= $cantidad ?> números</span>
                        <div class="fw-semibold">Números corregidos del extracto</div>
                    </div>
                    <button type="button" class="js-limpiar btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eraser"></i> Limpiar
                    </button>
                </div>

                <p class="form-text mt-0 mb-3">
                    En el orden del extracto, del 1° al <?= $cantidad ?>° premio.
                    Aca <strong>si</strong> puede repetirse un numero.
                </p>

                <div class="casillas">
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

                <hr class="my-3">

                <span class="rotulo d-block mb-2">Tablero 00 - 99</span>
                <div class="tablero js-tablero" aria-hidden="true">
                    <?php for ($n = 0; $n <= 99; $n++): ?>
                        <div class="tablero__celda"><?= num2($n) ?></div>
                    <?php endfor; ?>
                </div>
            </section>
        </form>

        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">premios</div>
                </div>
                <button type="submit" form="form-sorteo" id="btn-confirmar"
                        class="btn btn-primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i>
                    Guardar corrección
                </button>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
