<?php
/**
 * Carga de un turno del sabado: 20 numeros, sin selector de fecha (el
 * turno se calcula solo contando cuantos ya tiene el ciclo abierto).
 *
 * Al guardar se dispara el mismo cotejo que el semanal contra las
 * jugadas activas del ciclo sabado. Si alguien acerto los 10, el dia
 * se corta ahi mismo.
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

$pageTitle   = 'Cargar turno · ' . APP_NAME;
$navSeccion  = 'sabados';
$bodyClass   = 'con-accion-fija';
$pageScripts = ['numeros.js'];
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2">
        <h1 class="pantalla__titulo">Cargar turno</h1>
        <span class="rotulo text-nowrap">Sábado <?= (int) $ciclo['numero'] ?></span>
    </div>
    <p class="pantalla__bajada">
        Turno <?= $turno ?> de 5 · <?= e(CicloService::rotulo($ciclo)) ?>
    </p>

    <?php if ($fechaCiclo > $hoy): ?>

        <!-- Zona muerta: el sabado anterior se corto (con o sin ganador) y
             el ciclo nuevo todavia no llego a su fecha. -->
        <div class="vacio tarjeta mt-3">
            <i class="bi bi-calendar-event" aria-hidden="true"></i>
            <p class="mb-1"><strong>Todavía no llegó el próximo sábado.</strong></p>
            <p class="mb-0">
                El próximo turno que juega es el del
                <strong><?= e(nombreDia($fechaCiclo)) ?> <?= e($fechaCiclo->format('d/m')) ?></strong>.
            </p>
            <div class="mt-3 d-flex flex-column gap-2">
                <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>" class="btn btn-sm btn-primary">
                    Cargar jugadas para el próximo sábado
                </a>
                <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="btn btn-sm btn-outline-secondary">
                    Ver el sábado que se cerró
                </a>
            </div>
        </div>

    <?php else: ?>

        <form method="post" id="form-sorteo"
              data-numeros="sorteo" data-repetidos="si"
              action="<?= APP_URL ?>/admin/sabados/sorteo_guardar.php" novalidate>
            <?= csrfField() ?>

            <section class="tarjeta p-3 mt-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div>
                        <span class="rotulo">Turno <?= $turno ?> de 5</span>
                        <div class="fw-semibold">Los <?= $cantidad ?> números</div>
                    </div>
                    <button type="button" class="js-limpiar btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eraser"></i> Limpiar
                    </button>
                </div>

                <p class="form-text mt-0 mb-3">
                    En el orden del extracto, del 1° al <?= $cantidad ?>° premio.
                    Acá <strong>sí</strong> puede repetirse un número.
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

            <section class="tarjeta p-3 mt-3">
                <span class="rotulo">Cotejo automático</span>
                <div class="fw-semibold mb-2">&nbsp;</div>
                <p class="fila__meta mb-2">
                    Al guardar, el sistema compara estos <?= $cantidad ?> números contra
                    todas las jugadas activas de este sábado. Si una jugada tiene sus 10 números
                    entre estos, gana y el día se corta.
                </p>
                <div class="alert alert-warning mb-0 py-2" style="font-size:.875rem">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    Revisá bien los números antes de confirmar: si hay ganador,
                    el pozo se liquida y el ciclo se cierra automáticamente.
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
