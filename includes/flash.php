<?php
/** Muestra y consume el mensaje flash pendiente, si lo hay. */
$_flash = getFlash();
if ($_flash):
    $_iconos = [
        'success' => 'bi-check-circle-fill',
        'danger'  => 'bi-exclamation-octagon-fill',
        'warning' => 'bi-exclamation-triangle-fill',
        'info'    => 'bi-info-circle-fill',
    ];
    $_icono = $_iconos[$_flash['type']] ?? $_iconos['info'];
?>
    <div class="alert alert-<?= e($_flash['type']) ?> d-flex align-items-start gap-2 mb-3"
         role="alert">
        <i class="bi <?= e($_icono) ?> flex-shrink-0" style="margin-top:.15rem"></i>
        <div><?= nl2br(e($_flash['msg'])) ?></div>
    </div>
<?php endif; ?>
