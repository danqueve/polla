<?php
/** Punto de entrada: manda al panel o al login segun la sesion. */
require_once __DIR__ . '/config/app.php';

header('Location: ' . APP_URL . (isLoggedIn() ? '/admin/index.php' : '/auth/login.php'));
exit;
