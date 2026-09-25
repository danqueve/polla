<?php
/**
 * Cabecera para el panel de Administración (Gentelella / Desktop + Mobile responsive).
 * Parámetros esperados:
 *   $pageTitle    título del navegador
 *   $navSeccion   sección activa del menú ('tablero', 'jugadas', 'sorteos', 'clientes', 'solicitudes', 'reportes', 'ciclos', 'sabados', 'usuarios', 'vendedores', 'referidos', 'liquidaciones', 'configuracion')
 *   $bodyClass    clases extra del <body>
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a2332">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Decena de Oro Admin">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle ?? APP_NAME . ' · Admin') ?></title>

    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon-512.png">

    <?php
    // Todo local, sin CDNs ni Google Fonts -- ver la nota extensa en
    // includes/head.php: el primer paint dejaba de depender de dos
    // dominios externos mas las descargas de fuentes que encadenaban,
    // y ahora el service worker puede precachearlo todo.
    $_v = static fn(string $rel): string
        => (string) (@filemtime(BASE_PATH . '/' . $rel) ?: APP_VERSION);
    ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/bootstrap.min.css?v=<?= $_v('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css?v=<?= $_v('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/fuentes-admin.css?v=<?= $_v('assets/vendor/fuentes-admin.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= $_v('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css?v=<?= $_v('assets/css/admin.css') ?>">
</head>
<body class="admin-gentelella <?= e($bodyClass ?? '') ?>">
<div class="g-sidebar-overlay" id="gSidebarOverlay"></div>
