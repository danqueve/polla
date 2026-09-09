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
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Decena de Oro">
    <meta name="robots" content="noindex, nofollow">

    <title><?= e($pageTitle ?? APP_NAME) ?></title>

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=<?= APP_VERSION ?>">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
