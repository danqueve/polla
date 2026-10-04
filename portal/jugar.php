<?php
/**
 * El cliente arma su jugada (Fase 6).
 *
 * Mismo widget de siempre (numeros.js: casillas + "Agregar otra
 * jugada"), pero ahora lo completa el cliente sobre su propia sesion
 * en vez de un supervisor tipeandole los numeros. La cantidad de
 * casillas depende de la modalidad (10 semanal, 5 sabados). requireCliente()
 * sin flags exige sesion + activo + estado='aprobado' + clave ya cambiada:
 * un cliente pendiente de aprobacion no llega a esta pantalla, lo
 * manda derecho a portal/pendiente.php.
 *
 * Al confirmar no se cobra nada aca: se genera un codigo y se muestra
 * el monto a pagar. El pago y la confirmacion los hace el staff en
 * admin/solicitudes/.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\FavoritaService;
use Polla\Services\HorarioCargaService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PromocionService;
use Polla\Services\SolicitudService;

requireCliente();

$db         = getPDO();
$parametros = new ParametroService($db);
$horario    = new HorarioCargaService($parametros);
$ciclos     = new CicloService($db, $parametros);
$clienteId  = (int) clienteActualId();

$tipoJuego = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;
$horaAbierto = $horario->abierto($tipoJuego);

// Vista previa de a que ciclo va a ir esta jugada (nunca definitiva: la
// solicitud recien queda asignada de verdad cuando el staff confirme
// el pago -- ver SolicitudService::confirmar()). Sirve para avisar
// "esto va para la semana que viene" antes de que el cliente confirme.
$cicloPreview = $ciclos->obtenerCicloParaCarga($tipoJuego);

$importe  = $esSabado ? $parametros->importeJugadaSabado() : $parametros->importeJugada();
$cantidad = $esSabado ? $parametros->numerosPorJugadaSabado() : $parametros->numerosPorJugada();

// Promociones exclusivas del juego semanal: en sabados ni se calculan.
// Indexadas por cantidad_jugadas, para que promociones.js sugiera el
// paquete sin ninguna consulta extra al cambiar la cantidad de jugadas.
$promosPorCantidad = [];
if (!$esSabado) {
    foreach ((new PromocionService($db))->activasPorCantidad() as $cantidadPromo => $promo) {
        $promosPorCantidad[$cantidadPromo] = [
            'id'               => (int) $promo['id'],
            'precio_total'     => (float) $promo['precio_total'],
            'cantidad_jugadas' => (int) $promo['cantidad_jugadas'],
        ];
    }
}

$pendiente = SolicitudService::crearDesde($db)->pendientePara($clienteId, $tipoJuego);

// Repoblar tras un error de validacion del servidor. Un grupo vacio por
// defecto: lo normal es armar una sola jugada.
$gruposPrevios = old('grupos', [['numeros' => array_fill(0, $cantidad, '')]]);
if (!$gruposPrevios) {
    $gruposPrevios = [['numeros' => array_fill(0, $cantidad, '')]];
}

// ── Volver a jugar lo mismo ──────────────────────────────────
// "Volver a jugar" (?repetir=<jugada>) y "Jugar" en una favorita
// (?favorita=<id>) llegan aca con las casillas ya completas. Solo se
// PRECARGA: no se crea nada, el cliente revisa, puede cambiar numeros o
// sumar jugadas, y confirma por el camino de siempre (mismas
// validaciones, promociones y pago).
//
// Lo que volvio de un error de validacion (old) tiene prioridad: es lo
// que el cliente estaba tipeando. Y solo se carga lo que es SUYO, de
// ESTE juego y con la cantidad de numeros de hoy -- un id ajeno, de
// otro juego o de cuando se jugaban otros numeros deja el formulario
// vacio con un aviso, igual que un id que no existe.
$favoritasSvc = FavoritaService::crearDesde($db);
$favoritas    = $favoritasSvc->listar($clienteId, $tipoJuego);

$origenPrecarga = null;   // 'jugada' | 'favorita'
$nombrePrecarga = null;
$avisoPrecarga  = null;

if (!old('grupos') && (isset($_GET['repetir']) || isset($_GET['favorita']))) {
    if (isset($_GET['repetir'])) {
        $origen         = JugadaService::crearDesde($db)->numerosDeJugadaDelCliente($clienteId, (int) $_GET['repetir']);
        $origenPrecarga = 'jugada';
    } else {
        $origen         = $favoritasSvc->buscar($clienteId, (int) $_GET['favorita']);
        $origenPrecarga = 'favorita';
        $nombrePrecarga = $origen['nombre'] ?? null;
    }

    if (!$origen) {
        $avisoPrecarga = 'No encontramos esa ' . ($origenPrecarga === 'jugada' ? 'jugada' : 'favorita')
            . ' entre las tuyas. Armá la jugada de cero.';
    } elseif ($origen['tipo_juego'] !== $tipoJuego) {
        $avisoPrecarga = 'Esos números son de otro juego. Elegí la pestaña correcta o armá la jugada de cero.';
    } elseif (count($origen['numeros']) !== $cantidad) {
        $avisoPrecarga = 'Esos números eran ' . count($origen['numeros']) . ' y hoy se juegan ' . $cantidad
            . ' por jugada, así que no se pueden cargar tal cual. Armá la jugada de cero.';
    } else {
        $gruposPrevios = [['numeros' => array_map(
            static fn(int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            $origen['numeros']
        )]];
    }

    if ($avisoPrecarga !== null) {
        $origenPrecarga = null;
    }
}

/** Dibuja un bloque de "una jugada": sus 10 casillas, limpiar y aviso. */
$dibujarGrupo = static function ($indice, array $numeros, bool $favorita = false) use ($cantidad): void {
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

        <?php /* Sin id/for a proposito: numeros.js clona este bloque y solo
                 renombra los atributos name, asi que un id se repetiria.
                 El <label> envuelve al input y se asocia solo. */ ?>
        <label class="d-flex align-items-center gap-2 mt-3 mb-0 small">
            <input class="form-check-input mt-0" type="checkbox"
                   name="grupos[<?= e((string) $indice) ?>][favorita]" value="1"
                   <?= $favorita ? 'checked' : '' ?>>
            <span><i class="bi bi-star" aria-hidden="true"></i> Guardar también como favorita</span>
        </label>

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

    <?php if ($cicloPreview['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
        <div class="alert alert-info py-2" style="font-size:.875rem">
            <?php if ($esSabado): ?>
                Ya se cargó el primer turno del sábado en curso: esta jugada va a
                quedar anotada para el próximo sábado.
            <?php else: ?>
                Ya pasó el corte de carga de esta semana: esta jugada va a quedar
                anotada para la semana que viene.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= !$esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/jugar.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">Semana</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/jugar.php?tipo=<?= CicloService::TIPO_SABADO ?>">Sábado</a>
        </li>
    </ul>

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

    <?php if (!$horaAbierto): ?>

        <div class="vacio tarjeta mt-3">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <?= e($horario->motivoCerrado($tipoJuego)) ?>
        </div>

    <?php else: ?>

    <?php if ($avisoPrecarga !== null): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2 mt-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:.15rem"></i>
            <div><?= e($avisoPrecarga) ?></div>
        </div>
    <?php elseif ($origenPrecarga !== null): ?>
        <div class="alert alert-success d-flex align-items-start gap-2 mt-3" role="status">
            <i class="bi bi-arrow-repeat flex-shrink-0" style="margin-top:.15rem"></i>
            <div>
                <?php if ($origenPrecarga === 'favorita'): ?>
                    Cargamos tu favorita<?= $nombrePrecarga !== null ? ' «' . e($nombrePrecarga) . '»' : '' ?>.
                <?php else: ?>
                    Cargamos los números de tu jugada anterior.
                <?php endif; ?>
                Revisalos: podés cambiar lo que quieras o sumar más jugadas, y <strong>todavía no se
                generó ningún código</strong> hasta que toques el botón de abajo.
            </div>
        </div>
    <?php endif; ?>

    <?php if ($favoritas): ?>
        <section class="tarjeta p-3 mt-3" id="panel-favoritas">
            <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                <span class="rotulo"><i class="bi bi-star-fill" aria-hidden="true"></i> Tus favoritas</span>
                <a href="<?= APP_URL ?>/portal/favoritas.php?tipo=<?= e($tipoJuego) ?>" class="small">Administrar</a>
            </div>
            <?php foreach ($favoritas as $fav): ?>
                <div class="fila d-flex align-items-center justify-content-between gap-2">
                    <div class="min-w-0">
                        <?php if ($fav['nombre'] !== null): ?>
                            <p class="fila__titulo mb-1"><?= e($fav['nombre']) ?></p>
                        <?php endif; ?>
                        <div class="bolillas">
                            <?php foreach ($fav['numeros'] as $n): ?>
                                <span class="bolilla bolilla--chica" style="width:26px;height:26px;font-size:.75rem"><?= e(num2($n)) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if (count($fav['numeros']) === $cantidad): ?>
                        <button type="button" class="btn btn-sm btn-outline-success flex-shrink-0"
                                data-usar-numeros="<?= e($fav['canonica']) ?>">
                            Usar
                        </button>
                    <?php else: ?>
                        <span class="fila__meta flex-shrink-0">Tenía <?= count($fav['numeros']) ?> números</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <form method="post" id="form-jugada"
          action="<?= APP_URL ?>/portal/guardar_solicitud.php" novalidate>
        <?= csrfField() ?>
        <input type="hidden" name="tipo_juego" value="<?= e($tipoJuego) ?>">

        <div class="d-flex align-items-baseline justify-content-between gap-2 mt-3">
            <span class="rotulo">Los <?= $cantidad ?> números de cada jugada</span>
        </div>
        <p class="form-text mt-0 mb-0">
            Distintos entre 00 y 99 dentro de cada jugada. Se salta solo a la casilla siguiente.
        </p>

        <div id="grupos-jugada">
            <?php foreach ($gruposPrevios as $idx => $grupo): ?>
                <?php $dibujarGrupo($idx, $grupo['numeros'] ?? [], !empty($grupo['favorita'])); ?>
            <?php endforeach; ?>
        </div>

        <button type="button" id="btn-agregar-jugada" class="btn btn-outline-secondary w-100 mt-2 mb-3">
            <i class="bi bi-plus-lg"></i> Agregar otra jugada
        </button>

        <?php if (!$esSabado): ?>
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
        <?php endif; ?>
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

    <?php endif; ?>
</main>

<?php
flushOld();
require __DIR__ . '/../includes/foot.php';