<?php
/** Historial de ganadores, con filtros y exportable. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireLogin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$ganadores = $reporte->ganadores($filtro);

$totalPagado = 0.0;
foreach ($ganadores as $g) {
    $totalPagado += (float) $g['monto_premio'];
}

$pageTitle  = 'Ganadores · Reportes · ' . APP_NAME;
$navSeccion = 'reportes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/reportes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Reportes
    </a>

    <h1 class="pantalla__titulo">Ganadores</h1>
    <p class="pantalla__bajada">
        <?php if (!$alcance->esAdmin()): ?>
            De las jugadas que cargaste vos
        <?php else: ?>
            Historial completo de premios pagados
        <?php endif; ?>
    </p>

    <div class="row g-2 mt-3 mb-3">
        <div class="col-6">
            <div class="tarjeta-dato">
                <div class="tarjeta-dato__valor"><?= count($ganadores) ?></div>
                <div class="tarjeta-dato__rotulo">Premios</div>
            </div>
        </div>
        <div class="col-6">
            <div class="tarjeta-dato">
                <div class="tarjeta-dato__valor" style="color:var(--oro)">
                    <?= e(formatPesos($totalPagado)) ?>
                </div>
                <div class="tarjeta-dato__rotulo">Pagado en total</div>
            </div>
        </div>
    </div>

    <?php
    $accion = APP_URL . '/admin/reportes/ganadores.php';
    require __DIR__ . '/../../includes/reporte_filtros.php';
    ?>

    <?php if (!$ganadores): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-trophy" aria-hidden="true"></i>
            Todavía no hay ganadores que entren en este filtro.
        </div>

    <?php else: ?>

        <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'ganadores'])) ?>"
           class="btn btn-primary w-100 mb-3">
            <i class="bi bi-download"></i> Descargar CSV (<?= count($ganadores) ?> filas)
        </a>

        <?php foreach ($ganadores as $g): ?>
            <article class="tarjeta p-3 mb-2" style="border-color:#e8d6a4">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo">
                            <i class="bi bi-trophy-fill" style="color:var(--oro)"></i>
                            <?= e($g['cliente']) ?>
                        </p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($g['nro_cliente']) ?></span>
                            · DNI <?= e($g['dni']) ?>
                            <?php if ($g['telefono']): ?>
                                · <?= e($g['telefono']) ?>
                            <?php endif; ?>
                        </p>
                        <p class="fila__meta">
                            Ciclo <?= (int) $g['ciclo'] ?>
                            · sorteo del <?= e(formatFecha($g['sorteo'])) ?>
                            · jugada #<?= (int) $g['jugada_id'] ?>
                        </p>
                        <p class="fila__meta">
                            Cargada por <?= e($g['cargado_por'] ?? '—') ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <div class="rotulo">Premio</div>
                        <div class="cifra fw-bold fs-4" style="color:var(--oro)">
                            <?= e(formatPesos($g['monto_premio'])) ?>
                        </div>
                    </div>
                </div>

                <div class="bolillas mt-2">
                    <?php foreach ($g['numeros'] as $numero): ?>
                        <span class="bolilla bolilla--acertada"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
