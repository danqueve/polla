/* ============================================================
   Service worker de Decena de Oro [PWA].

   Solo cachea los assets ESTATICOS propios del sitio (CSS, JS,
   iconos, el manifest) para que la app abra rapido. A proposito NO
   toca ninguna pagina .php ni ningun POST: el pozo, las jugadas y los
   sorteos cambian todo el tiempo, y cachear esas respuestas mostraria
   datos viejos (por ejemplo, un pozo ya liquidado) sin que la
   persona lo note. Tampoco cachea los recursos de CDN (Bootstrap,
   Bootstrap Icons, Google Fonts): esos ya vienen con su propio cache
   HTTP de larga duracion, y duplicarlo aca solo suma complejidad
   (fetch cross-origin = respuesta opaca, no se puede verificar).

   Bump de CACHE_NAME en cada release que cambie alguno de estos
   archivos: es lo unico que fuerza a los clientes ya instalados a
   pisar su cache vieja.
   ============================================================ */

const CACHE_NAME = 'decena-static-v1';

/**
 * Sin barra inicial a proposito: el sitio vive en la raiz en
 * produccion (decenadeoro.lol/service-worker.js) pero en una
 * subcarpeta en local (localhost/polla/service-worker.js). Una ruta
 * relativa se resuelve contra la ubicacion de ESTE archivo en los dos
 * casos; con "/assets/..." fijo, en local apuntaria a
 * localhost/assets/... (que no existe) en vez de
 * localhost/polla/assets/....
 */
const ARCHIVOS_PRECACHE = [
    'assets/css/app.css',
    'assets/js/copiar.js',
    'assets/js/mostrar_clave.js',
    'assets/js/numeros.js',
    'assets/js/promociones.js',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/icons/icon-maskable-512.png',
    'manifest.json',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(ARCHIVOS_PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((nombres) => Promise.all(
                nombres
                    .filter((nombre) => nombre !== CACHE_NAME)
                    .map((nombre) => caches.delete(nombre))
            ))
            .then(() => self.clients.claim())
    );
});

/**
 * Cache-first solo para /assets/ y el manifest, propios de este
 * origen. Todo lo demas (paginas .php, APIs, CDNs) pasa de largo sin
 * que este service worker lo toque -- ni result.respondWith().
 */
self.addEventListener('fetch', (event) => {
    // Se calculan contra el scope real de este service worker (la
    // carpeta donde vive), no contra la raiz del dominio -- mismo
    // motivo que la lista de precache de arriba.
    const assetsBase   = new URL('assets/', self.registration.scope).href;
    const manifestUrl  = new URL('manifest.json', self.registration.scope).href;

    const esEstaticoPropio = event.request.url.startsWith(assetsBase)
        || event.request.url === manifestUrl;

    if (event.request.method !== 'GET' || !esEstaticoPropio) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cacheado) => {
            const redFetch = fetch(event.request).then((respuesta) => {
                if (respuesta && respuesta.ok) {
                    const copia = respuesta.clone();
                    caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copia));
                }
                return respuesta;
            }).catch(() => cacheado);

            // Si ya esta en cache, la mostramos al toque (rapido) y
            // actualizamos en segundo plano; si no, esperamos la red.
            return cacheado || redFetch;
        })
    );
});
