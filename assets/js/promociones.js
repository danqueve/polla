/* ============================================================
   Total a pagar + sugerencia de promo de paquete (Fase 7).

   Escucha grupos:cambio (numeros.js), que ya se dispara con la
   cantidad de jugadas actual tanto en la carga multiple del staff
   como en la seleccion propia del cliente desde el portal, y desde
   ahi:

    - recalcula "Cantidad de jugadas" y el total a cobrar/pagar.
    - si la cantidad coincide con una promocion activa, la sugiere
      con la opcion de aplicarla a precio de paquete; si no coincide
      (o se agrega/saca una jugada despues de tildar "aplicar"), la
      oculta y vuelve a precio de lista.

   Este script se carga ANTES que numeros.js (ver pageScripts en
   cada pantalla) para llegar a tiempo al primer grupos:cambio que
   numeros.js dispara al terminar de armarse la pagina -mismo orden
   que tenian los <script> inline que reemplaza.

   El bloque de sugerencia (#promo-sugerida) es opcional: una
   pantalla que no lo tenga solo recalcula cantidad/total, sin promos.
   ============================================================ */
(function () {
    'use strict';

    var totalEl = document.getElementById('total-a-cobrar');
    if (!totalEl) return;

    var cantidadEl   = document.getElementById('cantidad-jugadas');
    var btnTextoEl   = document.getElementById('btn-confirmar-texto');
    var plantillaBtn = btnTextoEl ? btnTextoEl.dataset.plantilla : null;
    var montoLista   = parseFloat(totalEl.dataset.monto);

    var bloquePromo  = document.getElementById('promo-sugerida');
    var textoPromoEl = document.getElementById('promo-sugerida-texto');
    var checkPromo   = document.getElementById('promo-aplicar');
    var inputPromo   = document.getElementById('promocion_id');

    var promos = {};
    if (bloquePromo) {
        try {
            promos = JSON.parse(bloquePromo.dataset.promos || '{}');
        } catch (err) {
            promos = {};
        }
    }

    var promoVigente = null;

    function formatearPesos(monto) {
        return '$' + Math.round(monto).toLocaleString('es-AR', { maximumFractionDigits: 0 });
    }

    function aplicaPromo() {
        return !!(promoVigente && checkPromo && checkPromo.checked);
    }

    function recalcular(cantidad) {
        var total = aplicaPromo() ? promoVigente.precio_total : montoLista * cantidad;
        var texto = formatearPesos(total);

        if (cantidadEl) cantidadEl.textContent = cantidad;
        totalEl.textContent = texto;
        if (btnTextoEl && plantillaBtn) btnTextoEl.textContent = plantillaBtn + ' ' + texto;
        if (inputPromo) inputPromo.value = aplicaPromo() ? promoVigente.id : '';
    }

    document.addEventListener('grupos:cambio', function (ev) {
        var cantidad = ev.detail.cantidad;

        if (bloquePromo) {
            promoVigente = promos[cantidad] || null;

            if (promoVigente) {
                var porJugada = promoVigente.precio_total / promoVigente.cantidad_jugadas;
                if (textoPromoEl) {
                    textoPromoEl.textContent = 'Con ' + cantidad + ' jugadas te conviene la promo: '
                        + formatearPesos(promoVigente.precio_total) + ' en total ('
                        + formatearPesos(porJugada) + ' por jugada).';
                }
                bloquePromo.hidden = false;
            } else {
                bloquePromo.hidden = true;
                if (checkPromo) checkPromo.checked = false;
            }
        }

        recalcular(cantidad);
    });

    if (checkPromo) {
        checkPromo.addEventListener('change', function () {
            var cantidadActual = cantidadEl ? (parseInt(cantidadEl.textContent, 10) || 1) : 1;
            recalcular(cantidadActual);
        });
    }
})();
