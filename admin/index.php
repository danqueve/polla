<?php
/** Tablero: estado del ciclo abierto, pozo y ultimas jugadas. */
require_once __DIR__ . '/../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\SorteoService;

requireLogin();

$db         = getPDO();
$ciclos     = new CicloService($db);
$parametros = new ParametroService($db);
$jugadas    = JugadaService::crearDesde($db);
$sorteos    = SorteoService::crearDesde($db);

$ciclo   = $ciclos->obtenerCicloActivo();
$cicloId = (int) $ciclo['id'];
$resumen = $ciclos->resumen($cicloId);
$ultimas = $jugadas->ultimas(5);

$extractos = $sorteos->listarPorCiclo($cicloId);
$arrastre  = (float) ($ciclo['monto_arrastrado'] ?? 0);

// Dias habiles del ciclo ya pasados que todavia no tienen extracto.
$sinCargar = 0;
$fechasHechas = array_column($extractos, 'fecha');
$dia = new DateTimeImmutable($ciclo['fecha_inicio']);
$fin = new DateTimeImmutable($ciclo['fecha_fin']);
$hoy = new DateTimeImmutable('today');
while ($dia <= $fin) {
    if ($dia <= $hoy && !in_array($dia->format('Y-m-d'), $fechasHechas, true)) {
        $sinCargar++;
    }
    $dia = $dia->modify('+1 day');
}

$pageTitle  = 'Tablero · ' . APP_NAME;
$navSeccion = 'tablero';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-3">
        <div>
            <span class="rotulo">Ciclo <?= (int) $ciclo['numero'] ?></span>
            <h1 class="pantalla__titulo"><?= e(CicloService::rotulo($ciclo)) ?></h1>
        </div>
        <span class="etiqueta etiqueta--verde">Abierto</span>
    </div>

    <!-- Pozo: la cifra que todos quieren ver primero -->
    <section class="pozo mb-3">
        <div class="pozo__rotulo mb-1">Pozo acumulado</div>
        <div class="pozo__monto"><?= e(formatPesos($ciclo['monto_acumulado'] ?? 0)) ?></div>
        <div class="mt-2" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            <?php if ($arrastre > 0): ?>
                Incluye <?= e(formatPesos($arrastre)) ?> que arrastro de la semana anterior
            <?php else: ?>
                <?= (int) $parametros->porcentajePozo() ?>% de cada jugada pagada de esta semana
            <?php endif; ?>
        </div>
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
                <div class="metrica__valor"><?= count($extractos) ?>/5</div>
                <div class="metrica__rotulo">Sorteos</div>
            </div>
        </div>
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor metrica__valor--oro"><?= e(formatPesos($resumen['recaudado'])) ?></div>
                <div class="metrica__rotulo">Recaudado</div>
            </div>
        </div>
    </div>

    <?php if ($sinCargar > 0): ?>
        <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:.15rem"></i>
            <div>
                <?= $sinCargar === 1
                    ? 'Falta cargar el extracto de un sorteo de esta semana.'
                    : "Faltan cargar los extractos de $sinCargar sorteos de esta semana." ?>
                Hasta que no se carguen, no se cotejan las jugadas.
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-2 mb-4">
        <div class="col-12">
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="btn btn-primary w-100">
                <i class="bi bi-plus-lg"></i> Cargar una jugada
            </a>
        </div>
        <div class="col-12">
            <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php"
               class="btn <?= $sinCargar > 0 ? 'btn-primary' : 'btn-outline-secondary' ?> w-100">
                <i class="bi bi-dice-5"></i> Cargar el sorteo de la Nocturna
            </a>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Ultimas jugadas</span>
        <a href="<?= APP_URL ?>/admin/jugadas/index.php" class="small text-decoration-none">Ver todas</a>
    </div>

    <?php if (!$ultimas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            Todavia no hay jugadas cargadas.
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
        <a href="<?= APP_URL ?>/admin/ciclos/ver.php?id=<?= $cicloId ?>"
           class="btn btn-outline-secondary w-100">
            <i class="bi bi-clipboard-data"></i> Resumen completo del ciclo
        </a>
        <a href="<?= APP_URL ?>/admin/reportes/index.php"
           class="btn btn-outline-secondary w-100">
            <i class="bi bi-bar-chart-line"></i>
            <?= isAdmin() ? 'Reportes y recaudación' : 'Reportes de lo que cargaste' ?>
        </a>
    </div>

</main>

<?php
require __DIR__ . '/../includes/bottom_nav.php';
require __DIR__ . '/../includes/foot.php';
