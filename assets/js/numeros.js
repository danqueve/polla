/* ============================================================
   Grilla de casillas numéricas — la usan la carga de jugada
   (10 números por jugada, sin repetir; puede haber varias jugadas
   en una misma carga) y la carga de sorteo (20 números, donde
   repetir SÍ vale porque el extracto puede sacar dos veces el
   mismo número).

   Se configura desde el HTML con data-numeros="jugada|sorteo" y
   data-repetidos="si|no" en cada "raíz" a validar de forma
   independiente: en el sorteo es el <form> entero (un solo set);
   en la carga de jugadas es cada bloque de "una jugada" por
   separado, así cada set de 10 valida sus propios repetidos sin
   mezclarse con los demás. El contador y el botón de confirmar son
   compartidos por toda la página (no hay uno por jugada), así que
   el botón se habilita recién cuando TODAS las raíces activas están
   completas.

   Todo lo que hace es comodidad y feedback inmediato: la validación
   de verdad la hacen JugadaService y SorteoService en el servidor,
   así que nada depende de que el JS haya corrido.
   ============================================================ */

(function () {
    'use strict';

    const form = document.querySelector('form');
    if (!form) return;

    /**
     * Activa una raíz de casillas (el form entero, o un bloque de
     * jugada dentro de él). Devuelve un objeto vivo con el estado de
     * esa raíz, o null si no tiene casillas.
     */
    function activar(raiz) {
        const permiteRepetidos = raiz.dataset.repetidos === 'si';

        const casillas   = Array.from(raiz.querySelectorAll('.casilla__input'));
        const tablero    = raiz.querySelector('.js-tablero');
        const aviso      = raiz.querySelector('.js-aviso');
        const btnLimpiar = raiz.querySelector('.js-limpiar');

        const total = casillas.length;
        if (!total) return null;

        const estado = { completo: false, cargados: 0, total };

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
            estado.completo = llevados === total && repetidos.size === 0;
            estado.cargados = llevados;

            if (aviso) {
                aviso.hidden = repetidos.size === 0;
                if (repetidos.size > 0) {
                    const lista = [...repetidos].sort((a, b) => a - b).map(dosCifras).join(', ');
                    aviso.textContent = 'Repetido: ' + lista + '. Cada número puede ir una sola vez.';
                }
            }

            if (tablero) {
                const marcados = new Set(cargados);
                Array.from(tablero.children).forEach((celda, n) => {
                    celda.classList.toggle('marcada', marcados.has(n));
                });
            }

            form.dispatchEvent(new CustomEvent('numeros:cambio', { bubbles: false }));
        }

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

        if (btnLimpiar) {
            btnLimpiar.addEventListener('click', function () {
                casillas.forEach((c) => { c.value = ''; });
                repintar();
                casillas[0].focus();
            });
        }

        repintar();

        return { raiz, estado };
    }

    function activarTodas(contenedor) {
        const raices = [];
        if (contenedor.matches('[data-numeros]')) raices.push(contenedor);
        raices.push(...contenedor.querySelectorAll('[data-numeros]'));
        return raices.map(activar).filter(Boolean);
    }

    let grupos = activarTodas(form);

    // ── Orquestador: un solo contador y un solo botón para toda la
    // página, aunque haya varias jugadas cargándose a la vez. ──────

    const contador   = document.getElementById('contador-numeros');
    const boton      = document.getElementById('btn-confirmar');
    const requisitos = Array.from(form.querySelectorAll('[data-requerido]'));

    function revisarTodo() {
        const cargados = grupos.reduce((acc, g) => acc + g.estado.cargados, 0);
        const total    = grupos.reduce((acc, g) => acc + g.estado.total, 0);
        const todoCompleto = grupos.length > 0 && grupos.every((g) => g.estado.completo);

        if (contador) {
            contador.textContent = cargados + '/' + total;
            contador.classList.toggle('contador--completo', todoCompleto);
        }

        if (boton) {
            const faltaAlgo = requisitos.some((campo) => campo.value.trim() === '');
            boton.disabled = !todoCompleto || faltaAlgo;
        }
    }

    form.addEventListener('numeros:cambio', revisarTodo);
    requisitos.forEach((campo) => {
        campo.addEventListener('change', revisarTodo);
        campo.addEventListener('input', revisarTodo);
    });

    // ── Agregar / quitar jugadas: solo existe en la carga múltiple. ──

    const contenedorGrupos = document.getElementById('grupos-jugada');
    const plantilla        = document.getElementById('plantilla-grupo-jugada');
    const btnAgregar       = document.getElementById('btn-agregar-jugada');

    function bloquesActuales() {
        return Array.from(contenedorGrupos.querySelectorAll('[data-numeros]'));
    }

    function renumerar() {
        const bloques = bloquesActuales();
        bloques.forEach((bloque, i) => {
            bloque.querySelectorAll('[name]').forEach((campo) => {
                campo.name = campo.name.replace(/grupos\[\d+\]/, 'grupos[' + i + ']');
            });
            const numero = bloque.querySelector('.js-grupo-numero');
            if (numero) numero.textContent = String(i + 1);
            const btnQuitar = bloque.querySelector('.js-quitar-grupo');
            if (btnQuitar) btnQuitar.hidden = bloques.length <= 1;
        });
        document.dispatchEvent(new CustomEvent('grupos:cambio', { detail: { cantidad: bloques.length } }));
    }

    if (contenedorGrupos && plantilla && btnAgregar) {
        btnAgregar.addEventListener('click', function () {
            const indice = bloquesActuales().length;
            const nodo = plantilla.content.firstElementChild.cloneNode(true);
            nodo.querySelectorAll('[name]').forEach((campo) => {
                campo.name = campo.name.replace(/__INDICE__/g, String(indice));
            });
            contenedorGrupos.appendChild(nodo);

            const nuevoGrupo = activar(nodo);
            if (nuevoGrupo) grupos.push(nuevoGrupo);

            renumerar();
            revisarTodo();

            const primerInput = nodo.querySelector('.casilla__input');
            if (primerInput) primerInput.focus();
        });

        contenedorGrupos.addEventListener('click', function (ev) {
            const btnQuitar = ev.target.closest('.js-quitar-grupo');
            if (!btnQuitar) return;
            const bloque = btnQuitar.closest('[data-numeros]');
            if (!bloque || bloquesActuales().length <= 1) return;

            grupos = grupos.filter((g) => g.raiz !== bloque);
            bloque.remove();

            renumerar();
            revisarTodo();
        });
    }

    // Evita el doble envío por doble toque en el celular
    form.addEventListener('submit', function () {
        if (boton) {
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';
        }
    });

    // Si la pagina volvio con varias jugadas ya cargadas (repoblado tras
    // un error de validacion), sincroniza los botones "Quitar" y el
    // resumen de pago con la cantidad real en vez de asumir que hay
    // una sola.
    if (contenedorGrupos) {
        renumerar();
    }

    revisarTodo();
})();
