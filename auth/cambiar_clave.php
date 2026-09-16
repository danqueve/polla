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

$pageTitle        = 'Cambiar contrasena · ' . APP_NAME;
$navSeccion       = '';
$pageSectionTitle = 'Cambiar Contraseña';
$breadcrumb       = [
    ['label' => 'Cambiar contraseña', 'url' => ''],
];

require __DIR__ . '/../includes/admin_head.php';
require __DIR__ . '/../includes/admin_sidebar.php';
require __DIR__ . '/../includes/admin_topbar.php';
?>

<main class="g-content">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Cambiar contraseña</h1>
            <p class="g-page-subtitle">Mínimo 8 caracteres</p>
        </div>
        <a href="<?= APP_URL ?>/admin/index.php" class="g-btn g-btn--outline">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>

    <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errores as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="g-card g-animate g-animate-delay-1">
                <div class="g-card__body">
                    <form method="post" novalidate>
                        <?= csrfField() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="actual">Contraseña actual</label>
                            <input type="password" class="form-control form-control-lg" id="actual" name="actual"
                                   autocomplete="current-password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="nueva">Contraseña nueva</label>
                            <input type="password" class="form-control form-control-lg" id="nueva" name="nueva"
                                   autocomplete="new-password" minlength="8" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-muted" for="repetida">Repetir la nueva</label>
                            <input type="password" class="form-control form-control-lg" id="repetida" name="repetida"
                                   autocomplete="new-password" minlength="8" required>
                        </div>

                        <button type="submit" class="g-btn g-btn--primary w-100">
                            <i class="bi bi-check-lg"></i> Guardar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require __DIR__ . '/../includes/admin_foot.php';
