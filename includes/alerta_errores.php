<?php
/**
 * Bloque de errores de validacion, compartido entre las pantallas que
 * manejan su propio array $errores (no el flash de sesion).
 * Espera definida: $errores (string[]).
 */
?>
<?php if (!empty($errores)): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-octagon-fill flex-shrink-0" style="margin-top:.15rem"></i>
        <div>
            <?php foreach ($errores as $error): ?>
                <div><?= e($error) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
