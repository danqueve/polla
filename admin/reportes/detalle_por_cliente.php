<?php
/**
 * Detalle de Jugadas por Cliente: un bloque por cliente con TODAS sus
 * jugadas (numeros incluidos), para control -- a diferencia de
 * jugadas.php (lista plana, mezcladas por fecha) y de clientes.php
 * (solo cuenta, sin numeros).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ReporteService;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;

requireAdmin();

$db      = getPDO();
$usuario = currentUser();

$alcance = AlcanceReporte::desdeSesion((int) $usuario['id'], (string) $usuario['rol']);
$reporte = new ReporteService($db, $alcance);
$filtro  = FiltroReporte::desdeGet($_GET, $alcance);

$jugadas = $reporte->jugadas($filtro, 5000);
$grupos  = $reporte->agruparPorCliente($jugadas);

$pageTitle        = 'Detalle de Jugadas por Cliente · ' . APP_NAME;
$navSeccion       = 'reportes';
$pageSectionTitle = 'Detalle de Jugadas por Cliente';
$breadcrumb       = [
    ['label' => 'Reportes', 'url' => APP_URL . '/admin/reportes/index.php'],
    ['label' => 'Detalle de Jugadas por Cliente', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Detalle de Jugadas por Cliente</h1>
            <p class="g-page-subtitle">
                <?= count($grupos) ?> clientes · <?= count($jugadas) ?> jugadas en total
                <?php if (count($jugadas) >= 5000): ?>
                    <span class="text-warning fw-semibold d-block mt-1">Se muestran las 5000 más recientes. Acote el rango para ver el resto.</span>
                <?php endif; ?>
            </p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?= APP_URL ?>/admin/reportes/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Reportes
            </a>
            <?php if ($grupos): ?>
                <a href="<?= APP_URL ?>/admin/reportes/detalle_por_cliente_imprimir.php?<?= e($filtro->comoQueryString()) ?>"
                   class="g-btn g-btn--outline" target="_blank" rel="noopener">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                </a>
                <a href="<?= APP_URL ?>/admin/reportes/exportar.php?<?= e($filtro->comoQueryString(['que' => 'detalle_cliente'])) ?>"
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
            $accion = APP_URL . '/admin/reportes/detalle_por_cliente.php';
            require __DIR__ . '/../../includes/reporte_filtros.php';
            ?>
        </div>
    </div>

    <?php if (!$grupos): ?>
        <div class="g-card g-animate g-animate-delay-2">
            <div class="p-5 text-center text-secondary">
                <i class="bi bi-people fs-1 d-block mb-3 text-muted"></i>
                No hay jugadas que coincidan con los filtros aplicados.
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($grupos as $i => $g): ?>
            <div class="g-card g-list-card mb-4 g-animate <?= $i < 3 ? 'g-animate-delay-' . ($i + 2) : '' ?>">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-person-circle me-1"></i>
                        <?= e($g['cliente']) ?>
                        <span class="text-muted fw-normal fs-7 ms-1">N° <?= e($g['nro_cliente']) ?> · DNI <?= e($g['dni']) ?></span>
                    </h2>
                    <span class="g-badge g-badge--info">
                        <?= $g['cantidad'] ?> <?= $g['cantidad'] === 1 ? 'jugada' : 'jugadas' ?> · <?= e(formatPesos($g['importe'])) ?>
                    </span>
                </div>
                <div class="g-card__body p-0" style="overflow:hidden">
                    <div class="tabla-scroll">
                        <table class="tabla-reporte">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Ciclo</th>
                                    <th>Números Jugados</th>
                                    <th class="text-end">Importe</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-end">Premio</th>
                                    <th>Cargado Por</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($g['jugadas'] as $j): ?>
                                    <tr>
                                        <td class="text-nowrap small text-muted"><?= e(formatFechaHora($j['fecha_carga'])) ?></td>
                                        <td class="fw-bold">#<?= (int) $j['ciclo'] ?></td>
                                        <td>
                                            <div class="bolillas">
                                                <?php foreach ($j['numeros'] as $num): ?>
                                                    <span class="bolilla bolilla--chica" style="width:24px;height:24px;font-size:.65rem;line-height:24px"><?= num2($num) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td class="text-end fw-bold"><?= e(formatPesos($j['importe'])) ?></td>
                                        <td class="text-center">
                                            <?php if ($j['estado'] === 'ganadora'): ?>
                                                <span class="g-badge g-badge--warning">Ganadora</span>
                                            <?php elseif ($j['estado'] === 'perdedora'): ?>
                                                <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                                            <?php else: ?>
                                                <span class="g-badge g-badge--success">Activa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold texto-oro">
                                            <?= $j['monto_premio'] !== null ? e(formatPesos($j['monto_premio'])) : '—' ?>
                                        </td>
                                        <td class="small text-muted"><?= e($j['cargado_por'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
