<?php
/**
 * "Mis jugadas": el pozo de la semana y las jugadas del ciclo activo.
 *
 * Todo lo que se lee de jugadas pasa por PortalService con el id de la
 * sesion, asi que no hay forma de pedir las de otro cliente.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\PortalService;

requireCliente();

$db        = getPDO();
$portal    = new PortalService($db);
$ciclos    = new CicloService($db);
$clienteId = (int) clienteActualId();

$ciclo   = $ciclos->obtenerCicloActivo();
$cicloId = (int) $ciclo['id'];

$jugadas      = $portal->jugadasDelCiclo($clienteId, $cicloId);
$sorteos      = $portal->sorteosDelCiclo($cicloId);
$totalSorteos = count($sorteos);
$cicloAbierto = true;

$pageTitle  = 'Mis jugadas · ' . APP_NAME;
$navSeccion = 'mis-jugadas';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <!-- Pozo de la semana -->
    <section class="pozo pozo-cliente mb-4 text-center">
        <div class="pozo__rotulo mb-2">Pozo de esta semana</div>
        <div class="pozo__monto"><?= e(formatPesos($ciclo['monto_acumulado'] ?? 0)) ?></div>
        <div class="mt-3" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            Semana del <?= e(CicloService::rotulo($ciclo)) ?>
        </div>
    </section>

    <?php if (!$jugadas): ?>

        <div class="tarjeta p-4 text-center">
            <i class="bi bi-ticket-perforated d-block mb-3"
               style="font-size:2.5rem;color:var(--borde-fuerte)" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">Esta semana no tenés jugadas</p>
            <p class="fila__meta mb-0">
                Hablá con Decena de Oro para cargar una y entrar en el pozo
                de <?= e(formatPesos($ciclo['monto_acumulado'] ?? 0)) ?>.
            </p>
        </div>

        <a href="<?= APP_URL ?>/portal/historial.php" class="btn btn-outline-secondary w-100 mt-3">
            <i class="bi bi-clock-history"></i> Ver mis jugadas anteriores
        </a>

    <?php else: ?>

        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="rotulo">
                <?= count($jugadas) === 1 ? 'Tu jugada' : 'Tus ' . count($jugadas) . ' jugadas' ?>
            </span>
            <span class="fila__meta">
                <?= $totalSorteos ?> de 5 sorteos
            </span>
        </div>

        <?php foreach ($jugadas as $jugada): ?>
            <?php $evaluacion = PortalService::evaluar($jugada['numeros'], $sorteos); ?>
            <?php require __DIR__ . '/../includes/portal_jugada.php'; ?>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../includes/portal_nav.php';
require __DIR__ . '/../includes/foot.php';
