<?php
/**
 * Carga de jugada: cliente + una o varias jugadas + pago con estilo Gentelella.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ClienteService;
use Polla\Services\HorarioCargaService;
use Polla\Services\ParametroService;
use Polla\Services\PromocionService;

requireLogin();

$db         = getPDO();
$parametros = new ParametroService($db);
$ciclos     = new CicloService($db);
$horario    = new HorarioCargaService($parametros);

$tipoJuego = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;

$ciclo       = $ciclos->obtenerCicloParaCarga($tipoJuego);
$clientes    = (new ClienteService($db))->listarActivosParaSelect();
$horaAbierto = $horario->abierto($tipoJuego);

$importe  = $esSabado ? $parametros->importeJugadaSabado() : $parametros->importeJugada();
$reparto  = $parametros->repartir($importe);
$cantidad = $esSabado ? $parametros->numerosPorJugadaSabado() : $parametros->numerosPorJugada();

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

$gruposPrevios = old('grupos', [['numeros' => array_fill(0, $cantidad, '')]]);
if (!$gruposPrevios) {
    $gruposPrevios = [['numeros' => array_fill(0, $cantidad, '')]];
}
$clientePrevio = (int) old('cliente_id', 0);

/** Dibuja un bloque de "una jugada": sus casillas, limpiar y aviso. */
$dibujarGrupo = static function ($indice, array $numeros) use ($cantidad): void {
    ?>
    <div class="g-card p-3 mt-3" data-numeros="jugada" data-repetidos="no">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <div class="fw-bold text-dark">
                <i class="bi bi-ticket-perforated me-1 text-primary"></i>
                Jugada <span class="js-grupo-numero"><?= is_int($indice) ? $indice + 1 : '' ?></span>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="js-limpiar g-btn g-btn--outline g-btn--sm">
                    <i class="bi bi-eraser"></i> Limpiar
                </button>
                <button type="button" class="js-quitar-grupo g-btn g-btn--outline g-btn--sm text-danger border-0" hidden
                        aria-label="Quitar esta jugada">
                    <i class="bi bi-x-lg" aria-hidden="true"></i> Quitar
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

$pageTitle        = 'Cargar Jugada · ' . APP_NAME;
$navSeccion       = 'jugadas';
$pageSectionTitle = 'Cargar Jugada';
$bodyClass        = 'con-accion-fija';
$pageScripts      = ['promociones.js', 'numeros.js'];
$breadcrumb       = [
    ['label' => 'Jugadas', 'url' => APP_URL . '/admin/jugadas/index.php'],
    ['label' => 'Cargar Jugada', 'url' => '']
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
                <span class="g-badge g-badge--blue">Ciclo <?= (int) $ciclo['numero'] ?></span>
                <?php if ($ciclo['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
                    <span class="g-badge g-badge--warning">Próximo <?= $esSabado ? 'Sábado' : 'Ciclo' ?></span>
                <?php else: ?>
                    <span class="g-badge g-badge--success">Ciclo Actual</span>
                <?php endif; ?>
            </div>
            <h1 class="g-page-title mt-2">Cargar Nueva Jugada</h1>
            <p class="g-page-subtitle">
                <?= $esSabado ? 'Edición especial de sábados' : 'Semana del ' . e(CicloService::rotulo($ciclo)) ?>
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/jugadas/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Jugadas
            </a>
        </div>
    </div>

    <!-- Pestañas Modalidad (Semanal / Sábados) -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= !$esSabado ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">
                        <i class="bi bi-calendar-week me-1"></i> Juego Semanal (10 Números)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $esSabado ? 'active' : '' ?>"
                       href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>">
                        <i class="bi bi-star me-1"></i> Edición Sábados (5 Números)
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <?php if ($ciclo['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
        <div class="g-alert-banner g-alert-banner--info mb-3">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <?php if ($esSabado): ?>
                    Ya se cargó el primer turno del sábado en curso: esta jugada quedará anotada para el <strong>próximo sábado</strong>.
                <?php else: ?>
                    Ya pasó el corte de carga de esta semana: esta jugada quedará anotada para la <strong>semana que viene</strong>.
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$horaAbierto): ?>
        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-clock-history fs-1 d-block mb-3 text-warning"></i>
            <h4 class="fw-bold text-dark">Horario de carga no disponible</h4>
            <p class="text-muted"><?= e($horario->motivoCerrado($tipoJuego)) ?></p>
        </div>
    <?php elseif (!$clientes): ?>
        <div class="g-card p-5 text-center text-secondary g-animate g-animate-delay-1">
            <i class="bi bi-person-plus fs-1 d-block mb-3 text-muted"></i>
            <h4 class="fw-bold text-dark">No hay clientes activos</h4>
            <p class="text-muted">Necesitas registrar al menos un cliente para asignarle una jugada.</p>
            <div class="mt-3">
                <a href="<?= APP_URL ?>/admin/clientes/form.php" class="g-btn g-btn--primary">
                    <i class="bi bi-plus-lg"></i> Dar de alta un cliente
                </a>
            </div>
        </div>
    <?php else: ?>

        <form method="post" id="form-jugada"
              action="<?= APP_URL ?>/admin/jugadas/guardar.php" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="tipo_juego" value="<?= e($tipoJuego) ?>">

            <div class="row g-4">
                <!-- Columna Izquierda: Cliente y Casillas -->
                <div class="col-12 col-lg-8">

                    <!-- Paso 1: Cliente -->
                    <div class="g-card mb-4 g-animate g-animate-delay-1">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 1</span>
                                Seleccionar Cliente
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <label class="form-label fw-semibold small text-muted" for="cliente_id">Cliente</label>
                            <select class="form-select form-select-lg" id="cliente_id" name="cliente_id"
                                    data-requerido required autofocus>
                                <option value="">Elegí un cliente...</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?= (int) $cliente['id'] ?>"
                                            <?= $clientePrevio === (int) $cliente['id'] ? 'selected' : '' ?>>
                                        <?= e($cliente['nombre']) ?> — N° <?= e($cliente['nro_cliente']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-2">
                                ¿No figura en la lista?
                                <a href="<?= APP_URL ?>/admin/clientes/form.php" target="_blank">
                                    <i class="bi bi-plus-circle me-1"></i>Dar de alta nuevo cliente
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Paso 2: Las jugadas y números -->
                    <div class="g-card mb-4 g-animate g-animate-delay-2">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 2</span>
                                Números de la Jugada (<?= $cantidad ?> números por jugada)
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <p class="text-muted small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                Ingrese números distintos entre 00 y 99. El foco avanza automáticamente al tipear cada número.
                            </p>

                            <div id="grupos-jugada">
                                <?php foreach ($gruposPrevios as $idx => $grupo): ?>
                                    <?php $dibujarGrupo($idx, $grupo['numeros'] ?? []); ?>
                                <?php endforeach; ?>
                            </div>

                            <button type="button" id="btn-agregar-jugada" class="g-btn g-btn--outline w-100 mt-3">
                                <i class="bi bi-plus-lg"></i> Agregar otra jugada para este cliente
                            </button>
                        </div>
                    </div>

                </div>

                <!-- Columna Derecha: Resumen de Pago y Confirmación -->
                <div class="col-12 col-lg-4">

                    <!-- Promoción si aplica -->
                    <?php if (!$esSabado): ?>
                        <div id="promo-sugerida" class="g-card mb-4 g-animate g-animate-delay-2 border-success" hidden
                             data-promos="<?= e(json_encode($promosPorCantidad, JSON_UNESCAPED_UNICODE)) ?>">
                            <div class="g-card__body p-3 bg-success-subtle rounded">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="bi bi-tag-fill fs-4 text-success flex-shrink-0"></i>
                                    <div>
                                        <div id="promo-sugerida-texto" class="fw-bold text-success-emphasis"></div>
                                        <div class="form-check form-switch mt-2 mb-0">
                                            <input class="form-check-input" type="checkbox" role="switch" id="promo-aplicar">
                                            <label class="form-check-label fw-semibold" for="promo-aplicar">Aplicar descuento de promo</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="promocion_id" id="promocion_id" value="">

                    <!-- Paso 3: Pago y Confirmación -->
                    <div class="g-card g-animate g-animate-delay-3">
                        <div class="g-card__header">
                            <h3 class="g-card__title">
                                <span class="g-badge g-badge--blue me-1">Paso 3</span>
                                Resumen de Pago
                            </h3>
                        </div>
                        <div class="g-card__body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Importe unitario:</span>
                                <span class="fw-semibold"><?= e(formatPesos($importe)) ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small">Cantidad jugadas:</span>
                                <span class="fw-bold" id="cantidad-jugadas">1</span>
                            </div>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-3">
                                <span class="fw-bold text-dark fs-6">Total a Cobrar:</span>
                                <span class="fw-extrabold fs-3 text-primary" id="total-a-cobrar"
                                      data-monto="<?= e((string) $importe) ?>">
                                    <?= e(formatPesos($importe)) ?>
                                </span>
                            </div>

                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>Aporte al Pozo (<?= (int) $parametros->porcentajePozo() ?>%):</span>
                                <span><?= e(formatPesos($reparto['pozo'])) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span>Gastos y Comisión:</span>
                                <span><?= e(formatPesos($reparto['gastos'])) ?></span>
                            </div>

                            <div class="g-alert-banner g-alert-banner--warning py-2 px-3 small mb-0">
                                <i class="bi bi-shield-check"></i>
                                <div>Al confirmar, la jugada se registra como <strong>pagada</strong> y suma al pozo.</div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </form>

        <!-- Template para "Agregar otra jugada" -->
        <template id="plantilla-grupo-jugada">
            <?php $dibujarGrupo('__INDICE__', array_fill(0, $cantidad, '')); ?>
        </template>

        <!-- Barra Fija Inferior de Confirmación -->
        <div class="accion-fija">
            <div class="accion-fija__interior d-flex align-items-center gap-3">
                <div class="text-nowrap">
                    <div class="contador" id="contador-numeros">0/<?= $cantidad ?></div>
                    <div class="rotulo" style="font-size:.625rem">números</div>
                </div>
                <button type="submit" form="form-jugada" id="btn-confirmar"
                        class="g-btn g-btn--primary flex-grow-1" disabled>
                    <i class="bi bi-check-lg"></i>
                    <span id="btn-confirmar-texto" data-plantilla="Confirmar y cobrar">Confirmar y cobrar <?= e(formatPesos($importe)) ?></span>
                </button>
            </div>
        </div>

    <?php endif; ?>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
