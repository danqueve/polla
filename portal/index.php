<?php
/**
 * "Mis jugadas": el pozo de la semana y las jugadas del ciclo activo.
 *
 * Todo lo que se lee de jugadas pasa por PortalService con el id de la
 * sesion, asi que no hay forma de pedir las de otro cliente.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\PortalService;
use Polla\Services\PozoService;
use Polla\Services\SolicitudService;

requireCliente();

$db        = getPDO();
$portal    = new PortalService($db);
$ciclos    = new CicloService($db);
$clienteId = (int) clienteActualId();

$tipoJuego = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;

$ciclo   = $ciclos->obtenerCicloActivo($tipoJuego);
$cicloId = (int) $ciclo['id'];

$jugadas      = $portal->jugadasDelCiclo($clienteId, $cicloId);
$sorteos      = $portal->sorteosDelCiclo($cicloId);
$totalSorteos = count($sorteos);
$cicloAbierto = true;

$pendiente = SolicitudService::crearDesde($db)->pendientePara($clienteId, $tipoJuego);

// Fase 7: el pozo que ve el cliente nunca baja del premio base
// garantizado, aunque lo acumulado real esta semana (o este sabado)
// sea menor. Caja separada: cada juego tiene su propio premio base.
$premioBase   = $esSabado
    ? (new ParametroService($db))->premioBaseSabado()
    : (new ParametroService($db))->premioBase();
$pozoMostrado = PozoService::montoAMostrar((float) ($ciclo['monto_acumulado'] ?? 0), $premioBase);

$pageTitle  = 'Mis jugadas · ' . APP_NAME;
$navSeccion = 'mis-jugadas';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="visually-hidden">Mis jugadas</h1>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= !$esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/index.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">Semana</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/index.php?tipo=<?= CicloService::TIPO_SABADO ?>">Sábado</a>
        </li>
    </ul>

    <!-- Pozo del ciclo -->
    <section class="pozo pozo-cliente mb-4 text-center">
        <div class="pozo__rotulo mb-2"><?= $esSabado ? 'Pozo de este sábado' : 'Pozo de esta semana' ?></div>
        <div class="pozo__monto"><?= e(formatPesos($pozoMostrado)) ?></div>
        <div class="mt-3" style="color:rgba(255,255,255,.72);font-size:.8125rem">
            <?= $esSabado ? '' : 'Semana del ' ?><?= e(CicloService::rotulo($ciclo)) ?>
        </div>
    </section>

    <?php if ($pendiente): ?>
        <a href="<?= APP_URL ?>/portal/solicitud.php?id=<?= (int) $pendiente['id'] ?>"
           class="tarjeta tarjeta--oro p-3 mb-3 d-flex align-items-center justify-content-between gap-2"
           style="text-decoration:none;color:inherit">
            <span class="d-flex align-items-center gap-2">
                <i class="bi bi-hourglass-split texto-oro"></i>
                <span>
                    Tenés una solicitud sin pagar: código
                    <strong class="cifra"><?= e($pendiente['numero_registro']) ?></strong>
                    por <?= e(formatPesos($pendiente['monto_total'])) ?>
                </span>
            </span>
            <i class="bi bi-chevron-right text-secondary flex-shrink-0"></i>
        </a>
    <?php endif; ?>

    <?php if (!$jugadas): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            <p class="fw-semibold mb-2"><?= $esSabado ? 'Este sábado no tenés jugadas' : 'Esta semana no tenés jugadas' ?></p>
            <p class="fila__meta mb-0">
                Armá una jugada vos mismo, o
                <a href="<?= e(whatsappUrl()) ?>" target="_blank" rel="noopener">hablá con Decena de Oro</a>,
                y entrá en el pozo de <?= e(formatPesos($pozoMostrado)) ?>.
            </p>
        </div>

        <a href="<?= APP_URL ?>/portal/jugar.php?tipo=<?= $tipoJuego ?>" class="btn btn-primary w-100 mt-3">
            <i class="bi bi-plus-lg"></i> Armar una jugada
        </a>
        <a href="<?= APP_URL ?>/portal/historial.php?tipo=<?= $tipoJuego ?>" class="btn btn-outline-secondary w-100 mt-2">
            <i class="bi bi-clock-history"></i> Ver mis jugadas anteriores
        </a>

    <?php else: ?>

        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="rotulo">
                <?= count($jugadas) === 1 ? 'Tu jugada' : 'Tus ' . count($jugadas) . ' jugadas' ?>
            </span>
            <span class="fila__meta">
                <?= $totalSorteos ?> de 5 <?= $esSabado ? 'turnos' : 'sorteos' ?>
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
