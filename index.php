<?php
/**
 * Punto de entrada. Manda a cada quien a su mundo: el panel y el
 * portal tienen sesiones distintas, asi que se preguntan por separado.
 */
require_once __DIR__ . '/config/app.php';

if (isLoggedIn()) {
    header('Location: ' . APP_URL . '/admin/index.php');
    exit;
}

// La sesion del portal vive en otra cookie: alcanza con mirar si existe
// para no mandar a un cliente al login del panel.
if (!empty($_COOKIE['POLLA_CLIENTE'])) {
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

header('Location: ' . APP_URL . '/auth/login.php');
exit;
