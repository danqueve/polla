<?php
/**
 * Paso del portal del cliente al panel de vendedor, para el cliente que
 * ademas tiene una cuenta de vendedor vinculada.
 *
 * Pide la clave del vendedor. Antes el salto era sin clave, y como la
 * clave del cliente es siempre su DNI, cualquiera que supiera el DNI de
 * un vendedor entraba a su panel (saldo de comisiones, lista de
 * referidos) sin conocer su clave: la clave propia del vendedor no
 * protegia nada. El salto inverso (vendedor/jugar.php) sigue sin clave a
 * proposito: va de la clave del vendedor a la del cliente, que es la mas
 * debil de las dos.
 *
 * El vendedor se resuelve siempre a partir de quien ya esta autenticado
 * como cliente en esta sesion, nunca de un id que llegue por GET/POST. La
 * clave se verifica con lo mismo que el login (VendedorAuthService::
 * verificar()), con el mismo limite de intentos (LoginThrottle, con el DNI
 * normalizado: comparte el contador con el login) y la misma auditoria.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\AuditoriaService;
use Polla\Services\LoginThrottle;
use Polla\Services\VendedorAuthService;
use Polla\Services\VendedorService;
use Polla\Support\CuentaInactivaException;
use Polla\Support\ValidacionException;

requireCliente();

$db       = getPDO();
$vendedor = (new VendedorService($db))->buscarPorClienteId(clienteActualId());

if (!$vendedor || (int) $vendedor['activo'] !== 1) {
    setFlash('danger', 'No tenés (o ya no tenés) un panel de vendedor activo. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

$errores = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La página estuvo abierta demasiado tiempo. Reintentá.';
    } elseif ($password === '') {
        $errores[] = 'Escribí tu contraseña de vendedor.';
    } else {
        $throttle = new LoginThrottle($db);
        $audit    = AuditoriaService::crearDesde($db);
        $dni      = (string) $vendedor['dni'];
        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        try {
            $throttle->verificar($dni, $ip);
        } catch (ValidacionException $e) {
            $audit->loginBloqueado($dni);
            $errores = $e->errores();
        }

        if (!$errores) {
            try {
                $fila = (new VendedorAuthService($db))->verificar($dni, $password);

                $throttle->limpiar($dni, $ip);
                $audit->loginOk('vendedor', (int) $fila['id'], $dni);

                cambiarASesion('POLLA_VENDEDOR');
                (new VendedorAuthService($db))->abrirSesion($fila);

                header('Location: ' . APP_URL . '/vendedor/index.php');
                exit;
            } catch (CuentaInactivaException $e) {
                // Lo dieron de baja entre que abrio la pantalla y la envio.
                $errores = $e->errores();
            } catch (ValidacionException $e) {
                $throttle->registrarFallo($dni, $ip);
                $audit->loginFallido($dni);
                $errores[] = 'La contraseña de vendedor no es correcta.';
            }
        }
    }
}

$pageTitle   = 'Panel de vendedor · ' . APP_NAME;
$bodyClass   = 'sin-barra';
$pageScripts = ['mostrar_clave.js'];
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../includes/flash.php'; ?>

    <div class="tarjeta p-4 mt-3">
        <div class="text-center">
            <i class="bi bi-shield-lock d-block mb-3 texto-oro"
               style="font-size:2.5rem" aria-hidden="true"></i>
            <h1 class="fw-semibold fs-5 mb-2">Tu panel de vendedor</h1>
            <p class="fila__meta mb-4">
                Por seguridad, confirmá tu contraseña de vendedor: la que usás
                para entrar al panel, no tu DNI.
            </p>
        </div>

        <?php require __DIR__ . '/../includes/alerta_errores.php'; ?>

        <form method="post" novalidate>
            <?= csrfField() ?>

            <div class="mb-4">
                <label class="form-label" for="password">Contraseña de vendedor</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required autofocus>
                    <button type="button" class="btn btn-outline-secondary"
                            data-toggle-password="password" aria-label="Mostrar contraseña">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="form-text">
                    ¿No te acordás? Hablá con Decena de Oro al
                    <?= e(CONTACTO_WHATSAPP_LEGIBLE) ?> para que te la cambien.
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right"></i> Entrar al panel
            </button>
        </form>
    </div>

    <a href="<?= APP_URL ?>/portal/index.php" class="btn btn-outline-secondary w-100 mt-3">
        <i class="bi bi-arrow-left"></i> Volver a mis jugadas
    </a>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
