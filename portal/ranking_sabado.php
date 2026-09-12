<?php
/**
 * Ranking de aciertos del sabado en curso: cualquier cliente logueado
 * lo puede ver, no solo quien jugo -- por eso PortalService::ranking()
 * recibe las jugadas de TODOS los clientes del ciclo (unica pantalla
 * del portal que rompe la regla de "solo mis propios datos", a
 * proposito). Solo se muestra nombre y N de cliente, nunca DNI,
 * telefono ni los numeros jugados por otro.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\ParametroService;
use Polla\Services\PortalService;
use Polla\Services\PozoService;
use Polla\Services\SorteoService;

requireCliente();

$db        = getPDO();
$clienteId = (int) clienteActualId();

$ciclo   = (new CicloService($db))->obtenerCicloActivo(CicloService::TIPO_SABADO);
$cicloId = (int) $ciclo['id'];

$lista   = SorteoService::crearDesde($db)->listarPorCiclo($cicloId);
$jugadas = JugadaService::crearDesde($db)->listarPorCiclo($cicloId);
$salidos = PortalService::numerosSalidos($lista);
$ranking = PortalService::ranking($jugadas, $salidos);

$premioBase   = (new ParametroService($db))->premioBaseSabado();
$pozoMostrado = PozoService::montoAMostrar((float) ($ciclo['monto_acumulado'] ?? 0), $premioBase);

$pageTitle  = 'Ranking del sábado · ' . APP_NAME;
$navSeccion = 'mis-jugadas';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <a href="<?= APP_URL ?>/portal/index.php?tipo=<?= CicloService::TIPO_SABADO ?>"
       class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Mis jugadas
    </a>

    <h1 class="pantalla__titulo">Ranking del sábado</h1>
    <p class="pantalla__bajada">
        Quién va anotando más aciertos contra los turnos ya salidos —
        <?= count($lista) ?>/5 turnos cargados. Se actualiza solo a medida
        que Decena de Oro va cargando cada turno.
    </p>

    <section class="pozo pozo-cliente mb-4 text-center">
        <div class="pozo__rotulo mb-2">Pozo de este sábado</div>
        <div class="pozo__monto"><?= e(formatPesos($pozoMostrado)) ?></div>
    </section>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
            <p class="fw-semibold mb-2">Todavía no hay resultados</p>
            <p class="fila__meta mb-0">
                El ranking aparece apenas Decena de Oro cargue el primer turno del sábado.
            </p>
        </div>
    <?php elseif (!$ranking): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            Todavía no hay jugadas cargadas este sábado.
        </div>
    <?php else: ?>
        <?php foreach ($ranking as $i => $puesto): ?>
            <?php $esVos = $puesto['cliente_id'] === $clienteId; ?>
            <div class="fila" <?= $esVos ? 'style="border-color:var(--oro);border-width:1.5px"' : '' ?>>
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="cifra fw-bold text-secondary" style="width:1.75rem;flex-shrink:0">
                            <?= $i === 0 ? '🏆' : ($i + 1) . '°' ?>
                        </span>
                        <div class="min-w-0">
                            <p class="fila__titulo mb-0">
                                <?= e($puesto['nombre']) ?><?= $esVos ? ' (vos)' : '' ?>
                            </p>
                            <p class="fila__meta mb-0">N° <?= e($puesto['nro_cliente']) ?></p>
                        </div>
                    </div>
                    <span class="etiqueta <?= $puesto['aciertos'] > 0 ? 'etiqueta--verde' : 'etiqueta--gris' ?> text-nowrap">
                        <?= (int) $puesto['aciertos'] ?>/<?= (int) $puesto['total'] ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</main>

<?php
require __DIR__ . '/../includes/portal_nav.php';
require __DIR__ . '/../includes/foot.php';
