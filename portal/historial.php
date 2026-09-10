<?php
/**
 * Historial: semanas ya cerradas en las que el cliente jugo, con el
 * mismo bloque visual que "Mis jugadas".
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\PortalService;

requireCliente();

$db        = getPDO();
$portal    = new PortalService($db);
$clienteId = (int) clienteActualId();

$tipoJuego = array_key_exists($_GET['tipo'] ?? '', CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$esSabado  = $tipoJuego === CicloService::TIPO_SABADO;

$ciclos       = $portal->ciclosJugados($clienteId, $tipoJuego);
$totalGanado  = $portal->totalGanado($clienteId);
$totalJugadas = $portal->totalJugadas($clienteId);

$cicloAbierto = false;

$pageTitle  = 'Historial · ' . APP_NAME;
$navSeccion = 'historial';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Historial</h1>
    <p class="pantalla__bajada"><?= $esSabado ? 'Tus sábados anteriores' : 'Tus semanas anteriores' ?></p>

    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link <?= !$esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/historial.php?tipo=<?= CicloService::TIPO_SEMANAL ?>">Semana</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $esSabado ? 'active' : '' ?>"
               href="<?= APP_URL ?>/portal/historial.php?tipo=<?= CicloService::TIPO_SABADO ?>">Sábado</a>
        </li>
    </ul>

    <div class="row g-2 mt-3 mb-4">
        <div class="col-6">
            <div class="metrica">
                <div class="metrica__valor"><?= $totalJugadas ?></div>
                <div class="metrica__rotulo">Jugadas en total</div>
            </div>
        </div>
        <div class="col-6">
            <div class="metrica">
                <div class="metrica__valor <?= $totalGanado > 0 ? 'metrica__valor--oro' : '' ?>">
                    <?= e(formatPesos($totalGanado)) ?>
                </div>
                <div class="metrica__rotulo">Ganado en total</div>
            </div>
        </div>
    </div>

    <?php if (!$ciclos): ?>

        <div class="vacio tarjeta">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">
                <?= $esSabado ? 'Todavía no hay sábados cerrados' : 'Todavía no hay semanas cerradas' ?>
            </p>
            <p class="fila__meta mb-0">
                <?= $esSabado
                    ? 'Cuando termine tu primer sábado de juego, lo vas a ver acá.'
                    : 'Cuando termine tu primera semana de juego, la vas a ver acá.' ?>
            </p>
        </div>

        <a href="<?= APP_URL ?>/portal/index.php?tipo=<?= $tipoJuego ?>" class="btn btn-outline-secondary w-100 mt-3">
            <i class="bi bi-ticket-perforated"></i> Ver mis jugadas actuales
        </a>

    <?php else: ?>

        <?php foreach ($ciclos as $c): ?>
            <?php
            $cicloId      = (int) $c['id'];
            $jugadas      = $portal->jugadasDelCiclo($clienteId, $cicloId);
            $sorteos      = $portal->sorteosDelCiclo($cicloId);
            $totalSorteos = count($sorteos);

            // Cuanto se llevo el cliente esa semana
            $ganadoEnCiclo = 0.0;
            foreach ($jugadas as $j) {
                $ganadoEnCiclo += (float) $j['monto_premio'];
            }
            ?>

            <section class="mb-4">
                <div class="d-flex align-items-baseline justify-content-between gap-2 mb-2">
                    <div>
                        <span class="rotulo"><?= $esSabado ? 'Sábado' : 'Semana' ?> <?= (int) $c['numero'] ?></span>
                        <p class="fw-semibold mb-0"><?= e(CicloService::rotulo($c)) ?></p>
                    </div>
                    <?php if ($ganadoEnCiclo > 0): ?>
                        <span class="etiqueta etiqueta--oro text-nowrap">
                            <i class="bi bi-trophy-fill"></i> Ganaste
                        </span>
                    <?php elseif ($c['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                        <span class="etiqueta etiqueta--gris text-nowrap">Ganó otro</span>
                    <?php else: ?>
                        <span class="etiqueta etiqueta--gris text-nowrap">Sin ganador</span>
                    <?php endif; ?>
                </div>

                <?php foreach ($jugadas as $jugada): ?>
                    <?php $evaluacion = PortalService::evaluar($jugada['numeros'], $sorteos); ?>
                    <?php require __DIR__ . '/../includes/portal_jugada.php'; ?>
                <?php endforeach; ?>
            </section>

        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../includes/portal_nav.php';
require __DIR__ . '/../includes/foot.php';
