<?php
/**
 * Carga de jugada: cliente + 10 numeros distintos + pago.
 *
 * Pensada para hacerse de pie y con una mano: el cliente dicta los
 * numeros y el supervisor los tipea de corrido en las 10 casillas,
 * que saltan solas. El tablero de abajo es solo confirmacion visual.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ClienteService;
use Polla\Services\ParametroService;

requireLogin();

$db         = getPDO();
$parametros = new ParametroService($db);
$ciclos     = new CicloService($db);

$ciclo    = $ciclos->obtenerCicloActivo();
$clientes = (new ClienteService($db))->listarActivosParaSelect();

$importe   = $parametros->importeJugada();
$reparto   = $parametros->repartir($importe);
$cantidad  = $parametros->numerosPorJugada();

// Repoblar tras un error de validacion del servidor
$numerosPrevios  = old('numeros', []);
$clientePrevio   = (int) old('cliente_id', 0);

$pageTitle    = 'Cargar jugada · ' . APP_NAME;
$navSeccion   = 'nueva';
$bodyClass    = 'con-accion-fija';
$pageScripts  = ['numeros.js'];
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2">
        <h1 class="pantalla__titulo">Cargar jugada</h1>
        <span class="rotulo text-nowrap">Ciclo <?= (int) $ciclo['numero'] ?></span>
    </div>
    <p class="pantalla__bajada">
        Semana del <?= e(CicloService::rotulo($ciclo)) ?>
    </p>

    <?php if (!$clientes): ?>

        <div class="vacio tarjeta mt-3">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            No hay clientes activos para cargarle una jugada.
            <div class="mt-3">
                <a href="<?= APP_URL ?>/admin/clientes/form.php" class="btn btn-sm btn-primary">
                    Dar de alta un cliente
                </a>
            </div>
        </div>

    <?php else: ?>

        <form method="post" id="form-jugada"
              data-numeros="jugada" data-repetidos="no"
              action="<?= APP_URL ?>/admin/jugadas/guardar.php" novalidate>
            <?= csrfField() ?>

            <!-- 1. Cliente -->
            <section class="tarjeta p-3 mt-3">
                <label class="form-label" for="cliente_id">
                    <span class="rotulo">Paso 1</span><br>Cliente
                </label>
                <select class="form-select" id="cliente_id" name="cliente_id"
                        data-requerido required autofocus>
                    <option value="">Elegí un cliente...</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= (int) $cliente['id'] ?>"
                                <?= $clientePrevio === (int) $cliente['id'] ? 'selected' : '' ?>>
                            <?= e($cliente['nombre']) ?> — N° <?= e($cliente['nro_cliente']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    Falta alguno?
                    <a href="<?= APP_URL ?>/admin/clientes/form.php">Dalo de alta</a>.
                </div>
            </section>

            <!-- 2. Los 10 numeros -->
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
                    Distintos, entre 00 y 99. Se salta solo a la casilla siguiente.
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
                                   aria-label="Numero <?= $i + 1 ?> de <?= $cantidad ?>">
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="alert alert-danger mt-3 mb-0 py-2" id="aviso-repetidos"
                     role="alert" aria-live="polite" hidden></div>

                <hr class="my-3">

                <span class="rotulo d-block mb-2">Tablero 00 - 99</span>
                <div class="tablero" id="tablero-numeros" aria-hidden="true">
                    <?php for ($n = 0; $n <= 99; $n++): ?>
                        <div class="tablero__celda"><?= num2($n) ?></div>
                    <?php endfor; ?>
                </div>
            </section>

            <!-- 3. Pago -->
            <section class="tarjeta p-3 mt-3">
                <span class="rotulo">Paso 3</span>
                <div class="fw-semibold mb-3">Pago</div>

                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span>Importe de la jugada</span>
                    <span class="cifra fw-bold fs-5"><?= e(formatPesos($importe)) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline fila__meta mb-1">
                    <span>Va al pozo (<?= (int) $parametros->porcentajePozo() ?>%)</span>
                    <span class="cifra"><?= e(formatPesos($reparto['pozo'])) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline fila__meta">
                    <span>Gastos y ganancias</span>
                    <span class="cifra"><?= e(formatPesos($reparto['gastos'])) ?></span>
                </div>

                <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:.875rem">
                    Al confirmar, la jugada queda registrada como <strong>pagada</strong>
                    y el aporte entra al pozo de esta semana.
                </div>
            </section>
        </form>

        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">numeros</div>
                </div>
                <button type="submit" form="form-jugada" id="btn-confirmar"
                        class="btn btn-primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i>
                    Confirmar y cobrar <?= e(formatPesos($importe)) ?>
                </button>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
