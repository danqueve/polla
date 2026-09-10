<?php
/**
 * Unica pantalla que ve un cliente autorregistrado mientras su cuenta
 * sigue pendiente de aprobacion. requireCliente() manda para aca a
 * cualquier otra URL del portal hasta que un admin/supervisor apruebe.
 */
require_once __DIR__ . '/../config/portal.php';

requireCliente(true);

$pageTitle = 'Cuenta en revisión · ' . APP_NAME;
$bodyClass = 'sin-barra';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <div class="tarjeta p-4 text-center mt-3">
        <i class="bi bi-hourglass-split d-block mb-3 texto-oro"
           style="font-size:2.5rem" aria-hidden="true"></i>
        <p class="fw-semibold mb-2">Tu cuenta está en revisión</p>
        <p class="fila__meta mb-0">
            Recibimos tu registro. Un administrador tiene que aprobarlo
            antes de que puedas ver o cargar jugadas. Hablá con Decena de
            Oro si tenés dudas o si tarda más de lo esperado.
        </p>
    </div>

    <a href="<?= APP_URL ?>/portal/logout.php" class="btn btn-outline-secondary w-100 mt-3">
        <i class="bi bi-box-arrow-right"></i> Salir
    </a>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
