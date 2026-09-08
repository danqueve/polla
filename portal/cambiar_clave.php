<?php
/**
 * Cambio de clave del cliente.
 *
 * Es la unica pantalla que se puede ver con debe_cambiar_clave = 1,
 * de ahi el requireCliente(true). Con el flag prendido no hay forma
 * de llegar a ninguna otra: requireCliente() redirige aca.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\ClienteAuthService;
use Polla\Support\ValidacionException;

requireCliente(true);

$db  = getPDO();
$cli = clienteActual();

// Con el flag prendido es el primer ingreso: no se pide la clave
// actual porque el cliente acaba de escribirla en el login.
$stmt = $db->prepare('SELECT debe_cambiar_clave FROM clientes WHERE id = :id');
$stmt->execute([':id' => clienteActualId()]);
$obligatorio = (int) $stmt->fetchColumn() === 1;

$errores = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $errores[] = 'La página estuvo abierta demasiado tiempo. Probá de nuevo.';
    } else {
        try {
            (new ClienteAuthService($db))->cambiarPassword(
                (int) clienteActualId(),
                $_POST['nueva']    ?? '',
                $_POST['repetida'] ?? '',
                $obligatorio ? null : ($_POST['actual'] ?? '')
            );
            setFlash('success', 'Listo, tu contraseña quedó cambiada.');
            header('Location: ' . APP_URL . '/portal/index.php');
            exit;
        } catch (ValidacionException $e) {
            $errores = $e->errores();
        }
    }
}

$pageTitle  = 'Tu contraseña · ' . APP_NAME;
$bodyClass  = 'sin-barra';
require __DIR__ . '/../includes/head.php';
require __DIR__ . '/../includes/portal_cabecera.php';
?>

<main class="pantalla">

    <?php if ($obligatorio): ?>
        <div class="aviso-clave mb-4">
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-shield-lock-fill flex-shrink-0" style="font-size:1.25rem"></i>
                <div>
                    <p class="fw-bold mb-1">Elegí tu contraseña</p>
                    <p class="mb-0" style="font-size:.9375rem">
                        Entraste con tu DNI. Antes de seguir, poné una contraseña
                        que sepas solamente vos.
                    </p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <a href="<?= APP_URL ?>/portal/index.php" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <h1 class="pantalla__titulo mb-4">Cambiar mi contraseña</h1>
    <?php endif; ?>

    <?php if ($errores): ?>
        <div class="alert alert-danger" role="alert">
            <?php foreach ($errores as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="tarjeta p-3" novalidate>
        <?= csrfField() ?>

        <?php if (!$obligatorio): ?>
            <div class="mb-3">
                <label class="form-label" for="actual">Tu contraseña de ahora</label>
                <input type="password" class="form-control" id="actual" name="actual"
                       autocomplete="current-password" required>
            </div>
        <?php endif; ?>

        <div class="mb-3">
            <label class="form-label" for="nueva">Contraseña nueva</label>
            <input type="password" class="form-control" id="nueva" name="nueva"
                   autocomplete="new-password" minlength="6" required
                   <?= $obligatorio ? 'autofocus' : '' ?>>
            <div class="form-text">Al menos 6 caracteres, y que no sea tu DNI.</div>
        </div>

        <div class="mb-4">
            <label class="form-label" for="repetida">Repetila</label>
            <input type="password" class="form-control" id="repetida" name="repetida"
                   autocomplete="new-password" minlength="6" required>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg"></i> Guardar
        </button>
    </form>

    <?php if ($obligatorio): ?>
        <p class="text-center mt-3 mb-0">
            <a href="<?= APP_URL ?>/portal/logout.php" class="fila__meta">Salir</a>
        </p>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/../includes/foot.php'; ?>
