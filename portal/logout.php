<?php
require_once __DIR__ . '/../config/portal.php';

\Polla\Services\RememberTokenService::crearDesde(getPDO())->olvidar();
cerrarSesionCliente();
setFlash('info', 'Cerraste tu sesión. Hasta la próxima.');

header('Location: ' . APP_URL . '/portal/login.php');
exit;
