<?php
/**
 * Resumen de un ciclo de sabado: pozo, jugadas, turnos cargados y, si
 * hubo ganador, quien gano y cuanto le toco.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PortalService;
use Polla\Services\PozoService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

$id    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$ciclo = $id > 0 ? $ciclos->buscarPorId($id) : $ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);

if (!$ciclo || $ciclo['tipo'] !== CicloService::TIPO_SABADO) {
    setFlash('danger', 'Ese sábado no existe.');
    header('Location: ' . APP_URL . '/admin/sabados/ciclos.php');
    exit;
}

$cicloId   = (int) $ciclo['id'];
$resumen   = $ciclos->resumen($cicloId);
$lista     = $sorteos->listarPorCiclo($cicloId);
$ganadores = $sorteos->ganadoresDeCiclo($cicloId);
$jugadas   = JugadaService::crearDesde($db)->listarPorCiclo($cicloId);

$abierto      = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;
$conGanador   = $ciclo['estado'] === CicloService::ESTADO_CON_GANADOR;
$esProgramado = $ciclo['estado'] === CicloService::ESTADO_PROGRAMADO;
$arrastre     = (float) ($ciclo['monto_arrastrado'] ?? 0);
$pozoReal     = (float) ($ciclo['monto_acumulado'] ?? 0);

if ($abierto) {
    $premioBase   = (new ParametroService($db))->premioBaseSabado();
    $pozoMostrado = PozoService::montoAMostrar($pozoReal, $premioBase);
    $pisoAplicado = $premioBase;
} elseif ($conGanador) {
    $pozoMostrado = (float) ($ciclo['monto_pagado'] ?? 0);
    $pisoAplicado = $ciclo['monto_piso_aplicado'] !== null ? (float) $ciclo['monto_piso_aplicado'] : 0.0;
} else {
    $pozoMostrado = $pozoReal;
    $pisoAplicado = 0.0;
}
$subsidio = max(0.0, $pisoAplicado - $pozoReal);

// Numeros que ya salieron en los turnos cargados, para marcar los
// aciertos parciales de cada jugada en el listado y armar el ranking.
$salidos  = PortalService::numerosSalidos($lista);
$ranking  = PortalService::ranking($jugadas, $salidos);

$pageTitle  = 'Sábado ' . (int) $ciclo['numero'] . ' · ' . APP_NAME;
$navSeccion = 'sabados';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/sabados/ciclos.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Todos los sábados
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-3">
        <div>
            <span class="rotulo">Sábado <?= (int) $ciclo['numero'] ?></span>
            <h1 class="pantalla__titulo"><?= e(CicloService::rotulo($ciclo)) ?></h1>
        </div>
        <?php if ($esProgramado): ?>
            <span class="etiqueta etiqueta--gris">
                <i class="bi bi-clock-history"></i> Próximo sábado (en formación)
            </span>
        <?php elseif ($abierto): ?>
            <span class="etiqueta etiqueta--verde">Abierto</span>
        <?php elseif ($conGanador): ?>
            <span class="etiqueta etiqueta--oro">Con ganador</span>
        <?php else: ?>
            <span class="etiqueta etiqueta--gris">Sin ganador</span>
        <?php endif; ?>
    </div>

    <section class="pozo mb-3">
        <div class="pozo__rotulo mb-1">
            <?php if ($abierto): ?>
                Pozo acumulado
            <?php elseif ($esProgramado): ?>
                Pozo acumulado (sábado en formación)
            <?php else: ?>
                Pozo al cierre
            <?php endif; ?>
        </div>
        <div class="pozo__monto"><?= e(formatPesos($pozoMostrado)) ?></div>

        <?php if ($arrastre > 0): ?>
            <div class="mt-2" style="color:rgba(255,255,255,.72);font-size:.8125rem">
                Incluye <?= e(formatPesos($arrastre)) ?> que venían del sábado anterior.
            </div>
        <?php endif; ?>

        <?php if ((float) ($ciclo['monto_pagado'] ?? 0) > 0): ?>
            <div class="mt-2" style="color:#f2d382;font-size:.8125rem">
                <i class="bi bi-check-circle-fill"></i>
                Liquidado el <?= e(formatFechaHora($ciclo['fecha_liquidacion'])) ?>
            </div>
        <?php endif; ?>

        <?php if (isAdmin() && $subsidio > 0): ?>
            <div class="pozo__desglose mt-3">
                <i class="bi bi-info-circle-fill"></i>
                De los cuales <?= e(formatPesos($pozoReal)) ?> son reales ·
                Decena de Oro <?= $conGanador ? 'cubrió' : 'está cubriendo' ?>
                <?= e(formatPesos($subsidio)) ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (isAdmin() && ($abierto || $conGanador)): ?>
        <div class="tarjeta p-3 mb-4">
            <span class="rotulo d-block mb-2">Desglose del pozo</span>
            <div class="desglose-pozo__fila">
                <span>Acumulado real (jugadas de sábado)</span>
                <span class="cifra"><?= e(formatPesos($pozoReal)) ?></span>
            </div>
            <div class="desglose-pozo__fila">
                <span>Piso garantizado<?= $abierto ? ' (vigente)' : ' (aplicado al liquidar)' ?></span>
                <span class="cifra"><?= e(formatPesos($pisoAplicado)) ?></span>
            </div>
            <div class="desglose-pozo__fila desglose-pozo__fila--total">
                <span><?= $conGanador ? 'Pagado' : 'A pagar si hay ganador' ?></span>
                <span class="cifra"><?= e(formatPesos($pozoMostrado)) ?></span>
            </div>
            <?php if ($subsidio > 0): ?>
                <div class="desglose-pozo__fila desglose-pozo__fila--subsidio">
                    <span>Subsidiado por Decena de Oro</span>
                    <span class="cifra"><?= e(formatPesos($subsidio)) ?></span>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
                            · sábado <?= e(formatFecha($ganador['sorteo_fecha'])) ?>
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
                El pozo de <?= e(formatPesos($ciclo['monto_pagado'])) ?> se dividió
                en partes iguales entre los <?= count($ganadores) ?> ganadores.
            </p>
        <?php else: ?>
            <div class="mb-4"></div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Turnos -->
    <span class="rotulo d-block mb-2">Turnos del sábado</span>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta mb-4">
            <i class="bi bi-dice-5" aria-hidden="true"></i>
            Todavía no se cargó ningún turno.
        </div>
    <?php else: ?>
        <div class="mb-4">
            <?php foreach ($lista as $sorteo): ?>
                <div class="fila">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <p class="fila__titulo">Turno <?= (int) $sorteo['turno'] ?> de 5</p>
                        <?php if ((int) $sorteo['ganadores_total'] > 0): ?>
                            <span class="etiqueta etiqueta--oro text-nowrap">
                                <i class="bi bi-trophy-fill"></i> Cortó el día
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="bolillas mt-2">
                        <?php foreach ($sorteo['numeros'] as $numero): ?>
                            <span class="bolilla"><?= e(num2($numero)) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <?php if (isAdmin() && $abierto): ?>
                        <form method="post" action="<?= APP_URL ?>/admin/sabados/sorteo_eliminar.php"
                              class="mt-2 text-end"
                              onsubmit="return confirm('¿Borrar el turno <?= (int) $sorteo['turno'] ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $sorteo['id'] ?>">
                            <input type="hidden" name="volver_a" value="<?= (int) $ciclo['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i> Borrar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Ranking por aciertos acumulados -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Ranking del sábado</span>
        <span class="fila__meta">quién va anotando más</span>
    </div>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta mb-4">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
            Todavía no hay resultados para armar el ranking: falta cargar el primer turno.
        </div>
    <?php elseif (!$ranking): ?>
        <div class="vacio tarjeta mb-4">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
            No hay jugadas en este sábado.
        </div>
    <?php else: ?>
        <div class="mb-4">
            <?php foreach ($ranking as $i => $puesto): ?>
                <div class="fila">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="cifra fw-bold text-secondary" style="width:1.75rem;flex-shrink:0">
                                <?= $i === 0 ? '🏆' : ($i + 1) . '°' ?>
                            </span>
                            <div class="min-w-0">
                                <p class="fila__titulo mb-0"><?= e($puesto['nombre']) ?></p>
                                <p class="fila__meta mb-0">N° <?= e($puesto['nro_cliente']) ?></p>
                            </div>
                        </div>
                        <span class="etiqueta <?= $puesto['aciertos'] > 0 ? 'etiqueta--verde' : 'etiqueta--gris' ?> text-nowrap">
                            <?= (int) $puesto['aciertos'] ?>/<?= (int) $puesto['total'] ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Jugadas con sus aciertos -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Jugadas del sábado</span>
        <span class="fila__meta">verde = ya salió</span>
    </div>

    <?php if (!$jugadas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            No hay jugadas en este sábado.
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
                            <span class="etiqueta etiqueta--gris">Perdió</span>
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
