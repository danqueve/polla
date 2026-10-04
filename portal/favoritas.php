<?php
/**
 * Mis favoritas: las combinaciones que el cliente guardo para volver a
 * jugarlas sin tipear numero por numero.
 *
 * Una favorita no es una jugada: no cuesta nada ni participa del sorteo.
 * "Jugar" lleva a Armar jugada con las casillas ya completas, y ahi se
 * confirma por el camino de siempre (ver portal/jugar.php).
 *
 * Aca se pueden crear (con las mismas casillas de siempre), renombrar y
 * borrar. Tambien se crean desde una jugada ya jugada (Mis jugadas /
 * Historial) y con el tilde de Armar jugada.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\FavoritaService;
use Polla\Services\ParametroService;

requireCliente();

$db         = getPDO();
$parametros = new ParametroService($db);
$clienteId  = (int) clienteActualId();

$tipoJuego = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;
$cantidad  = $esSabado ? $parametros->numerosPorJugadaSabado() : $parametros->numerosPorJugada();

$servicio  = FavoritaService::crearDesde($db);
$favoritas = $servicio->listar($clienteId, $tipoJuego);
$total     = $servicio->contar($clienteId);
$llena     = $total >= FavoritaService::MAXIMO_POR_CLIENTE;

// Lo que quedo tipeado si el guardado fallo (repetido, nombre largo...).
$numerosPrevios = old('fav_numeros', array_fill(0, $cantidad, ''));
if (!is_array($numerosPrevios) || !$numerosPrevios) {
    $numerosPrevios = array_fill(0, $cantidad, '');
}
$nombrePrevio = (string) old('fav_nombre', '');

$pageTitle   = 'Mis favoritas · ' . APP_NAME;
$navSeccion  = 'favoritas';
$pageScripts = ['numeros.js'];
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Mis favoritas</h1>
    <p class="pantalla__bajada">
        Guardá las combinaciones que jugás seguido y volvé a jugarlas con un toque,
        sin tipear número por número. Tenés <?= $total ?> de <?= FavoritaService::MAXIMO_POR_CLIENTE ?>.
    </p>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= !$esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/favoritas.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">Semana</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/favoritas.php?tipo=<?= CicloService::TIPO_SABADO ?>">Sábado</a>
        </li>
    </ul>

    <?php if (!$favoritas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-star" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">Todavía no tenés favoritas de <?= $esSabado ? 'sábado' : 'la semana' ?></p>
            <p class="fila__meta mb-0">
                Escribí una acá abajo, o tocá «Guardar como favorita» en cualquiera de tus jugadas.
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($favoritas as $fav): ?>
            <article class="tarjeta p-3 mb-3">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <div class="min-w-0">
                        <p class="fila__titulo mb-0">
                            <?= $fav['nombre'] !== null ? e($fav['nombre']) : '<span class="text-secondary">Sin nombre</span>' ?>
                        </p>
                    </div>
                    <form method="post" action="<?= APP_URL ?>/portal/favorita_eliminar.php" class="flex-shrink-0"
                          onsubmit="return confirm('¿Borrar esta favorita? Tus jugadas ya hechas no se tocan.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $fav['id'] ?>">
                        <input type="hidden" name="tipo_juego" value="<?= e($tipoJuego) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Borrar esta favorita">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                    </form>
                </div>

                <div class="bolillas mb-3">
                    <?php foreach ($fav['numeros'] as $n): ?>
                        <span class="bolilla bolilla--chica" style="width:28px;height:28px;font-size:.8125rem"><?= e(num2($n)) ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if (count($fav['numeros']) === $cantidad): ?>
                    <a href="<?= APP_URL ?>/portal/jugar.php?tipo=<?= e($tipoJuego) ?>&amp;favorita=<?= (int) $fav['id'] ?>"
                       class="btn btn-success w-100 mb-2">
                        <i class="bi bi-arrow-repeat"></i> Jugar estos números
                    </a>
                <?php else: ?>
                    <p class="fila__meta mb-2">
                        Esta favorita tiene <?= count($fav['numeros']) ?> números y hoy se juegan <?= $cantidad ?>
                        por jugada, así que no se puede jugar tal cual.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= APP_URL ?>/portal/favorita_renombrar.php" class="d-flex gap-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" value="<?= (int) $fav['id'] ?>">
                    <input type="hidden" name="tipo_juego" value="<?= e($tipoJuego) ?>">
                    <input type="text" name="nombre" class="form-control form-control-sm"
                           value="<?= e((string) $fav['nombre']) ?>" maxlength="40"
                           placeholder="Ponele un nombre (opcional)"
                           aria-label="Nombre de la favorita">
                    <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">Guardar nombre</button>
                </form>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($llena): ?>

        <div class="alert alert-info d-flex align-items-start gap-2" role="note">
            <i class="bi bi-info-circle-fill flex-shrink-0" style="margin-top:.15rem"></i>
            <div>
                Llegaste al máximo de <?= FavoritaService::MAXIMO_POR_CLIENTE ?> favoritas.
                Borrá alguna para poder guardar otra.
            </div>
        </div>

    <?php else: ?>

        <form method="post" id="form-favorita" action="<?= APP_URL ?>/portal/favorita_guardar.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="tipo_juego" value="<?= e($tipoJuego) ?>">
            <input type="hidden" name="volver" value="favoritas">

            <section class="tarjeta p-3 mb-3" data-numeros="jugada" data-repetidos="no">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-1">
                    <span class="rotulo">Nueva favorita</span>
                    <button type="button" class="js-limpiar btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eraser"></i> Limpiar
                    </button>
                </div>
                <p class="form-text mt-0 mb-2">
                    Los <?= $cantidad ?> números, distintos entre 00 y 99. Se salta solo a la casilla siguiente.
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

                <div class="alert alert-danger mt-3 mb-0 py-2 js-aviso" role="alert" aria-live="polite" hidden></div>

                <label class="form-label mt-3 mb-1" for="fav-nombre">Nombre (opcional)</label>
                <input type="text" id="fav-nombre" name="nombre" class="form-control"
                       value="<?= e($nombrePrevio) ?>" maxlength="40"
                       placeholder="Ej: Mis números de siempre">

                <div class="d-flex align-items-center gap-3 mt-3">
                    <div class="text-nowrap">
                        <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                        <div class="rotulo" style="font-size:.625rem">números</div>
                    </div>
                    <button type="submit" id="btn-confirmar" class="btn btn-primary flex-grow-1" disabled>
                        <i class="bi bi-star-fill"></i> Guardar favorita
                    </button>
                </div>
            </section>
        </form>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../includes/portal_nav.php';
require __DIR__ . '/../includes/foot.php';
