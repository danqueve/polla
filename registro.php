<?php
/**
 * Autorregistro publico de clientes. Sin login: cualquiera entra acá,
 * carga DNI, nombre y telefono. La clave queda fijada sola como el DNI
 * (no se pide ni se elige acá, igual que en el alta manual). La cuenta
 * nace pendiente y no puede operar hasta que un admin/supervisor la
 * apruebe desde admin/clientes/solicitudes.php.
 *
 * Usa la sesion del portal (config/portal.php) desde el arranque: si el
 * registro sale bien, dejamos al cliente ya logueado en esa misma
 * sesion (ClienteAuthService::login) para que vea de una el aviso de
 * "cuenta en revision" en vez de tener que loguearse de nuevo.
 */
require_once __DIR__ . '/config/portal.php';

use Polla\Services\ClienteAuthService;
use Polla\Services\ClienteRegistroService;
use Polla\Services\ClienteService;
use Polla\Support\ValidacionException;

if (clienteLogueado()) {
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

$errores  = [];
$dni      = old('dni');
$nombre   = old('nombre');
$telefono = old('telefono');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $dni      = trim($_POST['dni'] ?? '');
    $nombre   = trim($_POST['nombre'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La página estuvo abierta demasiado tiempo. Probá de nuevo.';
    } else {
        try {
            $db = getPDO();
            $datos = ['dni' => $dni, 'nombre' => $nombre, 'telefono' => $telefono];

            ClienteRegistroService::crearDesde($db)->registrar($datos);
            flushOld();

            // Ya tiene cuenta: lo dejamos logueado (clave = su propio DNI)
            // para que vea directamente el aviso de "cuenta en revision".
            $dniNormalizado = ClienteService::normalizarDni($dni);
            (new ClienteAuthService($db))->login($dniNormalizado, $dniNormalizado);

            header('Location: ' . APP_URL . '/portal/index.php');
            exit;
        } catch (ValidacionException $e) {
            setOld(['dni' => $dni, 'nombre' => $nombre, 'telefono' => $telefono]);
            $errores = $e->errores();
        }
    }
}

$pageTitle = 'Registrate · ' . APP_NAME;
$bodyClass = 'sin-barra';
require __DIR__ . '/includes/head.php';
?>

<main class="login">
    <div class="login__caja">

        <h1 class="login__marca">Decena<em>de Oro</em></h1>
        <p class="login__bajada">Registrate para empezar a jugar</p>

        <div class="login__panel">

            <?php if ($errores): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-octagon-fill flex-shrink-0" style="margin-top:.15rem"></i>
                    <div>
                        <?php foreach ($errores as $error): ?>
                            <div><?= e($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="alert alert-warning d-flex align-items-start gap-2 py-2 mb-3" style="font-size:.875rem">
                <i class="bi bi-info-circle-fill flex-shrink-0" style="margin-top:.15rem"></i>
                <div>
                    Tu cuenta queda pendiente hasta que un administrador la
                    apruebe. Tu usuario y tu contraseña van a ser tu DNI.
                </div>
            </div>

            <form method="post" novalidate>
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label" for="dni">Tu DNI</label>
                    <input type="text" class="form-control cifra" id="dni" name="dni"
                           value="<?= e($dni) ?>"
                           inputmode="numeric" pattern="[0-9]*" maxlength="9"
                           autocomplete="username"
                           placeholder="Sin puntos"
                           required autofocus>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="nombre">Nombre y apellido</label>
                    <input type="text" class="form-control" id="nombre" name="nombre"
                           value="<?= e($nombre) ?>"
                           autocomplete="name" autocapitalize="words" maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="telefono">
                        Teléfono <span class="fw-normal" style="color:rgba(255,255,255,.6)">(opcional)</span>
                    </label>
                    <input type="tel" class="form-control" id="telefono" name="telefono"
                           value="<?= e($telefono) ?>"
                           inputmode="tel" autocomplete="tel" maxlength="30"
                           placeholder="381 555 1234">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-person-plus"></i>
                    Enviar solicitud
                </button>
            </form>
        </div>

        <p class="text-center mt-4 mb-0" style="color:rgba(255,255,255,.45);font-size:.8125rem">
            ¿Ya tenés cuenta? <a href="<?= APP_URL ?>/portal/login.php" style="color:inherit">Entrá acá</a>.
        </p>
    </div>
</main>

<?php
flushOld();
require __DIR__ . '/includes/foot.php';
