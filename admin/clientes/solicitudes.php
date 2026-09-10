<?php
/**
 * Cola de aprobacion de autorregistros. Admin y supervisor ven el
 * listado y pueden aprobar; rechazar es exclusivo del admin (lo exige
 * tambien ClienteRegistroService::rechazar(), no solo esta pantalla).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ClienteRegistroService;

requireLogin();

$solicitudes = ClienteRegistroService::crearDesde(getPDO())->listarPendientes();

$pageTitle  = 'Solicitudes · ' . APP_NAME;
$navSeccion = 'clientes';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/clientes/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Clientes
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Solicitudes de alta</h1>
    <p class="pantalla__bajada">
        Cuentas que se autorregistraron desde <span class="cifra">/registro</span>
        y esperan revisión.
    </p>

    <?php if (!$solicitudes): ?>

        <div class="vacio tarjeta mt-3">
            <i class="bi bi-inbox" aria-hidden="true"></i>
            No hay solicitudes pendientes.
        </div>

    <?php else: ?>

        <?php foreach ($solicitudes as $s): ?>
            <article class="tarjeta p-3 mt-3">
                <p class="fila__titulo"><?= e($s['nombre']) ?></p>
                <p class="fila__meta">
                    <span class="cifra">N° <?= e($s['nro_cliente']) ?></span>
                    · DNI <?= e($s['dni']) ?>
                    <?php if ($s['telefono']): ?>
                        · <?= e($s['telefono']) ?>
                    <?php endif; ?>
                </p>
                <p class="fila__meta mb-3">
                    Solicitado el <?= e(formatFechaHora($s['fecha_alta'])) ?>
                </p>

                <div class="d-flex gap-2">
                    <form method="post" class="flex-grow-1"
                          action="<?= APP_URL ?>/admin/clientes/solicitud_aprobar.php"
                          onsubmit="return confirm('Aprobar la solicitud de <?= e(addslashes($s['nombre'])) ?>? Va a poder entrar al portal.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg"></i> Aprobar
                        </button>
                    </form>

                    <?php if (isAdmin()): ?>
                        <form method="post" class="flex-grow-1"
                              action="<?= APP_URL ?>/admin/clientes/solicitud_rechazar.php"
                              onsubmit="return confirm('Rechazar la solicitud de <?= e(addslashes($s['nombre'])) ?>?')">
                            <?= csrfField() ?>
                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-lg"></i> Rechazar
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
