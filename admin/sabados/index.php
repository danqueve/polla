<?php
/** Tablero del juego de sabados: estado del ciclo abierto, pozo y ultimas jugadas. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PozoService;
use Polla\Services\SorteoService;

requireLogin();

$db         = getPDO();
$ciclos     = new CicloService($db);
$parametros = new ParametroService($db);
$jugadas    = JugadaService::crearDesde($db);
$sorteos    = SorteoService::crearDesde($db);

$ciclo   = $ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);
$cicloId = (int) $ciclo['id'];
$resumen = $ciclos->resumen($cicloId);
$ultimas = $jugadas->ultimas(5, CicloService::TIPO_SABADO);

$turnos   = $sorteos->listarPorCiclo($cicloId);
$arrastre = (float) ($ciclo['monto_arrastrado'] ?? 0);

$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);
$premioBase   = $parametros->premioBaseSabado();
$pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
$subsidio     = max(0.0, $premioBase - $pozoReal);

$faltan = 5 - count($turnos);

$pageTitle  = 'Sábados · ' . APP_NAME;
$navSeccion = 'sabados';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-3">
        <div>
            <span class="rotulo">Sábado <?= (int) $ciclo['numero'] ?></span>
            <h1 class="pantalla__titulo"><?= e(CicloService::rotulo($ciclo)) ?></h1>
        </div>
        <span class="etiqueta etiqueta--verde">Abierto</span>
    </div>

    <section class="pozo mb-3">
        <div class="pozo__rotulo mb-1">Pozo de sábados</div>
        <div class="pozo__monto"><?= e(formatPesos($pozoMostrado)) ?></div>
        <div class="mt-2" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            <?php if ($arrastre > 0): ?>
                Incluye <?= e(formatPesos($arrastre)) ?> que arrastró del sábado anterior
            <?php else: ?>
                <?= (int) $parametros->porcentajePozo() ?>% de cada jugada de sábado pagada
            <?php endif; ?>
        </div>

        <?php if ($subsidio > 0): ?>
            <div class="pozo__desglose mt-3">
                <i class="bi bi-info-circle-fill"></i>
                De los cuales <?= e(formatPesos($pozoReal)) ?> son reales ·
                Decena de Oro está cubriendo <?= e(formatPesos($subsidio)) ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-2 mb-3">
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor"><?= (int) $resumen['jugadas_total'] ?></div>
                <div class="metrica__rotulo">Jugadas</div>
            </div>
        </div>
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor"><?= count($turnos) ?>/5</div>
                <div class="metrica__rotulo">Turnos</div>
            </div>
        </div>
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor metrica__valor--oro"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                <div class="metrica__rotulo">Recaudado</div>
            </div>
        </div>
    </div>

    <?php if ($faltan > 0 && $turnos): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:.15rem"></i>
            <div>
                Faltan <?= $faltan ?> <?= $faltan === 1 ? 'turno' : 'turnos' ?> de los 5 de este sábado.
                Hasta que no se carguen, no se cotejan las jugadas contra ellos.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-2 mb-4">
        <div class="col-12">
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= CicloService::TIPO_SABADO ?>"
               class="btn btn-primary w-100">
                <i class="bi bi-plus-lg"></i> Cargar una jugada de sábado
            </a>
        </div>
        <div class="col-12">
            <a href="<?= APP_URL ?>/admin/sabados/sorteo_nuevo.php"
               class="btn <?= $faltan > 0 ? 'btn-primary' : 'btn-outline-secondary' ?> w-100">
                <i class="bi bi-dice-5"></i> Cargar el próximo turno
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Últimas jugadas de sábado</span>
        <a href="<?= APP_URL ?>/admin/sabados/ver.php?id=<?= $cicloId ?>" class="small text-decoration-none">Ver ciclo</a>
    </div>

    <?php if (!$ultimas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            Todavía no hay jugadas de sábado cargadas.
        </div>
    <?php else: ?>
        <?php foreach ($ultimas as $jugada): ?>
            <div class="fila">
                <div class="d-flex justify-content-between align-items-baseline gap-2">
                    <p class="fila__titulo"><?= e($jugada['cliente_nombre']) ?></p>
                    <span class="fila__meta text-nowrap"><?= e(formatFechaHora($jugada['fecha_carga'])) ?></span>
                </div>
                <p class="fila__meta mb-2">
                    N° <?= e($jugada['nro_cliente']) ?> · <?= e(formatPesos($jugada['importe'])) ?>
                </p>
                <div class="bolillas">
                    <?php foreach ($jugada['numeros'] as $numero): ?>
                        <span class="bolilla"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="d-flex flex-column gap-2 mt-3">
        <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-calendar-week"></i> Historial de sábados
        </a>
        <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-arrow-left"></i> Volver al tablero semanal
        </a>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
