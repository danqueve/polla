<?php
/**
 * Vista global de referidos [Fase 11]: todos los vendedores y
 * supervisores que tienen codigo, cuantos referidos captaron y cuanto
 * acumularon de comision. Exclusivo del administrador -- el supervisor
 * tiene su propia vista, mios.php, acotada a el mismo.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireAdmin();

$referidores = (new ComisionService(getPDO()))->listarTodosLosReferidores();

$pageTitle  = 'Referidos · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Referidos</h1>
    <p class="pantalla__bajada">Vendedores y supervisores, quién refirió a quién</p>

    <?php if (!$referidores): ?>
        <div class="vacio tarjeta mt-3">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            Todavía no hay vendedores ni supervisores con código de referido activo.
        </div>
    <?php else: ?>
        <div class="mt-3">
            <?php foreach ($referidores as $r): ?>
                <a class="fila" href="<?= APP_URL ?>/admin/referidos/ver.php?tipo=<?= e($r['tipo']) ?>&id=<?= (int) $r['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <p class="fila__titulo"><?= e($r['nombre']) ?></p>
                            <p class="fila__meta">
                                <span class="etiqueta <?= $r['tipo'] === 'vendedor' ? 'etiqueta--verde' : 'etiqueta--oro' ?>">
                                    <?= $r['tipo'] === 'vendedor' ? 'Vendedor' : 'Supervisor' ?>
                                </span>
                                · <span class="cifra"><?= e($r['codigo_referido']) ?></span>
                            </p>
                            <p class="fila__meta">
                                <?= (int) $r['referidos_total'] ?> <?= (int) $r['referidos_total'] === 1 ? 'referido' : 'referidos' ?>
                                · <?= (int) $r['jugadas_total'] ?> <?= (int) $r['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                            </p>
                        </div>
                        <div class="text-end text-nowrap">
                            <div class="cifra fw-bold <?= (float) $r['saldo_pendiente'] > 0 ? '' : 'text-secondary' ?>">
                                <?= e(formatPesos($r['saldo_pendiente'])) ?>
                            </div>
                            <div class="fila__meta">saldo</div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-column gap-2 mt-4">
        <a href="<?= APP_URL ?>/admin/vendedores/index.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-person-badge"></i> Vendedores
        </a>
        <a href="<?= APP_URL ?>/admin/liquidaciones/index.php" class="btn btn-outline-secondary w-100">
            <i class="bi bi-cash-coin"></i> Liquidar comisiones
        </a>
    </div>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
