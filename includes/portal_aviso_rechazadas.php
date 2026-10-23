<?php
/**
 * Aviso al cliente de que una solicitud suya fue rechazada (no se
 * proceso el pago). Lo usan "Mis jugadas" y "Armar jugada".
 *
 * Espera definidas:
 *   $rechazadas  salida de SolicitudService::rechazadasSinAtender()
 *   $tipoJuego   el juego de la pestaña actual (las rechazadas ya vienen
 *                filtradas por ese mismo juego)
 *
 * Deja de mostrarse solo cuando el cliente arma una solicitud nueva de
 * ese juego, o a los 7 dias (ver rechazadasSinAtender()).
 */
foreach ($rechazadas ?? [] as $_rechazada):
    $_cant = (int) $_rechazada['cantidad_jugadas'];
?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0" style="margin-top:.15rem" aria-hidden="true"></i>
        <div>
            <strong>Tu solicitud <span class="cifra"><?= e($_rechazada['numero_registro']) ?></span> no se procesó.</strong>
            <?= $_cant === 1 ? 'Esa jugada no participa' : 'Esas ' . $_cant . ' jugadas no participan' ?>
            del sorteo. Si ya pagaste, hablá con Decena de Oro.
            <div class="d-flex flex-wrap gap-2 mt-2">
                <a href="<?= APP_URL ?>/portal/jugar.php?tipo=<?= e($tipoJuego) ?>&amp;reintentar=<?= (int) $_rechazada['id'] ?>"
                   class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    Volver a <?= $_cant === 1 ? 'cargarla' : 'cargarlas' ?>
                </a>
                <a href="<?= APP_URL ?>/portal/solicitud.php?id=<?= (int) $_rechazada['id'] ?>"
                   class="btn btn-sm btn-outline-dark">
                    Ver detalle
                </a>
                <a href="<?= e(whatsappUrl()) ?>" target="_blank" rel="noopener"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i> Escribir por WhatsApp
                </a>
            </div>
        </div>
    </div>
<?php
endforeach;
unset($_rechazada, $_cant);
