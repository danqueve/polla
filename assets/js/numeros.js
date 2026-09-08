/* ============================================================
   Grilla de casillas numéricas — la usan la carga de jugada
   (10 números, sin repetir) y la carga de sorteo (20 números,
   donde repetir SÍ vale porque el extracto puede sacar dos veces
   el mismo).

   Se configura desde el HTML, en el <form>:
       data-numeros="jugada|sorteo"
       data-repetidos="si|no"

   Todo lo que hace es comodidad y feedback inmediato: la
   validación de verdad la hacen JugadaService y SorteoService en
   el servidor, así que nada depende de que el JS haya corrido.
   ============================================================ */

(function () {
    'use strict';

    const form = document.querySelector('form[data-numeros]');
    if (!form) return;

    const permiteRepetidos = form.dataset.repetidos === 'si';

    const casillas   = Array.from(form.querySelectorAll('.casilla__input'));
    const contador   = document.getElementById('contador-numeros');
    const boton      = document.getElementById('btn-confirmar');
    const tablero    = document.getElementById('tablero-numeros');
    const aviso      = document.getElementById('aviso-repetidos');
    const btnLimpiar = document.getElementById('btn-limpiar');
    // Campos extra que también tienen que estar completos para habilitar
    // el envío (la fecha del sorteo, el cliente de la jugada).
    const requisitos = Array.from(form.querySelectorAll('[data-requerido]'));

    const total = casillas.length;
    if (!total) return;

    // ── Estado derivado ─────────────────────────────────────

    /** Valor normalizado de una casilla: "7" y "07" son el mismo 7. */
    function valorDe(casilla) {
        const limpio = casilla.value.replace(/\D/g, '');
        return limpio === '' ? null : parseInt(limpio, 10);
    }

    function dosCifras(n) {
        return String(n).padStart(2, '0');
    }

    function repintar() {
        const actuales = casillas.map(valorDe);
        const cargados = actuales.filter((n) => n !== null);

        // Sólo interesa marcar repetidos donde están prohibidos.
        let repetidos = new Set();
        if (!permiteRepetidos) {
            const veces = new Map();
            cargados.forEach((n) => veces.set(n, (veces.get(n) || 0) + 1));
            repetidos = new Set([...veces].filter(([, c]) => c > 1).map(([n]) => n));
        }

        casillas.forEach((casilla, i) => {
            const n = actuales[i];
            const mal = n !== null && repetidos.has(n);
            casilla.classList.toggle('cargada', n !== null && !mal);
            casilla.classList.toggle('repetida', mal);
            casilla.setAttribute('aria-invalid', mal ? 'true' : 'false');
        });

        // En una jugada cuentan los distintos; en un extracto, las casillas
        // llenas, porque el mismo número puede ocupar dos posiciones.
        const llevados = permiteRepetidos ? cargados.length : new Set(cargados).size;
        const completo = llevados === total && repetidos.size === 0;

        if (contador) {
            contador.textContent = llevados + '/' + total;
            contador.classList.toggle('contador--completo', completo);
        }

        if (aviso) {
            aviso.hidden = repetidos.size === 0;
            if (repetidos.size > 0) {
                const lista = [...repetidos].sort((a, b) => a - b).map(dosCifras).join(', ');
                aviso.textContent = 'Repetido: ' + lista + '. Cada número puede ir una sola vez.';
            }
        }

        if (boton) {
            const faltaAlgo = requisitos.some((campo) => campo.value.trim() === '');
            boton.disabled = !completo || faltaAlgo;
        }

        if (tablero) {
            const marcados = new Set(cargados);
            Array.from(tablero.children).forEach((celda, n) => {
                celda.classList.toggle('marcada', marcados.has(n));
            });
        }
    }

    // ── Interacción ─────────────────────────────────────────

    casillas.forEach((casilla, i) => {
        casilla.addEventListener('input', function () {
            // El teclado numérico de Android deja pasar signos y espacios.
            this.value = this.value.replace(/\D/g, '').slice(0, 2);

            // Con las dos cifras completas saltamos solo a la siguiente,
            // que es lo que espera quien viene tipeando de corrido.
            if (this.value.length === 2 && i < total - 1) {
                casillas[i + 1].focus();
                casillas[i + 1].select();
            }
            repintar();
        });

        casilla.addEventListener('blur', function () {
            // "7" al salir del campo queda "07": así se lee la quiniela.
            if (this.value.length === 1) {
                this.value = '0' + this.value;
                repintar();
            }
        });

        casilla.addEventListener('focus', function () {
            this.select();
        });

        casilla.addEventListener('keydown', function (ev) {
            if (ev.key === 'Backspace' && this.value === '' && i > 0) {
                ev.preventDefault();
                casillas[i - 1].focus();
                casillas[i - 1].select();
            }
            if (ev.key === 'ArrowLeft' && i > 0) {
                casillas[i - 1].focus();
            }
            if (ev.key === 'ArrowRight' && i < total - 1) {
                casillas[i + 1].focus();
            }
        });

        // Pegar "01 02 03 ..." en cualquier casilla reparte la lista desde
        // ahí: sirve para copiar los números de un WhatsApp o el extracto
        // de la página de la quiniela.
        casilla.addEventListener('paste', function (ev) {
            const texto = (ev.clipboardData || window.clipboardData).getData('text') || '';
            const trozos = texto.match(/\d{1,2}/g);
            if (!trozos || trozos.length < 2) return;

            ev.preventDefault();
            trozos.slice(0, total - i).forEach((trozo, j) => {
                casillas[i + j].value = dosCifras(parseInt(trozo, 10) % 100);
            });
            const ultima = Math.min(i + trozos.length, total - 1);
            casillas[ultima].focus();
            repintar();
        });
    });

    requisitos.forEach((campo) => {
        campo.addEventListener('change', repintar);
        campo.addEventListener('input', repintar);
    });

    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function () {
            casillas.forEach((c) => { c.value = ''; });
            repintar();
            casillas[0].focus();
        });
    }

    // Evita el doble envío por doble toque en el celular
    form.addEventListener('submit', function () {
        if (boton) {
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        }
    });

    repintar();
})();
