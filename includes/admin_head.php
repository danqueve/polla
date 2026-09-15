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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <?php
    $_appCssVersion = @filemtime(BASE_PATH . '/assets/css/app.css') ?: APP_VERSION;
    $_adminCssVersion = @filemtime(BASE_PATH . '/assets/css/admin.css') ?: APP_VERSION;
    ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= $_appCssVersion ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css?v=<?= $_adminCssVersion ?>">
</head>
<body class="admin-gentelella <?= e($bodyClass ?? '') ?>">
<div class="g-sidebar-overlay" id="gSidebarOverlay"></div>
