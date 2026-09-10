<?php
/**
 * Carga de jugada: cliente + una o varias jugadas de 10 numeros + pago.
 *
 * Pensada para hacerse de pie y con una mano: el cliente dicta los
 * numeros y el supervisor los tipea de corrido en las 10 casillas,
 * que saltan solas. "Agregar otra jugada" repite el bloque de 10
 * casillas para cargar varias jugadas del mismo cliente en una sola
 * operacion (ej. paga 3 de una vez); cada bloque valida sus propios
 * repetidos por separado.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ClienteService;
use Polla\Services\ParametroService;
use Polla\Services\PromocionService;

requireLogin();

$db         = getPDO();
$parametros = new ParametroService($db);
$ciclos     = new CicloService($db);

$ciclo    = $ciclos->obtenerCicloActivo();
$clientes = (new ClienteService($db))->listarActivosParaSelect();

$importe  = $parametros->importeJugada();
$reparto  = $parametros->repartir($importe);
$cantidad = $parametros->numerosPorJugada();

// Promociones activas indexadas por cantidad_jugadas, para que
// promociones.js sugiera el paquete sin ninguna consulta extra al
// cambiar la cantidad de jugadas. Solo lo que la vista necesita.
$promosPorCantidad = [];
foreach ((new PromocionService($db))->activasPorCantidad() as $cantidadPromo => $promo) {
    $promosPorCantidad[$cantidadPromo] = [
        'id'               => (int) $promo['id'],
        'precio_total'     => (float) $promo['precio_total'],
        'cantidad_jugadas' => (int) $promo['cantidad_jugadas'],
    ];
}

// Repoblar tras un error de validacion del servidor. Un grupo vacio por
// defecto: lo normal es cargar una sola jugada.
$gruposPrevios = old('grupos', [['numeros' => array_fill(0, $cantidad, '')]]);
if (!$gruposPrevios) {
    $gruposPrevios = [['numeros' => array_fill(0, $cantidad, '')]];
}
$clientePrevio = (int) old('cliente_id', 0);

/** Dibuja un bloque de "una jugada": sus 10 casillas, limpiar y aviso. */
$dibujarGrupo = static function ($indice, array $numeros) use ($cantidad): void {
    ?>
    <div class="tarjeta p-3 mt-2" data-numeros="jugada" data-repetidos="no">
        <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
            <div class="fw-semibold">
                Jugada <span class="js-grupo-numero"><?= is_int($indice) ? $indice + 1 : '' ?></span>
            </div>
            <div class="d-flex gap-3">
                <button type="button" class="js-limpiar btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eraser"></i> Limpiar
                </button>
                <button type="button" class="js-quitar-grupo btn btn-sm btn-outline-danger" hidden
                        aria-label="Quitar esta jugada">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <div class="casillas">
            <?php for ($i = 0; $i < $cantidad; $i++): ?>
                <?php $previo = isset($numeros[$i]) ? trim((string) $numeros[$i]) : ''; ?>
                <div class="casilla">
                    <span class="casilla__indice" aria-hidden="true"><?= $i + 1 ?></span>
                    <input type="text"
                           class="casilla__input"
                           name="grupos[<?= e((string) $indice) ?>][numeros][]"
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

        <div class="alert alert-danger mt-3 mb-0 py-2 js-aviso" role="alert" aria-live="polite" hidden></div>
    </div>
    <?php
};

$pageTitle    = 'Cargar jugada · ' . APP_NAME;
$navSeccion   = 'nueva';
$bodyClass    = 'con-accion-fija';
$pageScripts  = ['promociones.js', 'numeros.js'];
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

            <!-- 2. Las jugadas: una o varias -->
            <div class="d-flex align-items-baseline justify-content-between gap-2 mt-3">
                <span class="rotulo">Paso 2 · Los <?= $cantidad ?> números de cada jugada</span>
            </div>
            <p class="form-text mt-0 mb-0">
                Distintos entre 00 y 99 dentro de cada jugada. Se salta solo a la casilla siguiente.
            </p>

            <div id="grupos-jugada">
                <?php foreach ($gruposPrevios as $idx => $grupo): ?>
                    <?php $dibujarGrupo($idx, $grupo['numeros'] ?? []); ?>
                <?php endforeach; ?>
            </div>

            <button type="button" id="btn-agregar-jugada" class="btn btn-outline-secondary w-100 mt-2 mb-3">
                <i class="bi bi-plus-lg"></i> Agregar otra jugada
            </button>

            <div id="promo-sugerida" class="alert alert-success d-flex align-items-start gap-2 mb-3" hidden
                 data-promos="<?= e(json_encode($promosPorCantidad, JSON_UNESCAPED_UNICODE)) ?>">
                <i class="bi bi-tag-fill flex-shrink-0" style="margin-top:.15rem" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <div id="promo-sugerida-texto" class="fw-semibold"></div>
                    <div class="form-check form-switch mt-2 mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="promo-aplicar">
                        <label class="form-check-label" for="promo-aplicar">Aplicar la promo</label>
                    </div>
                </div>
            </div>
            <input type="hidden" name="promocion_id" id="promocion_id" value="">

            <!-- 3. Pago -->
            <section class="tarjeta p-3 mb-3">
                <span class="rotulo">Paso 3</span>
                <div class="fw-semibold mb-3">Pago</div>

                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span>Monto por jugada</span>
                    <span class="cifra"><?= e(formatPesos($importe)) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span>Cantidad de jugadas</span>
                    <span class="cifra" id="cantidad-jugadas">1</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-baseline mb-2">
                    <span class="fw-semibold">Total a cobrar</span>
                    <span class="cifra fw-bold fs-3" id="total-a-cobrar"
                          data-monto="<?= e((string) $importe) ?>">
                        <?= e(formatPesos($importe)) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline fila__meta mb-1">
                    <span>Va al pozo por jugada (<?= (int) $parametros->porcentajePozo() ?>%)</span>
                    <span class="cifra"><?= e(formatPesos($reparto['pozo'])) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-baseline fila__meta">
                    <span>Gastos y ganancias por jugada</span>
                    <span class="cifra"><?= e(formatPesos($reparto['gastos'])) ?></span>
                </div>

                <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:.875rem">
                    Al confirmar, las jugadas quedan registradas como <strong>pagadas</strong>
                    y su aporte entra al pozo de esta semana.
                </div>
            </section>
        </form>

        <!-- Plantilla para "Agregar otra jugada": numeros.js clona esto y
             reemplaza __INDICE__ por la posicion real. -->
        <template id="plantilla-grupo-jugada">
            <?php $dibujarGrupo('__INDICE__', array_fill(0, $cantidad, '')); ?>
        </template>

        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">numeros</div>
                </div>
                <button type="submit" form="form-jugada" id="btn-confirmar"
                        class="btn btn-primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i>
                    <span id="btn-confirmar-texto" data-plantilla="Confirmar y cobrar">Confirmar y cobrar <?= e(formatPesos($importe)) ?></span>
                </button>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php
flushOld();
require __DIR__ . '/../../includes/foot.php';
