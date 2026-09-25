<?php
/**
 * Cabecera comun. Las pantallas definen antes de incluirla:
 *   $pageTitle    titulo del navegador
 *   $navSeccion   item del menu inferior que queda activo
 *   $bodyClass    clases extra del <body>
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover habilita el safe-area del notch y la barra de inicio -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d4a2d">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Decena de Oro">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle ?? APP_NAME) ?></title>

    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <link rel="icon" href="<?= APP_URL ?>/assets/icons/icon-192.png">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/icons/icon-512.png">

    <?php
    // Todo el front-end se sirve desde este mismo dominio, a proposito:
    // antes Bootstrap, los iconos y las fuentes venian de jsdelivr y de
    // Google Fonts, y encima las fuentes se pedian con un @import
    // adentro de app.css (serialmente bloqueante: habia que bajar y
    // parsear app.css antes de que el navegador supiera que las
    // necesitaba). Con una conexion de celular mala eso se veia como
    // "la pagina tarda en mostrar los datos": el texto estaba, pero los
    // iconos eran cuadraditos vacios y la tipografia saltaba. Ademas
    // asi el service worker puede precachearlo y la PWA funciona
    // offline de verdad.
    //
    // filemtime() como cache-buster: se actualiza solo con cada cambio
    // real del archivo, a diferencia de APP_VERSION, que es una
    // constante manual que nadie se acuerda de subir.
    $_v = static fn(string $rel): string
        => (string) (@filemtime(BASE_PATH . '/' . $rel) ?: APP_VERSION);
    ?>
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap/bootstrap.min.css?v=<?= $_v('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css?v=<?= $_v('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/vendor/fuentes-portal.css?v=<?= $_v('assets/vendor/fuentes-portal.css') ?>">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= $_v('assets/css/app.css') ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
