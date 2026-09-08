<?php
/**
 * Carga del extracto de la Nocturna: fecha + 20 numeros.
 *
 * Al guardar se dispara el cotejo contra todas las jugadas activas
 * del ciclo. Si alguien acerto los 10, la semana se corta ahi mismo.
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

// Dias habiles del ciclo que todavia no tienen extracto, para el selector.
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

// La fecha sugerida es la pendiente mas reciente: lo normal es cargar
// el sorteo de anoche, pero si quedo alguno atrasado aparece primero.
$fechaPrevia = old('fecha', $pendientes ? end($pendientes)->format('Y-m-d') : '');
$numerosPrevios = old('numeros', []);

$pageTitle   = 'Cargar sorteo · ' . APP_NAME;
$navSeccion  = 'sorteos';
$bodyClass   = 'con-accion-fija';
$pageScripts = ['numeros.js'];
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2">
        <h1 class="pantalla__titulo">Cargar sorteo</h1>
        <span class="rotulo text-nowrap">Ciclo <?= (int) $ciclo['numero'] ?></span>
    </div>
    <p class="pantalla__bajada">
        Extracto de la Nocturna de Tucuman · semana del <?= e(CicloService::rotulo($ciclo)) ?>
    </p>

    <?php if (!$pendientes && $inicioCiclo > $hoy): ?>

        <!-- Zona muerta: alguien gano y corto la semana, y el ciclo nuevo
             todavia no arranco. Los sorteos que quedan de esta semana
             calendario ya no participan (regla 3.2 de la especificacion). -->
        <div class="vacio tarjeta mt-3">
            <i class="bi bi-trophy" aria-hidden="true"></i>
            <p class="mb-1"><strong>La semana pasada se corto porque hubo ganador.</strong></p>
            <p class="mb-0">
                Los sorteos que quedan de esta semana ya no participan.
                El proximo que juega es el del
                <strong><?= e(nombreDia($inicioCiclo)) ?> <?= e($inicioCiclo->format('d/m')) ?></strong>.
            </p>
            <div class="mt-3 d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="btn btn-sm btn-primary">
                    Cargar jugadas para la semana que viene
                </a>
                <a href="<?= APP_URL ?>/admin/ciclos/index.php" class="btn btn-sm btn-outline-secondary">
                    Ver el ciclo que se cerro
                </a>
            </div>
        </div>

    <?php elseif (!$pendientes): ?>

        <div class="vacio tarjeta mt-3">
            <i class="bi bi-calendar-check" aria-hidden="true"></i>
            Ya estan cargados todos los sorteos de esta semana que ya ocurrieron.
            <div class="mt-3">
                <a href="<?= APP_URL ?>/admin/sorteos/index.php" class="btn btn-sm btn-outline-secondary">
                    Ver los cargados
                </a>
            </div>
        </div>

    <?php else: ?>

        <?php if (count($pendientes) > 1): ?>
            <div class="alert alert-warning mt-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                Quedan <?= count($pendientes) ?> sorteos sin cargar en esta semana.
                Cargalos en orden: el del viernes es el que cierra el ciclo.
            </div>
        <?php endif; ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sorteos/guardar.php" novalidate>
            <?= csrfField() ?>

            <!-- 1. Fecha -->
            <section class="tarjeta p-3 mt-3">
                <label class="form-label" for="fecha">
                    <span class="rotulo">Paso 1</span><br>Fecha del sorteo
                </label>
                <select class="form-select" id="fecha" name="fecha" data-requerido required>
                    <?php foreach ($pendientes as $pendiente): ?>
                        <?php $iso = $pendiente->format('Y-m-d'); ?>
                        <option value="<?= e($iso) ?>" <?= $fechaPrevia === $iso ? 'selected' : '' ?>>
                            <?= e(nombreDia($pendiente)) ?> <?= e($pendiente->format('d/m/Y')) ?><?=
                                $iso === $hoy->format('Y-m-d') ? ' — hoy' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Solo aparecen los dias de esta semana que faltan cargar.</div>
            </section>

            <!-- 2. Los 20 numeros del extracto -->
            <section class="tarjeta p-3 mt-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="rotulo">Paso 2</span>
                        <div class="fw-semibold">Los <?= $cantidad ?> numeros</div>
                    </div>
                    <button type="button" id="btn-limpiar" class="btn btn-sm btn-outline-secondary">
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
                <div class="tablero" id="tablero-numeros" aria-hidden="true">
                    <?php for ($n = 0; $n <= 99; $n++): ?>
                        <div class="tablero__celda"><?= num2($n) ?></div>
                    <?php endfor; ?>
                </div>
            </section>

            <!-- 3. Advertencia -->
            <section class="tarjeta p-3 mt-3">
                <span class="rotulo">Paso 3</span>
                <div class="fw-semibold mb-2">Cotejo automatico</div>
                <p class="fila__meta mb-2">
                    Al guardar, el sistema compara estos <?= $cantidad ?> numeros contra
                    todas las jugadas activas del ciclo. Si una jugada tiene sus 10 numeros
                    entre estos, gana y la semana se corta.
                </p>
                <div class="alert alert-warning mb-0 py-2" style="font-size:.875rem">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Revisá bien los numeros antes de confirmar: si hay ganador,
                    el pozo se liquida y el ciclo se cierra automaticamente.
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
                    Guardar y cotejar
                </button>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
