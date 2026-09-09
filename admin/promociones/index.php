<?php
/** Paquetes promocionales. Solo el administrador entra aca. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\PromocionService;

requireAdmin();

$promociones = new PromocionService(getPDO());
$lista       = $promociones->listar();

$pageTitle  = 'Promociones · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/configuracion/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Configuración
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pantalla__titulo">Promociones</h1>
            <p class="pantalla__bajada">Paquetes de jugadas con descuento</p>
        </div>
        <a href="<?= APP_URL ?>/admin/promociones/form.php" class="btn btn-primary btn-sm text-nowrap">
            <i class="bi bi-plus-lg"></i> Nueva
        </a>
    </div>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-box-seam" aria-hidden="true"></i>
            Todavía no hay promociones cargadas.
            <div class="mt-3">
                <a href="<?= APP_URL ?>/admin/promociones/form.php" class="btn btn-sm btn-primary">
                    Crear la primera
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($lista as $promo): ?>
            <?php
            $porJugada = (float) $promo['precio_total'] / max(1, (int) $promo['cantidad_jugadas']);
            ?>
            <a class="fila" href="<?= APP_URL ?>/admin/promociones/form.php?id=<?= (int) $promo['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo">
                            <?= (int) $promo['cantidad_jugadas'] ?> jugadas por
                            <?= e(formatPesos($promo['precio_total'])) ?>
                        </p>
                        <p class="fila__meta">
                            <?= e(formatPesos($porJugada)) ?> por jugada
                            · creada <?= e(formatFecha($promo['fecha_creacion'])) ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <?php if ((int) $promo['activa'] === 1): ?>
                            <span class="etiqueta etiqueta--verde">Activa</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta--gris">Inactiva</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="alert alert-info mt-4 mb-0" role="note">
        <strong>Cómo se aplican.</strong> Cuando la cantidad de jugadas que
        se está cargando (por el staff o por el propio cliente) coincide
        con una promoción activa, el sistema la sugiere — quien carga
        decide si la aplica o cobra precio de lista. Como mucho puede
        haber una promoción activa por cada cantidad de jugadas.
    </div>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
