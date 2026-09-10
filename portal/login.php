<?php
/**
 * El formulario de login vive unificado en auth/login.php. Esta pagina
 * sigue existiendo porque requireCliente() y cualquier link/bookmark
 * viejo todavia apuntan aca.
 *
 * A proposito NO llama a getFlash(): si hay un flash pendiente (por
 * ejemplo, requireCliente() rebotando una cuenta rechazada o dada de
 * baja) tiene que quedar intacto en esta misma sesion POLLA_CLIENTE
 * para que auth/login.php lo pueda leer despues del redirect.
 */
require_once __DIR__ . '/../config/portal.php';

if (clienteLogueado()) {
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

header('Location: ' . APP_URL . '/auth/login.php');
exit;
