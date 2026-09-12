<?php
/** Cierre comun: bundle de Bootstrap + scripts propios de la pantalla. */
?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <?php foreach (($pageScripts ?? []) as $_src): ?>
        <script src="<?= APP_URL ?>/assets/js/<?= e($_src) ?>?v=<?= APP_VERSION ?>"></script>
    <?php endforeach; ?>

    <script>
        // APP_URL en vez de una ruta fija "/service-worker.js": el
        // sitio vive en la raiz en produccion pero en una subcarpeta
        // en local, mismo motivo que en service-worker.js.
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('<?= APP_URL ?>/service-worker.js', {
                    scope: '<?= APP_URL ?>/',
                });
            });
        }
    </script>
</body>
</html>
