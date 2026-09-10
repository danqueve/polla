/* ============================================================
   Mostrar/ocultar contraseña: boton con data-toggle-password="id"
   alterna el type del input con ese id.
   ============================================================ */

(function () {
    'use strict';

    document.querySelectorAll('[data-toggle-password]').forEach(function (boton) {
        const input = document.getElementById(boton.getAttribute('data-toggle-password'));
        if (!input) return;

        boton.addEventListener('click', function () {
            const oculta = input.type === 'password';
            input.type = oculta ? 'text' : 'password';

            const icono = boton.querySelector('i');
            if (icono) icono.className = oculta ? 'bi bi-eye-slash' : 'bi bi-eye';

            boton.setAttribute('aria-label', oculta ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    });
})();
