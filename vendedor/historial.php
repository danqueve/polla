<?php
/** Historial de liquidaciones (pagos) ya cobradas por el vendedor. */
require_once __DIR__ . '/../config/vendedor.php';

use Polla\Services\LiquidacionService;

requireVendedor();

$vendedor      = vendedorActual();
$liquidaciones = LiquidacionService::crearDesde(getPDO())->historial('vendedor', (int) $vendedor['id']);

$totalCobrado = 0.0;
foreach ($liquidaciones as $l) {
    $totalCobrado += (float) $l['monto'];
}

$pageTitle  = 'Historial de pagos · ' . APP_NAME;
$navSeccion = 'vendedor-historial';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/vendedor_cabecera.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/vendedor/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Mi panel
    </a>

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Historial de pagos</h1>
    <p class="pantalla__bajada">Comisiones que ya te liquidó Decena de Oro</p>

    <div class="row g-2 mt-3 mb-4">
        <div class="col-12">
            <div class="metrica">
                <div class="metrica__valor metrica__valor--oro"><?= e(formatPesos($totalCobrado)) ?></div>
                <div class="metrica__rotulo">Cobrado en total</div>
            </div>
        </div>
    </div>

    <?php if (!$liquidaciones): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">Todavía no cobraste ninguna liquidación</p>
            <p class="fila__meta mb-0">
                Cuando Decena de Oro te liquide tu saldo, el pago va a
                aparecer acá.
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($liquidaciones as $l): ?>
            <div class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e(formatFechaHora($l['fecha'])) ?></p>
                        <?php if ($l['liquidado_por_nombre']): ?>
                            <p class="fila__meta">Liquidado por <?= e($l['liquidado_por_nombre']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="cifra fw-bold text-nowrap"><?= e(formatPesos($l['monto'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
