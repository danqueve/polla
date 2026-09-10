/* ============================================================
   Copiar al portapapeles: cualquier boton con data-copiar="valor"
   copia ese texto y muestra una confirmacion breve.
   ============================================================ */

(function () {
    'use strict';

    document.querySelectorAll('[data-copiar]').forEach(function (boton) {
        const original = boton.innerHTML;

        boton.addEventListener('click', function () {
            const valor = boton.getAttribute('data-copiar') || '';
            if (!valor || !navigator.clipboard) return;

            navigator.clipboard.writeText(valor).then(function () {
                boton.innerHTML = '<i class="bi bi-check-lg"></i> Copiado';
                boton.disabled = true;
                setTimeout(function () {
                    boton.innerHTML = original;
                    boton.disabled = false;
                }, 1500);
            });
        });
    });
})();
