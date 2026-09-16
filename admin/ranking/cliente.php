<?php
/**
 * Detalle de un cliente dentro de un ciclo: sus jugadas de ese ciclo,
 * con los numeros ya salidos marcados. Se llega desde una fila del
 * ranking (admin/ranking/index.php).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\PortalService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

$cicloId   = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
$clienteId = isset($_GET['cliente']) ? (int) $_GET['cliente'] : 0;

$ciclo = $cicloId > 0 ? $ciclos->buscarPorId($cicloId) : null;
if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/ranking/index.php');
    exit;
}

$lista   = $sorteos->listarPorCiclo($cicloId);
$salidos = PortalService::numerosSalidos($lista);

$jugadas = array_values(array_filter(
    JugadaService::crearDesde($db)->listarPorCiclo($cicloId, '', 5000),
    static fn($j) => (int) $j['cliente_id'] === $clienteId && $j['estado'] !== 'anulada'
));

if (!$jugadas) {
    setFlash('danger', 'Ese cliente no tiene jugadas en ese ciclo.');
    header('Location: ' . APP_URL . '/admin/ranking/index.php?ciclo=' . $cicloId);
    exit;
}

$clienteNombre = $jugadas[0]['cliente_nombre'];
$nroCliente    = $jugadas[0]['nro_cliente'];
$esSabado      = $ciclo['tipo'] === CicloService::TIPO_SABADO;

$pageTitle        = $clienteNombre . ' · Ranking · ' . APP_NAME;
$navSeccion       = 'ranking';
$pageSectionTitle = 'Jugadas del cliente';
$breadcrumb       = [
    ['label' => 'Ranking', 'url' => APP_URL . '/admin/ranking/index.php?ciclo=' . $cicloId],
    ['label' => $clienteNombre, 'url' => ''],
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue">Ciclo <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge g-badge--info"><?= $esSabado ? 'Sábado' : 'Semana' ?></span>
            </div>
            <h1 class="g-page-title mt-2"><?= e($clienteNombre) ?></h1>
            <p class="g-page-subtitle">N° <?= e($nroCliente) ?> · <?= e(CicloService::rotulo($ciclo)) ?></p>
        </div>
        <a href="<?= APP_URL ?>/admin/ranking/index.php?ciclo=<?= $cicloId ?>" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver al ranking
        </a>
    </div>

    <!-- Jugadas del cliente en este ciclo -->
    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-ticket-perforated"></i>
                Jugadas (<?= count($jugadas) ?>)
            </h2>
        </div>
        <div class="g-card__body">
            <?php foreach ($jugadas as $jugada): ?>
                <?php
                $aciertos = 0;
                foreach ($jugada['numeros'] as $numero) {
                    if (isset($salidos[$numero])) {
                        $aciertos++;
                    }
                }
                ?>
                <div class="g-list-item">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="g-list-item__meta">
                            <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                        </div>

                        <div class="text-end">
                            <?php if ($jugada['estado'] === 'ganadora'): ?>
                                <span class="g-badge g-badge--warning">Ganadora</span>
                            <?php elseif ($jugada['estado'] === 'perdedora'): ?>
                                <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                            <?php else: ?>
                                <span class="g-badge g-badge--success">Activa</span>
                            <?php endif; ?>
                            <div class="small fw-semibold text-primary mt-1">
                                <?= $aciertos ?> / <?= count($jugada['numeros']) ?> salidos
                            </div>
                        </div>
                    </div>

                    <div class="bolillas mt-2">
                        <?php foreach ($jugada['numeros'] as $numero): ?>
                            <span class="bolilla <?= isset($salidos[$numero]) ? 'bolilla--acertada' : '' ?>">
                                <?= e(num2($numero)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
