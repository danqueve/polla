<?php
/** Corrección de los números de una jugada antes de que entre en sorteo. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;

requireAdmin();

$id       = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$volverA  = isset($_GET['volver_a']) ? (int) $_GET['volver_a'] : 0;
$tipoCrudo = $_GET['tipo'] ?? null;
$tipo     = is_string($tipoCrudo) && array_key_exists($tipoCrudo, CicloService::TIPOS)
    ? $tipoCrudo
    : CicloService::TIPO_SEMANAL;
$busqueda = trim((string) ($_GET['q'] ?? ''));
$pagina   = max(1, (int) ($_GET['pagina'] ?? 1));

$destinoListado = static function (int $cicloId, string $tipoJuego, string $q, int $numeroPagina): string {
    $params = ['tipo' => $tipoJuego];
    if ($cicloId > 0) {
        $params['ciclo'] = $cicloId;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($numeroPagina > 1) {
        $params['pagina'] = $numeroPagina;
    }

    return APP_URL . '/admin/jugadas/index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$db      = getPDO();
$jugadas = JugadaService::crearDesde($db);
$jugada  = $id > 0 ? $jugadas->buscarPorId($id) : null;

if (!$jugada) {
    setFlash('danger', 'La jugada no existe o todavía está pendiente de cobro.');
    header('Location: ' . $destinoListado($volverA, $tipo, $busqueda, $pagina));
    exit;
}

// La URL de retorno se deriva siempre de la jugada real, no de parámetros
// manipulables del navegador.
$tipoJuego = $jugada['tipo_juego'];
$cicloId   = (int) $jugada['ciclo_id'];
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;
$parametros = new ParametroService($db);
$cantidad   = $esSabado
    ? $parametros->numerosPorJugadaSabado()
    : $parametros->numerosPorJugada();

$puedeEditar = (bool) ($jugada['puede_editar'] ?? false);
$bloqueo     = (string) ($jugada['bloqueo_jugada'] ?? 'No se puede editar esta jugada.');
$numerosPrevios = old('numeros', array_map('strval', $jugada['numeros']));
$volverUrl = $destinoListado($cicloId, $tipoJuego, $busqueda, $pagina);

$estadoCiclo = (string) ($jugada['ciclo_estado'] ?? '');
if ($estadoCiclo === CicloService::ESTADO_PROGRAMADO) {
    $claseEstado  = 'g-badge--info';
    $rotuloEstado = $esSabado ? 'Próximo sábado' : 'Próximo ciclo';
} elseif ($estadoCiclo === CicloService::ESTADO_ABIERTO) {
    $claseEstado  = 'g-badge--success';
    $rotuloEstado = 'Ciclo abierto';
} else {
    $claseEstado  = 'g-badge--danger';
    $rotuloEstado = CicloService::ESTADOS[$estadoCiclo] ?? 'Ciclo no disponible';
}

$pageTitle        = 'Editar Jugada · ' . APP_NAME;
$navSeccion       = 'jugadas';
$pageSectionTitle = 'Editar Jugada';
$bodyClass        = $puedeEditar ? 'con-accion-fija' : '';
$pageScripts      = $puedeEditar ? ['numeros.js'] : [];
$breadcrumb       = [
    ['label' => 'Jugadas', 'url' => $volverUrl],
    ['label' => 'Editar jugada', 'url' => ''],
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
                <span class="g-badge g-badge--blue"><?= $esSabado ? 'Sábados' : 'Semanal' ?></span>
                <span class="g-badge <?= $claseEstado ?>">
                    <?= e($rotuloEstado) ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2">Editar jugada de <?= e($jugada['cliente_nombre']) ?></h1>
            <p class="g-page-subtitle">
                N° <?= e($jugada['nro_cliente']) ?> · <?= $esSabado ? 'Sábado' : 'Ciclo' ?> <?= (int) $jugada['ciclo_numero'] ?>
            </p>
        </div>

        <div>
            <a href="<?= e($volverUrl) ?>" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Jugadas
            </a>
        </div>
    </div>

    <?php if (!$puedeEditar): ?>

        <div class="g-card p-5 text-center text-secondary g-animate">
            <i class="bi bi-lock-fill fs-1 d-block mb-3 text-warning"></i>
            <h4 class="fw-bold text-dark">Esta jugada no se puede editar</h4>
            <p class="text-muted mb-0"><?= e($bloqueo) ?></p>
        </div>

    <?php else: ?>

        <form method="post" id="form-jugada"
              data-numeros="jugada" data-repetidos="no"
              action="<?= APP_URL ?>/admin/jugadas/editar_guardar.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $jugada['id'] ?>">
            <input type="hidden" name="volver_a" value="<?= $cicloId ?>">
            <input type="hidden" name="tipo" value="<?= e($tipoJuego) ?>">
            <input type="hidden" name="q" value="<?= e($busqueda) ?>">
            <input type="hidden" name="pagina" value="<?= $pagina ?>">

            <div class="row g-4">
                <div class="col-12 col-lg-8">
                    <div class="g-card g-animate g-animate-delay-1">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <i class="bi bi-pencil-square me-1"></i>
                                Elegí los <?= $cantidad ?> números corregidos
                            </h3>
                            <button type="button" class="js-limpiar g-btn g-btn--outline g-btn--sm">
                                <i class="bi bi-eraser"></i> Limpiar
                            </button>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                Los números deben ser distintos, entre 00 y 99. La corrección queda registrada en auditoría.
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
                                               aria-label="Número <?= $i + 1 ?> de <?= $cantidad ?>">
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <div class="alert alert-danger mt-3 mb-0 py-2 js-aviso" role="alert" aria-live="polite" hidden></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-4">
                    <div class="g-card g-animate g-animate-delay-2">
                        <div class="g-card__header">
                            <h3 class="g-card__title"><i class="bi bi-shield-check me-1"></i> Regla de seguridad</h3>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-0">
                                Solo se pueden corregir jugadas activas antes de que se cargue el primer sorteo o turno del ciclo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">números</div>
                </div>
                <button type="submit" form="form-jugada" id="btn-confirmar"
                        class="g-btn g-btn--primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i> Guardar corrección
                </button>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
