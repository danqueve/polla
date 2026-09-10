<?php
/**
 * Login unico: admin, supervisor y cliente entran por la misma pantalla.
 * Lo unico que cambia despues es el perfil (y con el, los permisos) —
 * la puerta de entrada es una sola.
 *
 * config/app.php (sesion PHPSESSID, panel) y config/portal.php (sesion
 * POLLA_CLIENTE, cliente) no se pueden tener abiertas a la vez: cada uno
 * usa un nombre de sesion distinto y PHP no permite renombrar una sesion
 * ya activa. Por eso esta pagina cambia de "mundo" con cambiarASesion()
 * (mas abajo) en vez de requerir los dos config a la vez: primero prueba
 * si ya hay sesion abierta en cualquiera de los dos mundos, y en el POST
 * prueba primero contra `usuarios` y despues contra `clientes`, sin abrir
 * la sesion definitiva hasta tener un match real.
 */
require_once __DIR__ . '/../config/bootstrap.php';

use Polla\Services\AuthService;
use Polla\Services\ClienteAuthService;
use Polla\Support\CuentaInactivaException;
use Polla\Support\ValidacionException;

/**
 * session_write_close() no limpia el id interno que arrastra PHP: si el
 * proximo session_start() se hace a ciegas, reusa ese mismo id en vez de
 * leer la cookie que corresponde al otro nombre de sesion (y las dos
 * terminan compartiendo el mismo archivo), o —peor— session_id('') hace
 * que PHP descarte cualquier cookie real y arranque siempre una sesion
 * nueva y vacia. La forma correcta de cambiar de "mundo" a mitad de
 * request es leer nosotros mismos la cookie que corresponda y fijarla
 * de forma explicita antes de arrancar.
 */
function cambiarASesion(string $nombre): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
    session_name($nombre);
    session_id($_COOKIE[$nombre] ?? session_create_id());
    session_start();
}

// Nombre de sesion por defecto (la del panel), capturado antes de que
// nada lo cambie: hace falta para poder reabrirla mas abajo sin depender
// de un segundo require_once de config/app.php (que no vuelve a correr).
$sesionPanel = session_name();

require_once __DIR__ . '/../config/app.php';
if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

// Todavia no sabemos si el visitante es cliente. La sesion del panel
// quedo vacia (no hay nadie logueado): la soltamos y probamos la del
// portal antes de mostrar el formulario. cambiarASesion() ya deja la
// sesion correcta arrancada, asi que este require_once no vuelve a
// tocarla (su guard ve session_status() distinto de NONE) y solo aporta
// las funciones (clienteLogueado, etc.).
cambiarASesion('POLLA_CLIENTE');
require_once __DIR__ . '/../config/portal.php';
if (clienteLogueado()) {
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

// Si el visitante viene rebotado de requireCliente() (baja o rechazo,
// via el adaptador de portal/login.php), el flash vive en esta misma
// sesion del portal.
$flash = getFlash();

// De aca en adelante la sesion activa es la del portal: ahi se genera
// y valida el CSRF de este formulario.
$errores       = [];
$identificador = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $identificador = trim($_POST['usuario'] ?? '');
    $password       = $_POST['password'] ?? '';

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La pagina estuvo abierta demasiado tiempo. Reintentá.';
    } else {
        $db = getPDO();

        try {
            $fila = (new AuthService($db))->verificar($identificador, $password);

            // Matcheo contra usuarios: soltamos la sesion del portal (la
            // activa desde arriba) y recien ahi abrimos la del panel, que
            // no se puede tener abierta a la vez que la otra.
            cambiarASesion($sesionPanel);
            (new AuthService($db))->abrirSesion($fila);

            header('Location: ' . APP_URL . '/admin/index.php');
            exit;
        } catch (CuentaInactivaException $e) {
            // Matcheo real contra usuarios pero la cuenta esta
            // desactivada: mensaje especifico, no probamos clientes.
            $errores = $e->errores();
        } catch (ValidacionException $eStaff) {
            try {
                $cliente = (new ClienteAuthService($db))->verificar($identificador, $password);

                // Matcheo contra clientes: la sesion activa ya es la del
                // portal, no hay que cambiar nada.
                (new ClienteAuthService($db))->abrirSesion($cliente);

                header('Location: ' . APP_URL . '/portal/index.php');
                exit;
            } catch (CuentaInactivaException $eCliente) {
                $errores = $eCliente->errores();
            } catch (ValidacionException $eClienteGenerico) {
                // Ninguna de las dos tablas matcheo: un solo mensaje
                // generico, para no filtrar contra cual se probo.
                $errores[] = 'Usuario/DNI o contraseña incorrectos.';
            }
        }
    }
}

$pageTitle   = 'Ingresar · ' . APP_NAME;
$bodyClass   = 'sin-barra';
$pageScripts = ['mostrar_clave.js'];
require __DIR__ . '/../includes/head.php';
?>

<main class="login">
    <div class="login__caja">

        <h1 class="login__marca">Decena<em>de Oro</em></h1>
        <p class="login__bajada">Quiniela Nocturna de Tucumán</p>

        <div class="login__panel">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                    <?= nl2br(e($flash['msg'])) ?>
                </div>
            <?php endif; ?>

            <?php require __DIR__ . '/../includes/alerta_errores.php'; ?>

            <form method="post" novalidate>
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label" for="usuario">Usuario o DNI</label>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           value="<?= e($identificador) ?>"
                           autocomplete="username"
                           autocapitalize="none" autocorrect="off" spellcheck="false"
                           required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                        <button type="button" class="btn btn-outline-secondary"
                                data-toggle-password="password" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        Si sos cliente, tu contraseña es tu DNI.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Ingresar
                </button>
            </form>
        </div>

        <p class="text-center mt-4 mb-2" style="color:rgba(255,255,255,.7);font-size:.875rem">
            ¿Todavía no jugaste? <a href="<?= APP_URL ?>/registro.php" style="color:inherit">Registrate acá</a>.
        </p>
        <p class="text-center mb-0" style="color:rgba(255,255,255,.75);font-size:.8125rem">
            ¿No podés entrar?
            <a href="<?= e(whatsappUrl()) ?>" target="_blank" rel="noopener" style="color:inherit">
                Hablá con Decena de Oro
            </a>.
        </p>
    </div>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
