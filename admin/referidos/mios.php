<?php
/**
 * "Mis referidos" [Fase 11]: la vista del supervisor sobre sus propios
 * referidos, jugadas y saldo -- misma informacion que ve un vendedor en
 * su panel, pero dentro del panel admin porque el supervisor ya vive
 * ahi para todo lo demas. No puede liquidar (eso es exclusivo del
 * administrador, en admin/liquidaciones/).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireLogin();

$usuario    = currentUser();
$comisiones = new ComisionService(getPDO());

// Un admin no tiene codigo de referido (Fase 11 solo se lo da a
// supervisores). No se bloquea la pantalla -- simplemente no hay nada
// que mostrar.
$stmt = getPDO()->prepare('SELECT codigo_referido FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $usuario['id']]);
$codigo = $stmt->fetchColumn();

$referidos = $codigo ? $comisiones->listarReferidos('supervisor', (int) $usuario['id']) : [];
$saldo     = $codigo ? $comisiones->saldoPendiente('supervisor', (int) $usuario['id']) : 0.0;
$link      = $codigo ? APP_URL . '/registro.php?ref=' . $codigo : '';

$pageTitle   = 'Mis referidos · ' . APP_NAME;
$navSeccion  = '';
$pageScripts = ['copiar.js'];
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Mis referidos</h1>

    <?php if (!$codigo): ?>
        <div class="vacio tarjeta mt-3">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            Tu usuario todavía no tiene código de referido. Hablá con el administrador.
        </div>
    <?php else: ?>

        <section class="pozo mb-3 mt-3">
            <div class="pozo__rotulo mb-1">Tu saldo acumulado</div>
            <div class="pozo__monto"><?= e(formatPesos($saldo)) ?></div>
        </section>

        <div class="tarjeta p-3 mb-4">
            <span class="rotulo d-block mb-2">Tu código de referido</span>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="cifra fs-4"><?= e($codigo) ?></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-copiar="<?= e($codigo) ?>">
                    <i class="bi bi-clipboard"></i> Copiar código
                </button>
            </div>
            <div class="alias-transferencia text-break mt-3" style="font-size:.8125rem;letter-spacing:0">
                <?= e($link) ?>
            </div>
            <button type="button" class="btn btn-sm btn-primary w-100 mt-2" data-copiar="<?= e($link) ?>">
                <i class="bi bi-clipboard"></i> Copiar link
            </button>
        </div>

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

        <span class="rotulo d-block mb-2">Tus referidos</span>

        <?php if (!$referidos): ?>
            <div class="vacio tarjeta">
                <i class="bi bi-people" aria-hidden="true"></i>
                Todavía no tenés referidos.
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

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
