<?php
/**
 * Resumen de un ciclo: pozo, jugadas, sorteos cargados y, si hubo
 * ganador, quien gano y cuanto le toco.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ciclo = $id > 0 ? $ciclos->buscarPorId($id) : $ciclos->obtenerCicloActivo();

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/ciclos/index.php');
    exit;
}

$cicloId   = (int) $ciclo['id'];
$resumen   = $ciclos->resumen($cicloId);
$lista     = $sorteos->listarPorCiclo($cicloId);
$ganadores = $sorteos->ganadoresDeCiclo($cicloId);
$jugadas   = JugadaService::crearDesde($db)->listarPorCiclo($cicloId);

$abierto   = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;
$arrastre  = (float) ($ciclo['monto_arrastrado'] ?? 0);

// Numeros que ya salieron en la semana, para marcar los aciertos parciales
// de cada jugada en el listado.
$salidos = [];
foreach ($lista as $sorteo) {
    foreach ($sorteo['numeros'] as $numero) {
        $salidos[$numero] = true;
    }
}

$pageTitle  = 'Ciclo ' . (int) $ciclo['numero'] . ' · ' . APP_NAME;
$navSeccion = 'ciclos';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/ciclos/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Todos los ciclos
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-3">
        <div>
            <span class="rotulo">Ciclo <?= (int) $ciclo['numero'] ?></span>
            <h1 class="pantalla__titulo"><?= e(CicloService::rotulo($ciclo)) ?></h1>
        </div>
        <?php if ($abierto): ?>
            <span class="etiqueta etiqueta--verde">Abierto</span>
        <?php elseif ($ciclo['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
            <span class="etiqueta etiqueta--oro">Con ganador</span>
        <?php else: ?>
            <span class="etiqueta etiqueta--gris">Sin ganador</span>
        <?php endif; ?>
    </div>

    <!-- Pozo -->
    <section class="pozo mb-3">
        <div class="pozo__rotulo mb-1">
            <?= $abierto ? 'Pozo acumulado' : 'Pozo al cierre' ?>
        </div>
        <div class="pozo__monto"><?= e(formatPesos($ciclo['monto_acumulado'] ?? 0)) ?></div>

        <?php if ($arrastre > 0): ?>
            <div class="mt-2" style="color:rgba(255,255,255,.72);font-size:.8125rem">
                Incluye <?= e(formatPesos($arrastre)) ?> que venian de la semana anterior.
            </div>
        <?php endif; ?>

        <?php if ((float) ($ciclo['monto_pagado'] ?? 0) > 0): ?>
            <div class="mt-2" style="color:#f2d382;font-size:.8125rem">
                <i class="bi bi-check-circle-fill"></i>
                Liquidado el <?= e(formatFechaHora($ciclo['fecha_liquidacion'])) ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="row g-2 mb-4">
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor"><?= (int) $resumen['jugadas_total'] ?></div>
                <div class="metrica__rotulo">Jugadas</div>
            </div>
        </div>
        <div class="col-4">
            <div class="metrica">
                <div class="metrica__valor"><?= count($lista) ?>/5</div>
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

    <!-- Ganadores -->
    <?php if ($ganadores): ?>
        <span class="rotulo d-block mb-2">
            <?= count($ganadores) === 1 ? 'Ganador' : 'Ganadores' ?>
        </span>

        <?php foreach ($ganadores as $ganador): ?>
            <article class="tarjeta tarjeta--realce p-3 mb-2" style="border-color:#e8d6a4">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo">
                            <i class="bi bi-trophy-fill" style="color:var(--oro)"></i>
                            <?= e($ganador['cliente_nombre']) ?>
                        </p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($ganador['nro_cliente']) ?></span>
                            <?php if ($ganador['telefono']): ?>
                                · <?= e($ganador['telefono']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="fila__meta">
                            Jugada #<?= (int) $ganador['jugada_id'] ?>
                            · sorteo del <?= e(formatFecha($ganador['sorteo_fecha'])) ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <div class="rotulo">Le toca</div>
                        <div class="cifra fw-bold fs-4" style="color:var(--oro)">
                            <?= e(formatPesos($ganador['monto_premio'])) ?>
                        </div>
                    </div>
                </div>

                <div class="bolillas mt-2">
                    <?php foreach ($ganador['numeros'] as $numero): ?>
                        <span class="bolilla bolilla--acertada"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (count($ganadores) > 1): ?>
            <p class="fila__meta mb-4">
                El pozo de <?= e(formatPesos($ciclo['monto_pagado'])) ?> se dividio
                en partes iguales entre los <?= count($ganadores) ?> ganadores.
            </p>
        <?php else: ?>
            <div class="mb-4"></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Sorteos -->
    <span class="rotulo d-block mb-2">Sorteos de la semana</span>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta mb-4">
            <i class="bi bi-dice-5" aria-hidden="true"></i>
            Todavia no se cargo ningun extracto.
        </div>
    <?php else: ?>
        <div class="mb-4">
            <?php foreach ($lista as $sorteo): ?>
                <div class="fila">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <p class="fila__titulo"><?= e(formatFechaDia($sorteo['fecha'])) ?></p>
                        <?php if ((int) $sorteo['ganadores_total'] > 0): ?>
                            <span class="etiqueta etiqueta--oro text-nowrap">
                                <i class="bi bi-trophy-fill"></i> Corto la semana
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bolillas mt-2">
                        <?php foreach ($sorteo['numeros'] as $numero): ?>
                            <span class="bolilla"><?= e(num2($numero)) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Jugadas con sus aciertos -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Jugadas del ciclo</span>
        <span class="fila__meta">verde = ya salio</span>
    </div>

    <?php if (!$jugadas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            No hay jugadas en este ciclo.
        </div>
    <?php else: ?>
        <?php foreach ($jugadas as $jugada): ?>
            <?php
            $aciertos = 0;
            foreach ($jugada['numeros'] as $numero) {
                if (isset($salidos[$numero])) {
                    $aciertos++;
                }
            }
            ?>
            <article class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($jugada['cliente_nombre']) ?></p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($jugada['nro_cliente']) ?></span>
                            · <?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <?php if ($jugada['estado'] === 'ganadora'): ?>
                            <span class="etiqueta etiqueta--oro">Ganadora</span>
                        <?php elseif ($jugada['estado'] === 'perdedora'): ?>
                            <span class="etiqueta etiqueta--gris">Perdio</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta--verde">Activa</span>
                        <?php endif; ?>
                        <div class="fila__meta mt-1">
                            <?= $aciertos ?>/<?= count($jugada['numeros']) ?> salidos
                        </div>
                    </div>
                </div>

                <div class="bolillas mt-2">
                    <?php foreach ($jugada['numeros'] as $numero): ?>
                        <span class="bolilla <?= isset($salidos[$numero]) ? 'bolilla--acertada' : '' ?>">
                            <?= e(num2($numero)) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
