<?php
/**
 * Panel del vendedor [Fase 11]: su link de referido, saldo acumulado y
 * listado de a quien refirio. Lo primero que ve es el saldo (por que
 * entra) y su codigo/link para compartir (que es su unico trabajo).
 */
require_once __DIR__ . '/../config/vendedor.php';

use Polla\Services\ComisionService;

requireVendedor();

$vendedor   = vendedorActual();
$comisiones = new ComisionService(getPDO());

$saldo     = $comisiones->saldoPendiente('vendedor', (int) $vendedor['id']);
$referidos = $comisiones->listarReferidos('vendedor', (int) $vendedor['id']);
$link      = APP_URL . '/registro.php?ref=' . $vendedor['codigo_referido'];

$pageTitle  = 'Mi panel · ' . APP_NAME;
$navSeccion = 'vendedor-inicio';
$pageScripts = ['copiar.js'];
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/vendedor_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="visually-hidden">Mi panel de vendedor</h1>

    <!-- Saldo: lo primero, lo mas grande -->
    <section class="pozo pozo-cliente mb-4 text-center">
        <div class="pozo__rotulo mb-2">Tu saldo acumulado</div>
        <div class="pozo__monto"><?= e(formatPesos($saldo)) ?></div>
        <div class="mt-3" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            Comisión de tus referidos, todavía sin cobrar
        </div>
    </section>

    <!-- Codigo + link: lo segundo, es tu unica herramienta de trabajo -->
    <section class="codigo-solicitud mb-3">
        <div class="codigo-solicitud__rotulo mb-2">Tu código de referido</div>
        <div class="codigo-solicitud__valor"><?= e($vendedor['codigo_referido']) ?></div>
        <button type="button" class="btn btn-sm btn-outline-light codigo-solicitud__copiar"
                data-copiar="<?= e($vendedor['codigo_referido']) ?>">
            <i class="bi bi-clipboard"></i> Copiar código
        </button>
    </section>

    <div class="tarjeta p-3 mb-4">
        <span class="rotulo d-block mb-2">O compartí el link directo</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="alias-transferencia text-break" style="font-size:.8125rem;letter-spacing:0">
                <?= e($link) ?>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-primary w-100 mt-3" data-copiar="<?= e($link) ?>">
            <i class="bi bi-clipboard"></i> Copiar link
        </button>
        <p class="form-text mt-2 mb-0">
            Quien se registre con este link queda como tu referido. Cobrás
            comisión cuando juega, no por el solo hecho de registrarse.
        </p>
    </div>

    <!-- Cantidad de referidos -->
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

    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Tus referidos</span>
    </div>

    <?php if (!$referidos): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">Todavía no tenés referidos</p>
            <p class="fila__meta mb-0">
                Compartí tu código o tu link para que empiecen a aparecer acá.
            </p>
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

    <a href="<?= APP_URL ?>/vendedor/historial.php" class="btn btn-outline-secondary w-100 mt-4">
        <i class="bi bi-clock-history"></i> Ver historial de pagos
    </a>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
