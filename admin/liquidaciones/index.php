<?php
/**
 * Liquidar comisiones [Fase 11]: un referidor por fila, con su saldo
 * pendiente y un boton de liquidar. Exclusivo del administrador.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireAdmin();

$referidores = (new ComisionService(getPDO()))->listarTodosLosReferidores();
$conSaldo    = array_filter($referidores, static fn($r) => (float) $r['saldo_pendiente'] > 0);
$sinSaldo    = array_filter($referidores, static fn($r) => (float) $r['saldo_pendiente'] <= 0);

$pageTitle  = 'Liquidaciones · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/referidos/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Referidos
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Liquidaciones</h1>
    <p class="pantalla__bajada">Pagá el saldo pendiente de cada vendedor o supervisor</p>

    <?php if (!$conSaldo): ?>
        <div class="vacio tarjeta mt-3">
            <i class="bi bi-cash-coin" aria-hidden="true"></i>
            No hay saldo pendiente de nadie por ahora.
        </div>
    <?php else: ?>
        <div class="mt-3 mb-4">
            <?php foreach ($conSaldo as $r): ?>
                <div class="fila">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="min-w-0">
                            <p class="fila__titulo">
                                <a href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>"
                                   class="text-decoration-none" style="color:inherit">
                                    <?= e($r['nombre']) ?>
                                </a>
                            </p>
                            <p class="fila__meta">
                                <?= $r['tipo'] === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
                                · <?= (int) $r['referidos_total'] ?> <?= (int) $r['referidos_total'] === 1 ? 'referido' : 'referidos' ?>
                            </p>
                        </div>
                        <span class="cifra fw-bold fs-5 text-nowrap"><?= e(formatPesos($r['saldo_pendiente'])) ?></span>
                    </div>
                    <form method="post" action="<?= APP_URL ?>/admin/liquidaciones/guardar.php"
                          onsubmit="return confirm('¿Liquidar <?= e(formatPesos($r['saldo_pendiente'])) ?> a <?= e(addslashes($r['nombre'])) ?>?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="tipo" value="<?= e($r['tipo']) ?>">
                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-cash-coin"></i> Liquidar
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($sinSaldo): ?>
        <span class="rotulo d-block mb-2">Sin saldo pendiente</span>
        <?php foreach ($sinSaldo as $r): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <p class="fila__titulo mb-0"><?= e($r['nombre']) ?></p>
                    <span class="fila__meta"><?= e(formatPesos(0)) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
