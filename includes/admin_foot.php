<?php
/**
 * Cierre para el panel de Administración (Gentelella).
 * Incluye Bootstrap, scripts de sidebar toggle, mobile bottom nav y Service Worker.
 */
?>
    <?php if (strpos($bodyClass ?? '', 'con-accion-fija') === false): ?>
        <!-- Bottom Nav para móviles (se oculta automáticamente en desktop via CSS) -->
        <!-- Se omite en las pantallas con accion-fija: antes del rediseño
             Gentelella, bottom_nav.php se incluia a mano y esas 10
             pantallas (formularios con boton fijo tipo "Confirmar y
             cobrar") lo dejaban afuera a proposito -- las dos barras
             fijas al pie tapandose entre si dejaban una franja oscura
             asomando arriba del boton y la nav inaccesible. -->
        <?php require __DIR__ . '/bottom_nav.php'; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>

    <script>
    (function () {
        const toggleBtn = document.getElementById('gSidebarToggle');
        const sidebar = document.getElementById('gSidebar');
        const overlay = document.getElementById('gSidebarOverlay');

        function openSidebar() {
            if (sidebar) sidebar.classList.add('open');
            if (overlay) overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (sidebar && sidebar.classList.contains('open')) {
                    closeSidebar();
                } else {
                    openSidebar();
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', closeSidebar);
        }

        // Cerrar con ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    })();
    </script>

    <?php foreach (($pageScripts ?? []) as $_src): ?>
        <?php
        $_jsVersion = @filemtime(BASE_PATH . '/assets/js/' . $_src) ?: APP_VERSION;
        ?>
        <script src="<?= APP_URL ?>/assets/js/<?= e($_src) ?>?v=<?= $_jsVersion ?>"></script>
    <?php endforeach; ?>

    <script>
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
