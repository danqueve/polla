<?php
/**
 * Auditoria: quien cargo que y cuando, en una sola linea de tiempo.
 * Exclusiva del admin, tanto en la pantalla como en el service.
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

$eventos = $reporte->auditoria($filtro);

$iconos = [
    'jugada'  => ['bi-ticket-perforated', ''],
    'sorteo'  => ['bi-dice-5',            'evento__marca--sorteo'],
    'cliente' => ['bi-person-plus',       'evento__marca--cliente'],
];

$pageTitle  = 'Auditoría · Reportes · ' . APP_NAME;
$navSeccion = 'reportes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/reportes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Reportes
    </a>

    <h1 class="pantalla__titulo">Auditoría</h1>
    <p class="pantalla__bajada">
        Quién cargó qué y cuándo: jugadas, extractos y altas de clientes,
        de lo más nuevo a lo más viejo.
    </p>

    <div class="mt-3">
        <?php
        $accion = APP_URL . '/admin/reportes/auditoria.php';
        require __DIR__ . '/../../includes/reporte_filtros.php';
        ?>
    </div>

    <?php if (!$eventos): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            No hay movimientos que entren en este filtro.
        </div>

    <?php else: ?>

        <div class="tarjeta p-3">
            <?php foreach ($eventos as $ev): ?>
                <?php [$icono, $clase] = $iconos[$ev['tipo']] ?? ['bi-dot', '']; ?>
                <div class="evento">
                    <span class="evento__marca <?= e($clase) ?>">
                        <i class="bi <?= e($icono) ?>"></i>
                    </span>
                    <div class="min-w-0 flex-grow-1">
                        <p class="mb-0" style="font-size:.9375rem"><?= e($ev['detalle']) ?></p>
                        <p class="fila__meta mb-0">
                            <?= e($ev['quien'] ?? 'usuario borrado') ?>
                            <?php if ($ev['rol']): ?>
                                · <?= e(\Polla\Services\UsuarioService::ROLES[$ev['rol']] ?? $ev['rol']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <span class="evento__cuando text-nowrap">
                        <?= e(formatFechaHora($ev['cuando'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="tabla-pista">
            Se muestran los <?= count($eventos) ?> movimientos más recientes del filtro.
        </p>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
