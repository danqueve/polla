<?php
/** Cuantas jugadas cargo cada cliente por ciclo, con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireAdmin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$filas = $reporte->jugadasPorCliente($filtro);

$totalJugadas = 0;
foreach ($filas as $f) {
    $totalJugadas += (int) $f['cantidad'];
}

$pageTitle        = 'Jugadas por Cliente · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Jugadas por Cliente';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Jugadas por Cliente', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Jugadas por Cliente</h1>
            <p class="g-page-subtitle">
                <?= count($filas) ?> filas (cliente x ciclo) · <?= $totalJugadas ?> jugadas en total
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <?php if ($filas): ?>
                <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'clientes'])) ?>"
                   class="g-btn g-btn--primary">
                    <i class="bi bi-download me-1"></i> Exportar CSV
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtros -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <?php
            $accion = APP_URL . '/admin/reportes/clientes.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <!-- Tabla -->
    <div class="g-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-people me-1"></i>
                Cantidad de Jugadas por Cliente
            </h2>
        </div>

        <div class="g-card__body p-0" style="overflow:hidden">
            <?php if (!$filas): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-people fs-1 d-block mb-3 text-muted"></i>
                    No hay jugadas que coincidan con los filtros aplicados.
                </div>
            <?php else: ?>
                <div class="tabla-scroll">
                    <table class="tabla-reporte">
                        <thead>
                            <tr>
                                <th>Ciclo</th>
                                <th>Cliente</th>
                                <th class="text-end">Cantidad de Jugadas</th>
                                <th class="text-end">Importe Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filas as $f): ?>
                                <?php $cicloRotulo = CicloService::rotulo([
                                    'fecha_inicio' => $f['fecha_inicio'],
                                    'fecha_fin'    => $f['fecha_fin'],
                                ]); ?>
                                <tr>
                                    <td class="fw-bold">
                                        #<?= (int) $f['ciclo_numero'] ?>
                                        <span class="text-muted fw-normal small d-block">
                                            <?= e(CicloService::TIPOS[$f['ciclo_tipo']] ?? $f['ciclo_tipo']) ?> · <?= e($cicloRotulo) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($f['cliente']) ?></div>
                                        <div class="small text-muted">N° <?= e($f['nro_cliente']) ?></div>
                                    </td>
                                    <td class="text-end fw-bold"><?= (int) $f['cantidad'] ?></td>
                                    <td class="text-end text-muted"><?= e(formatPesos($f['importe'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="tabla-pista px-3 pb-2 mb-0 d-md-none">
                    <i class="bi bi-arrow-left-right"></i> Deslizá para ver todas las columnas.
                </p>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
