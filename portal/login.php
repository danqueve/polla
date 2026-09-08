<?php
/**
 * Login del portal del cliente. Usuario = DNI.
 *
 * Nada de esta carpeta toca config/app.php: el portal corre sobre su
 * propia sesion (config/portal.php), con otro nombre de cookie.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\ClienteAuthService;
use Polla\Support\ValidacionException;

if (clienteLogueado()) {
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

$errores = [];
$dni     = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $dni = trim($_POST['dni'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La página estuvo abierta demasiado tiempo. Probá de nuevo.';
    } else {
        try {
            (new ClienteAuthService(getPDO()))->login($dni, $_POST['password'] ?? '');
            header('Location: ' . APP_URL . '/portal/index.php');
            exit;
        } catch (ValidacionException $e) {
            $errores = $e->errores();
        }
    }
}

$flash     = getFlash();
$pageTitle = 'Entrar · ' . APP_NAME;
$bodyClass = 'sin-barra';
require __DIR__ . '/../includes/head.php';
?>

<main class="login">
    <div class="login__caja">

        <h1 class="login__marca">Polla Semanal<em>Los Quevedo</em></h1>
        <p class="login__bajada">Mirá cómo van tus jugadas de la semana</p>

        <div class="login__panel">

            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>" role="alert">
                    <?= nl2br(e($flash['msg'])) ?>
                </div>
            <?php endif; ?>

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

                <div class="mb-4">
                    <label class="form-label" for="password">Tu contraseña</label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                    <div class="form-text">
                        Si entrás por primera vez, tu contraseña es tu mismo DNI.
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Entrar
                </button>
            </form>
        </div>

        <p class="text-center mt-4 mb-0" style="color:rgba(255,255,255,.45);font-size:.8125rem">
            ¿No podés entrar? Hablá con Los Quevedo.
        </p>
    </div>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
