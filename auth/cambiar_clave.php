<?php
/** Cambio de contrasena del propio usuario del panel. */
require_once __DIR__ . '/../config/app.php';

use Polla\Services\AuthService;
use Polla\Support\ValidacionException;

requireLogin();

$errores = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La sesion expiro. Reintentá.';
    } else {
        try {
            (new AuthService(getPDO()))->cambiarPassword(
                currentUserId(),
                $_POST['actual']    ?? '',
                $_POST['nueva']     ?? '',
                $_POST['repetida']  ?? ''
            );
            setFlash('success', 'Listo, tu contrasena quedo cambiada.');
            header('Location: ' . APP_URL . '/admin/index.php');
            exit;
        } catch (ValidacionException $e) {
            $errores = $e->errores();
        }
    }
}

$pageTitle  = 'Cambiar contrasena · ' . APP_NAME;
$navSeccion = '';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Volver
    </a>

    <h1 class="pantalla__titulo">Cambiar contrasena</h1>
    <p class="pantalla__bajada">Minimo 8 caracteres.</p>

    <?php if ($errores): ?>
        <div class="alert alert-danger mt-3" role="alert">
            <?php foreach ($errores as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta p-3 mt-3" novalidate>
        <?= csrfField() ?>

        <div class="mb-3">
            <label class="form-label" for="actual">Contrasena actual</label>
            <input type="password" class="form-control" id="actual" name="actual"
                   autocomplete="current-password" required>
        </div>

        <div class="mb-3">
            <label class="form-label" for="nueva">Contrasena nueva</label>
            <input type="password" class="form-control" id="nueva" name="nueva"
                   autocomplete="new-password" minlength="8" required>
        </div>

        <div class="mb-4">
            <label class="form-label" for="repetida">Repetir la nueva</label>
            <input type="password" class="form-control" id="repetida" name="repetida"
                   autocomplete="new-password" minlength="8" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Guardar
        </button>
    </form>
</main>

<?php
require __DIR__ . '/../includes/bottom_nav.php';
require __DIR__ . '/../includes/foot.php';
