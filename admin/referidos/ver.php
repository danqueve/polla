<?php
/** Detalle de un referidor puntual (vendedor o supervisor) [Fase 11]. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;
use Polla\Services\LiquidacionService;

requireAdmin();

$tipo = $_GET['tipo'] ?? '';
$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!in_array($tipo, ['vendedor', 'supervisor'], true) || $id <= 0) {
    setFlash('danger', 'Referidor inválido.');
    header('Location: ' . APP_URL . '/admin/referidos/index.php');
    exit;
}

$comisiones = new ComisionService(getPDO());
$nombre     = $comisiones->nombreDe($tipo, $id);

if (!$nombre) {
    setFlash('danger', 'Ese ' . $tipo . ' no existe.');
    header('Location: ' . APP_URL . '/admin/referidos/index.php');
    exit;
}

$referidos     = $comisiones->listarReferidos($tipo, $id);
$saldo         = $comisiones->saldoPendiente($tipo, $id);
$acreditado    = $comisiones->totalAcreditado($tipo, $id);
$liquidaciones = LiquidacionService::crearDesde(getPDO())->historial($tipo, $id, 20);

$pageTitle  = e($nombre) . ' · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/referidos/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Referidos
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-baseline justify-content-between gap-2 mb-1">
        <h1 class="pantalla__titulo"><?= e($nombre) ?></h1>
        <span class="etiqueta <?= $tipo === 'vendedor' ? 'etiqueta--verde' : 'etiqueta--oro' ?>">
            <?= $tipo === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
        </span>
    </div>

    <section class="pozo mb-4 mt-3">
        <div class="pozo__rotulo mb-1">Saldo pendiente</div>
        <div class="pozo__monto"><?= e(formatPesos($saldo)) ?></div>
        <div class="mt-2" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            <?= e(formatPesos($acreditado)) ?> acreditados en total desde siempre
        </div>
    </section>

    <?php if ($saldo > 0): ?>
        <form method="post" action="<?= APP_URL ?>/admin/liquidaciones/guardar.php" class="mb-4"
              onsubmit="return confirm('¿Liquidar <?= e(formatPesos($saldo)) ?> a <?= e(addslashes($nombre)) ?>?')">
            <?= csrfField() ?>
            <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <input type="hidden" name="volver_a" value="ver">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-cash-coin"></i> Liquidar <?= e(formatPesos($saldo)) ?>
            </button>
        </form>
    <?php endif; ?>

    <div class="row g-2 mb-4">
        <div class="col-6">
            <div class="metrica">
                <div class="metrica__valor"><?= count($referidos) ?></div>
                <div class="metrica__rotulo">Referidos</div>
            </div>
        </div>
        <div class="col-6">
            <div class="metrica">
                <div class="metrica__valor metrica__valor--oro">
                    <?= array_sum(array_column($referidos, 'jugadas_total')) ?>
                </div>
                <div class="metrica__rotulo">Jugadas generadas</div>
            </div>
        </div>
    </div>

    <span class="rotulo d-block mb-2">Sus referidos</span>

    <?php if (!$referidos): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-people" aria-hidden="true"></i>
            Todavía no tiene referidos.
        </div>
    <?php else: ?>
        <?php foreach ($referidos as $r): ?>
            <div class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($r['nombre']) ?></p>
                        <p class="fila__meta">
                            N° <?= e($r['nro_cliente']) ?> · desde <?= e(formatFecha($r['fecha_alta'])) ?>
                        </p>
                    </div>
                    <span class="etiqueta <?= (int) $r['jugadas_total'] > 0 ? 'etiqueta--verde' : 'etiqueta--gris' ?> text-nowrap">
                        <?= (int) $r['jugadas_total'] ?> <?= (int) $r['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($liquidaciones): ?>
        <span class="rotulo d-block mb-2 mt-4">Liquidaciones anteriores</span>
        <?php foreach ($liquidaciones as $l): ?>
            <div class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e(formatFechaHora($l['fecha'])) ?></p>
                        <?php if ($l['liquidado_por_nombre']): ?>
                            <p class="fila__meta">por <?= e($l['liquidado_por_nombre']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="cifra fw-bold text-nowrap"><?= e(formatPesos($l['monto'])) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
