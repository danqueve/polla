<?php
require_once __DIR__ . '/../config/app.php';

use Polla\Services\AuthService;

(new AuthService(getPDO()))->logout();

header('Location: ' . APP_URL . '/auth/login.php');
exit;
