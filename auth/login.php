<?php
/**
 * Login del panel administrativo (admin / supervisor).
 * El login de clientes al portal va aparte, en la fase 3.
 */
require_once __DIR__ . '/../config/app.php';

use Polla\Services\AuthService;
use Polla\Support\ValidacionException;

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

$errores = [];
$usuario = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La pagina estuvo abierta demasiado tiempo. Reintentá.';
    } else {
        try {
            (new AuthService(getPDO()))->login($usuario, $_POST['password'] ?? '');
            header('Location: ' . APP_URL . '/admin/index.php');
            exit;
        } catch (ValidacionException $e) {
            $errores = $e->errores();
        }
    }
}

$pageTitle = 'Ingresar · ' . APP_NAME;
$bodyClass = 'sin-barra';
require __DIR__ . '/../includes/head.php';
?>

<main class="login">
    <div class="login__caja">

        <h1 class="login__marca">Decena<em>de Oro</em></h1>
        <p class="login__bajada">Panel de carga · Quiniela Nocturna de Tucuman</p>

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

            <form method="post" novalidate>
                <?= csrfField() ?>

                <div class="mb-3">
                    <label class="form-label" for="usuario">Usuario</label>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           value="<?= e($usuario) ?>"
                           autocomplete="username"
                           autocapitalize="none" autocorrect="off" spellcheck="false"
                           required autofocus>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="password">Contrasena</label>
                    <input type="password" class="form-control" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i>
                    Ingresar
                </button>
            </form>
        </div>

        <p class="text-center mt-4 mb-0" style="color:rgba(255,255,255,.45);font-size:.8125rem">
            Sos cliente? Tu acceso es por el portal, con tu DNI.
        </p>
    </div>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
