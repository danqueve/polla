<?php
require_once __DIR__ . '/../config/vendedor.php';

cerrarSesionVendedor();
setFlash('info', 'Cerraste tu sesión. Hasta la próxima.');

header('Location: ' . APP_URL . '/auth/login.php');
exit;
