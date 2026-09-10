<?php
/**
 * El cliente arma su jugada (Fase 6).
 *
 * Mismo widget de siempre (numeros.js: 10 casillas + "Agregar otra
 * jugada"), pero ahora lo completa el cliente sobre su propia sesion
 * en vez de un supervisor tipeandole los numeros. requireCliente() sin
 * flags exige sesion + activo + estado='aprobado' + clave ya cambiada:
 * un cliente pendiente de aprobacion no llega a esta pantalla, lo
 * manda derecho a portal/pendiente.php.
 *
 * Al confirmar no se cobra nada aca: se genera un codigo y se muestra
 * el monto a pagar. El pago y la confirmacion los hace el staff en
 * admin/solicitudes/.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\ParametroService;
use Polla\Services\PromocionService;
use Polla\Services\SolicitudService;

requireCliente();

$db         = getPDO();
$parametros = new ParametroService($db);
$clienteId  = (int) clienteActualId();

$importe  = $parametros->importeJugada();
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

$pendiente = SolicitudService::crearDesde($db)->pendientePara($clienteId);

// Repoblar tras un error de validacion del servidor. Un grupo vacio por
// defecto: lo normal es armar una sola jugada.
$gruposPrevios = old('grupos', [['numeros' => array_fill(0, $cantidad, '')]]);
if (!$gruposPrevios) {
    $gruposPrevios = [['numeros' => array_fill(0, $cantidad, '')]];
}

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

$pageTitle   = 'Armar jugada · ' . APP_NAME;
$navSeccion  = 'jugar';
$bodyClass   = 'con-accion-fija';
$pageScripts = ['promociones.js', 'numeros.js'];
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Armar jugada</h1>
    <p class="pantalla__bajada">
        Elegí <?= $cantidad ?> números por jugada. Después pagás en persona o por
        transferencia con el código que te va a quedar.
    </p>

    <?php if ($pendiente): ?>
        <div class="alert alert-info d-flex align-items-start gap-2 mt-3" role="note">
            <i class="bi bi-info-circle-fill flex-shrink-0" style="margin-top:.15rem"></i>
            <div>
                Ya tenés una solicitud sin pagar: código
                <strong class="cifra"><?= e($pendiente['numero_registro']) ?></strong>
                por <?= e(formatPesos($pendiente['monto_total'])) ?>.
                <a href="<?= APP_URL ?>/portal/solicitud.php?id=<?= (int) $pendiente['id'] ?>">Ver de nuevo</a>.
            </div>
        </div>
    <?php endif; ?>

    <form method="post" id="form-jugada"
          action="<?= APP_URL ?>/portal/guardar_solicitud.php" novalidate>
        <?= csrfField() ?>

        <div class="d-flex align-items-baseline justify-content-between gap-2 mt-3">
            <span class="rotulo">Los <?= $cantidad ?> números de cada jugada</span>
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

        <section class="tarjeta p-3 mb-3">
            <span class="rotulo">Cuánto vas a pagar</span>
            <div class="fw-semibold mb-3">&nbsp;</div>

            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span>Monto por jugada</span>
                <span class="cifra"><?= e(formatPesos($importe)) ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-baseline mb-2">
                <span>Cantidad de jugadas</span>
                <span class="cifra" id="cantidad-jugadas">1</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between align-items-baseline mb-0">
                <span class="fw-semibold">Total a pagar</span>
                <span class="cifra fw-bold fs-5" id="total-a-cobrar"
                      data-monto="<?= e((string) $importe) ?>">
                    <?= e(formatPesos($importe)) ?>
                </span>
            </div>

            <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:.875rem">
                Al confirmar se genera un código, pero <strong>todavía no se cobra
                nada</strong>: tus jugadas quedan a la espera de que Decena de Oro
                registre el pago.
            </div>
        </section>
    </form>

    <div class="accion-fija">
        <div class="accion-fija__interior d-flex align-items-center gap-3">
            <div class="text-nowrap">
                <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                <div class="rotulo" style="font-size:.625rem">números</div>
            </div>
            <button type="submit" form="form-jugada" id="btn-confirmar"
                    class="btn btn-primary flex-grow-1" disabled>
                <i class="bi bi-check-lg"></i>
                <span id="btn-confirmar-texto">Generar código</span>
            </button>
        </div>
    </div>

    <!-- Plantilla para "Agregar otra jugada": numeros.js clona esto y
         reemplaza __INDICE__ por la posicion real. -->
    <template id="plantilla-grupo-jugada">
        <?php $dibujarGrupo('__INDICE__', array_fill(0, $cantidad, '')); ?>
    </template>
</main>

<?php
flushOld();
require __DIR__ . '/../includes/foot.php';