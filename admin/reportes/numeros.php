<?php
/**
 * Estadisticas de numeros: frecuencia, calientes/frios y atraso de cada
 * numero 00-99 en los sorteos cargados. Publica dentro del staff -no es
 * un dato de ningun cliente ni de quien cargo el extracto- asi que la
 * ven admin y supervisor por igual, sin acotar por alcance. Se
 * recalcula en cada carga: sin cache, el volumen es chico.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireLogin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::total((int) $usuario['id']); // a propósito: dato público del sorteo
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$stats = $reporte->estadisticasNumeros($filtro);

/** Porcentaje de aparición -> clase de intensidad del mapa de calor. */
$claseCalor = static function (float $porcentaje): string {
    if ($porcentaje <= 0)  return '';
    if ($porcentaje <= 15) return 'tablero__celda--n1';
    if ($porcentaje <= 35) return 'tablero__celda--n2';
    return 'tablero__celda--n3';
};

$maxApariciones = $stats['numeros'] ? max(array_column($stats['numeros'], 'apariciones')) : 0;

$pageTitle  = 'Estadísticas de números · Reportes · ' . APP_NAME;
$navSeccion = 'reportes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/reportes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Reportes
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Estadísticas de números</h1>
    <p class="pantalla__bajada">Frecuencia y atraso de cada número dentro del período filtrado</p>

    <?php
    $ocultarFiltrosPersonales = true;
    $accion = APP_URL . '/admin/reportes/numeros.php';
    require __DIR__ . '/../../includes/reporte_filtros.php';
    ?>

    <?php if ($stats['total_sorteos'] === 0): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-dice-5" aria-hidden="true"></i>
            No hay sorteos cargados que entren en este filtro.
        </div>

    <?php else: ?>

        <!-- KPIs -->
        <div class="row g-2 mt-3 mb-3">
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= $stats['total_sorteos'] ?></div>
                    <div class="tarjeta-dato__rotulo">Sorteos analizados</div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= num2($stats['calientes'][0]['numero']) ?></div>
                    <div class="tarjeta-dato__rotulo">Número más caliente</div>
                    <div class="tarjeta-dato__pie"><?= $stats['calientes'][0]['apariciones'] ?> apariciones</div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <div class="tarjeta-dato__valor"><?= num2($stats['frios'][0]['numero']) ?></div>
                    <div class="tarjeta-dato__rotulo">Número más frío</div>
                    <div class="tarjeta-dato__pie"><?= $stats['frios'][0]['apariciones'] ?> apariciones</div>
                </div>
            </div>
            <div class="col-6">
                <div class="tarjeta-dato">
                    <?php if ($stats['atrasados']): ?>
                        <div class="tarjeta-dato__valor" style="color:var(--oro)">
                            <?= num2($stats['atrasados'][0]['numero']) ?>
                        </div>
                        <div class="tarjeta-dato__rotulo">Más atrasado</div>
                        <div class="tarjeta-dato__pie"><?= $stats['atrasados'][0]['atraso'] ?> sorteos sin salir</div>
                    <?php else: ?>
                        <div class="tarjeta-dato__valor" style="color:var(--oro)"><?= count($stats['nunca']) ?></div>
                        <div class="tarjeta-dato__rotulo">Nunca salieron en el período</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mapa de calor 00-99 -->
        <span class="rotulo d-block mb-2">Mapa de frecuencia</span>
        <div class="tablero mb-2" role="img"
             aria-label="Mapa de frecuencia de los números 00 a 99 en el período filtrado">
            <?php foreach ($stats['numeros'] as $f): ?>
                <?php
                $clases = trim($claseCalor($f['porcentaje']) . ($f['apariciones'] === $maxApariciones && $maxApariciones > 0 ? ' tablero__celda--record' : ''));
                ?>
                <div class="tablero__celda <?= e($clases) ?>"
                     title="<?= e(num2($f['numero'])) ?>: <?= $f['apariciones'] ?> <?= $f['apariciones'] === 1 ? 'vez' : 'veces' ?> (<?= number_format($f['porcentaje'], 1) ?>%)<?= $f['atraso'] === null ? ' · nunca en este período' : ' · ' . $f['atraso'] . ' sorteos sin salir' ?>">
                    <?= num2($f['numero']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="leyenda-calor mb-4">
            <span><span class="leyenda-calor__marca" style="background:#eceae3"></span> nunca</span>
            <span><span class="leyenda-calor__marca" style="background:var(--verde-claro)"></span> frío (≤15%)</span>
            <span><span class="leyenda-calor__marca" style="background:var(--verde)"></span> esperado</span>
            <span><span class="leyenda-calor__marca" style="background:var(--verde-oscuro)"></span> caliente (&gt;35%)</span>
            <span><span class="leyenda-calor__marca" style="background:#fff;outline:2px solid var(--oro);outline-offset:-2px"></span> el más caliente</span>
        </div>

        <!-- Top 10 calientes -->
        <span class="rotulo d-block mb-2">Top 10 calientes</span>
        <div class="mb-4">
            <?php foreach ($stats['calientes'] as $f): ?>
                <?php $ancho = $maxApariciones > 0 ? max(1.5, $f['apariciones'] / $maxApariciones * 100) : 0; ?>
                <div class="barra-ciclo">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span class="fw-semibold cifra"><?= num2($f['numero']) ?></span>
                        <span class="barra-ciclo__cifra">
                            <?= $f['apariciones'] ?> · <?= number_format($f['porcentaje'], 1) ?>%
                        </span>
                    </div>
                    <div class="barra-ciclo__pista">
                        <div class="barra-ciclo__dato" style="width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Top 10 fríos -->
        <span class="rotulo d-block mb-2">Top 10 fríos</span>
        <div class="mb-4">
            <?php foreach ($stats['frios'] as $f): ?>
                <?php $ancho = $maxApariciones > 0 ? max(1.5, $f['apariciones'] / $maxApariciones * 100) : 0; ?>
                <div class="barra-ciclo">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span class="fw-semibold cifra"><?= num2($f['numero']) ?></span>
                        <span class="barra-ciclo__cifra">
                            <?= $f['apariciones'] ?> · <?= number_format($f['porcentaje'], 1) ?>%
                        </span>
                    </div>
                    <div class="barra-ciclo__pista">
                        <div class="barra-ciclo__dato barra-ciclo__dato--tenue"
                             style="width: <?= number_format($ancho, 2, '.', '') ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Atraso -->
        <span class="rotulo d-block mb-2">Más atrasados</span>
        <?php if (!$stats['atrasados']): ?>
            <p class="fila__meta mb-4">Ningún número salió todavía en este período.</p>
        <?php else: ?>
            <div class="tabla-scroll mb-2">
                <table class="tabla-reporte">
                    <thead>
                        <tr><th>Número</th><th>Apariciones</th><th>Atraso</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats['atrasados'] as $f): ?>
                            <tr>
                                <td class="cifra"><?= num2($f['numero']) ?></td>
                                <td><?= $f['apariciones'] ?></td>
                                <td><?= $f['atraso'] ?> sorteos</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($stats['nunca']): ?>
            <p class="fila__meta mb-4">
                <?= count($stats['nunca']) ?> números no salieron ni una vez en este período:
                <?= implode(' ', array_map('num2', $stats['nunca'])) ?>
            </p>
        <?php endif; ?>

        <!-- Detalle completo -->
        <span class="rotulo d-block mb-2">Detalle completo (00 a 99)</span>
        <div class="tabla-scroll mb-3">
            <table class="tabla-reporte">
                <thead>
                    <tr><th>Número</th><th>Apariciones</th><th>Porcentaje</th><th>Atraso</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['numeros'] as $f): ?>
                        <tr>
                            <td class="cifra"><?= num2($f['numero']) ?></td>
                            <td><?= $f['apariciones'] ?></td>
                            <td><?= number_format($f['porcentaje'], 1) ?>%</td>
                            <td><?= $f['atraso'] === null ? '—' : $f['atraso'] . ' sorteos' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'numeros'])) ?>"
           class="btn btn-primary w-100 mb-3">
            <i class="bi bi-download"></i> Descargar CSV
        </a>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
