<?php
/**
 * Solicitudes de pago (Fase 6): el cliente arma la jugada desde el
 * portal, el staff busca por el codigo de 6 y confirma o rechaza.
 *
 * "Solicitudes" ya se usa para la cola de autorregistro de clientes
 * (admin/clientes/solicitudes.php) — son conceptos distintos que
 * comparten el sustantivo, por eso todo el texto en pantalla dice
 * "de pago" para no confundirlas.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\SolicitudService;

requireLogin();

$db         = getPDO();
$solicitudes = SolicitudService::crearDesde($db);

$codigo    = trim($_GET['codigo'] ?? '');
$solicitud = $codigo !== '' ? $solicitudes->buscarPorCodigo($codigo) : null;

$pendientes = $solicitudes->listarPendientes();

$pageTitle  = 'Solicitudes de pago · ' . APP_NAME;
$navSeccion = 'solicitudes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Solicitudes de pago</h1>
    <p class="pantalla__bajada">
        Jugadas que un cliente armó desde el portal y todavía no confirmaste.
    </p>

    <form method="get" class="mt-3">
        <label class="form-label" for="codigo">Código de la solicitud</label>
        <div class="d-flex gap-2">
            <input type="text" class="form-control cifra text-uppercase" id="codigo" name="codigo"
                   value="<?= e($codigo) ?>"
                   maxlength="6" autocapitalize="characters" autocomplete="off"
                   placeholder="ABC123" style="letter-spacing:.15em;font-size:1.25rem"
                   autofocus>
            <button type="submit" class="btn btn-primary flex-shrink-0">
                <i class="bi bi-search"></i> Buscar
            </button>
        </div>
    </form>

    <?php if ($codigo !== '' && !$solicitud): ?>
        <div class="alert alert-danger mt-3" role="alert">
            No encontramos ninguna solicitud con el código <strong><?= e(strtoupper($codigo)) ?></strong>.
            Revisá que esté bien tipeado.
        </div>
    <?php endif; ?>

    <?php if ($solicitud): ?>
        <article class="tarjeta tarjeta--realce p-3 mt-3">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <span class="rotulo d-block mb-1">Código <?= e($solicitud['numero_registro']) ?></span>
                    <p class="fila__titulo mb-0"><?= e($solicitud['cliente_nombre']) ?></p>
                    <p class="fila__meta">
                        <span class="cifra">N° <?= e($solicitud['nro_cliente']) ?></span>
                        · DNI <?= e($solicitud['dni']) ?>
                        <?php if ($solicitud['telefono']): ?>
                            · <?= e($solicitud['telefono']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php if ($solicitud['estado'] === 'pendiente'): ?>
                    <span class="etiqueta etiqueta--oro text-nowrap">Pendiente</span>
                <?php elseif ($solicitud['estado'] === 'confirmada'): ?>
                    <span class="etiqueta etiqueta--verde text-nowrap">Confirmada</span>
                <?php else: ?>
                    <span class="etiqueta etiqueta--roja text-nowrap">Rechazada</span>
                <?php endif; ?>
            </div>

            <div class="d-flex justify-content-between align-items-baseline mb-3">
                <span class="fila__meta">
                    <?= (int) $solicitud['cantidad_jugadas'] ?>
                    <?= (int) $solicitud['cantidad_jugadas'] === 1 ? 'jugada' : 'jugadas' ?>
                    · armada el <?= e(formatFechaHora($solicitud['fecha_creacion'])) ?>
                </span>
                <span class="cifra fw-bold fs-5"><?= e(formatPesos($solicitud['monto_total'])) ?></span>
            </div>

            <?php foreach ($solicitud['jugadas'] as $jugada): ?>
                <div class="bolillas mb-2">
                    <?php foreach ($jugada['numeros'] as $numero): ?>
                        <span class="bolilla"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($solicitud['estado'] === 'pendiente'): ?>
                <div class="d-flex gap-2 mt-3">
                    <form method="post" class="flex-grow-1"
                          action="<?= APP_URL ?>/admin/solicitudes/confirmar.php"
                          onsubmit="return confirm('¿Confirmar el pago de <?= e(addslashes($solicitud['cliente_nombre'])) ?> por <?= e(formatPesos($solicitud['monto_total'])) ?>?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Confirmar pago
                        </button>
                    </form>

                    <?php if (isAdmin()): ?>
                        <form method="post" class="flex-grow-1"
                              action="<?= APP_URL ?>/admin/solicitudes/rechazar.php"
                              onsubmit="return confirm('¿Rechazar la solicitud de <?= e(addslashes($solicitud['cliente_nombre'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $solicitud['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php elseif ($solicitud['estado'] === 'confirmada'): ?>
                <p class="fila__meta mb-0 mt-2">
                    Confirmada el <?= e(formatFechaHora($solicitud['fecha_resolucion'])) ?>.
                </p>
            <?php else: ?>
                <p class="fila__meta mb-0 mt-2">
                    Rechazada el <?= e(formatFechaHora($solicitud['fecha_resolucion'])) ?>.
                </p>
            <?php endif; ?>
        </article>
    <?php endif; ?>

    <hr class="my-4">

    <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="rotulo">Cola de pendientes</span>
        <span class="fila__meta"><?= count($pendientes) ?></span>
    </div>

    <?php if (!$pendientes): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            No hay solicitudes de pago pendientes.
        </div>
    <?php else: ?>
        <?php foreach ($pendientes as $p): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/solicitudes/index.php?codigo=<?= e($p['numero_registro']) ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($p['cliente_nombre']) ?></p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($p['nro_cliente']) ?></span>
                            · <?= (int) $p['cantidad_jugadas'] ?>
                            <?= (int) $p['cantidad_jugadas'] === 1 ? 'jugada' : 'jugadas' ?>
                            · <?= e(formatFechaHora($p['fecha_creacion'])) ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <div class="cifra fw-bold"><?= e($p['numero_registro']) ?></div>
                        <div class="fila__meta"><?= e(formatPesos($p['monto_total'])) ?></div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
