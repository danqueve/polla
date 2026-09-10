<?php
/**
 * Muestra el código y el monto de una solicitud recien armada (o
 * revisitada mas tarde: el cliente puede necesitar el codigo dias
 * despues, para cuando vaya a pagar).
 *
 * Ownership: se verifica que la solicitud sea del cliente logueado,
 * no solo que exista un id valido.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\SolicitudService;

requireCliente();

$db        = getPDO();
$clienteId = (int) clienteActualId();
$id        = (int) ($_GET['id'] ?? 0);

$solicitud = $id > 0 ? SolicitudService::crearDesde($db)->buscarPorId($id) : null;

if (!$solicitud || (int) $solicitud['cliente_id'] !== $clienteId) {
    setFlash('danger', 'No encontramos esa solicitud.');
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

$estado = $solicitud['estado'];

$pageTitle   = 'Tu código · ' . APP_NAME;
$navSeccion  = 'jugar';
$pageScripts = ['copiar.js'];
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <?php if ($estado === 'pendiente'): ?>
        <h1 class="pantalla__titulo">Presentá este código</h1>
        <p class="pantalla__bajada">
            Guardalo o hacele una captura de pantalla: lo vas a necesitar para pagar.
        </p>
    <?php elseif ($estado === 'confirmada'): ?>
        <div class="etiqueta etiqueta--verde mb-2"><i class="bi bi-check-circle-fill"></i> Pago confirmado</div>
        <h1 class="pantalla__titulo">Ya está al día</h1>
        <p class="pantalla__bajada">
            Decena de Oro confirmó tu pago. Tus jugadas ya están en juego esta semana.
        </p>
    <?php else: ?>
        <div class="etiqueta etiqueta--roja mb-2"><i class="bi bi-x-circle-fill"></i> Rechazada</div>
        <h1 class="pantalla__titulo">Esta solicitud no se procesó</h1>
        <p class="pantalla__bajada">
            <a href="<?= e(whatsappUrl()) ?>" target="_blank" rel="noopener">Hablá con Decena de Oro</a>
            si te parece que es un error.
        </p>
    <?php endif; ?>

    <section class="codigo-solicitud mt-3">
        <div class="codigo-solicitud__rotulo mb-2">Tu código</div>
        <div class="codigo-solicitud__valor"><?= e($solicitud['numero_registro']) ?></div>
        <button type="button" class="btn btn-sm btn-outline-light codigo-solicitud__copiar"
                data-copiar="<?= e($solicitud['numero_registro']) ?>">
            <i class="bi bi-clipboard"></i> Copiar código
        </button>
    </section>

    <section class="monto-a-pagar mt-3">
        <div class="pozo__rotulo mb-2" style="color:var(--tinta-tenue)">
            <?= $estado === 'pendiente' ? 'Total a pagar' : 'Total' ?>
        </div>
        <div class="monto-a-pagar__valor"><?= e(formatPesos($solicitud['monto_total'])) ?></div>
        <p class="fila__meta mt-2 mb-0">
            <?= (int) $solicitud['cantidad_jugadas'] ?>
            <?= (int) $solicitud['cantidad_jugadas'] === 1 ? 'jugada' : 'jugadas' ?>
        </p>
    </section>

    <?php if ($estado === 'pendiente'): ?>
        <div class="alert alert-warning mt-3" role="note">
            <i class="bi bi-info-circle-fill"></i>
            <strong>Cómo pagar:</strong> hacé la transferencia a este alias:
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="alias-transferencia">decenadeoro</div>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-copiar="decenadeoro">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
            </div>
            Una vez transferido, compartile el código al vendedor para que te
            confirme el pago. Tus jugadas quedan
            reservadas hasta entonces.
        </div>
    <?php endif; ?>

    <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
        <span class="rotulo">
            <?= (int) $solicitud['cantidad_jugadas'] === 1 ? 'Tu jugada' : 'Tus jugadas' ?>
        </span>
    </div>

    <?php foreach ($solicitud['jugadas'] as $jugada): ?>
        <div class="fila mb-2">
            <div class="bolillas">
                <?php foreach ($jugada['numeros'] as $numero): ?>
                    <span class="bolilla"><?= e(num2($numero)) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex flex-column gap-2 mt-4">
        <a href="<?= APP_URL ?>/portal/index.php" class="btn btn-primary w-100">
            <i class="bi bi-ticket-perforated"></i> Ir a Mis jugadas
        </a>
        <?php if ($estado === 'pendiente'): ?>
            <a href="<?= APP_URL ?>/portal/jugar.php" class="btn btn-outline-secondary w-100">
                <i class="bi bi-plus-lg"></i> Armar otra jugada
            </a>
        <?php endif; ?>
    </div>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
