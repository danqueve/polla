<?php
// ============================================================
// config/bootstrap.php - Nucleo compartido
//
// Todo lo que necesitan por igual el panel administrativo y el
// portal del cliente: entorno, conexion, autoloader y helpers.
//
// Deliberadamente NO arranca la sesion. De eso se encargan:
//   config/app.php     -> sesion del panel  (admin | supervisor)
//   config/portal.php  -> sesion del cliente
//
// Son dos cookies con nombres distintos, asi que cada mundo solo
// ve la suya. Ninguna pagina incluye este archivo directamente.
// ============================================================

define('APP_NAME',    'Decena de Oro');
define('APP_SHORT',   'Decena de Oro');
define('APP_VERSION', '1.1.0');
define('BASE_PATH',   dirname(__DIR__));

// Contacto por WhatsApp: numero de Tucuman en formato E.164 sin el "+",
// como lo pide un link wa.me (54 = Argentina, 9 = celular, 381 = area).
define('CONTACTO_WHATSAPP',        '5493813444178');
define('CONTACTO_WHATSAPP_LEGIBLE', '381 344-4178');

function whatsappUrl(): string
{
    return 'https://wa.me/' . CONTACTO_WHATSAPP;
}

// ── Entorno segun el host ───────────────────────────────────
$_appHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
if ($_appHost === 'localhost' || $_appHost === '127.0.0.1') {
    define('APP_URL', 'http://localhost/polla');
    define('APP_ENV', 'development');
} else {
    $_appScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    define('APP_URL', $_appScheme . '://' . $_appHost);
    define('APP_ENV', 'production');
}
unset($_appHost, $_appScheme);

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

// ── Headers de seguridad HTTP ───────────────────────────────
// Se fijan antes de cualquier salida. No interfieren con nada
// visible; son instrucciones para el navegador.
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0'); // desactivado a favor de CSP
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

// ── Ajustes de sesion, comunes a las dos ────────────────────
// Van aca porque hay que fijarlos antes de cualquier session_start().
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}

require_once __DIR__ . '/db.php';

// ── Autoloader de src/ (sin Composer) ───────────────────────
spl_autoload_register(static function (string $class): void {
    $prefix = 'Polla\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file     = BASE_PATH . '/src/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});


// ============================================================
// Cambio de "mundo" de sesion (panel / portal / vendedor)
// ============================================================

/**
 * Cambia la sesion activa a la que corresponde a otro nombre de cookie
 * (PHPSESSID, POLLA_CLIENTE o POLLA_VENDEDOR), sin perder la que ya
 * estuviera guardada en esa cookie. Hace falta porque PHP no permite
 * renombrar una sesion ya activa: session_write_close() la suelta, y
 * recien ahi se puede arrancar la otra leyendo su propia cookie.
 *
 * La usan auth/login.php (para probar login contra las tres tablas en
 * cascada) y los saltos sin clave entre panel de vendedor y portal del
 * cliente (vendedor/jugar.php, portal/panel_vendedor.php).
 */
function cambiarASesion(string $nombre): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
    session_name($nombre);
    session_id($_COOKIE[$nombre] ?? session_create_id());
    session_start();
}


// ============================================================
// Peticiones POST
// ============================================================

/**
 * Corta la ejecucion si la peticion no es POST con CSRF valido.
 * Sirve igual al panel y al portal: opera sobre la sesion que este
 * abierta, sea cual sea.
 *
 * Con el CSRF invalido no se guarda nada, igual que siempre. Lo que
 * cambio es la respuesta: antes era una pagina en blanco con "La sesion
 * expiro" (403) y se perdia todo lo tipeado. El caso real es un
 * formulario que quedo abierto hasta que vencio la sesion -- tipico del
 * cliente con la app del celular: "recordarme" le abre una sesion nueva
 * sin que se entere, y el formulario trae la clave CSRF de la vieja.
 * Ahora vuelve a la pantalla de donde vino, con lo tipeado ya cargado
 * (las pantallas lo repueblan con old(), como tras cualquier error) y un
 * aviso: se confirma de nuevo y listo.
 */
function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Metodo no permitido.');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $origen = paginaDeOrigen();
        // Solo se repuebla si el POST salio de una pantalla de esta misma
        // app: un formulario de otro sitio no deja nada cargado en la
        // sesion de nadie.
        if ($origen !== null) {
            setOld($_POST);
        }
        setFlash('warning',
            'La página estuvo abierta mucho tiempo y, por seguridad, no se guardó nada. '
          . 'Revisá los datos y volvé a confirmar.');
        header('Location: ' . ($origen ?? inicioDeLaSesionActual()));
        exit;
    }
}

/**
 * URL de la pantalla desde la que se envio este formulario (el Referer),
 * SOLO si es de esta misma app y es una pantalla, no el propio handler
 * que esta procesando el POST. null si no se puede confiar en ella: otro
 * sitio, otra app del mismo servidor, una ruta rara o ningun Referer.
 *
 * Se compara host y ruta, no el esquema: detras de un proxy, APP_URL
 * puede salir "http" con el sitio en https, y eso no la vuelve ajena.
 * La URL que se devuelve se arma siempre desde APP_URL, nunca se copia
 * la del navegador entera.
 */
function paginaDeOrigen(): ?string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer === '' || preg_match('/[\r\n\0]/', $referer)) {
        return null;
    }

    $ref  = parse_url($referer);
    $base = parse_url(APP_URL);
    if (!$ref || !$base || !isset($ref['host'], $ref['path'])) {
        return null;
    }

    $hostRef  = strtolower($ref['host']) . (isset($ref['port']) ? ':' . $ref['port'] : '');
    $hostBase = strtolower($base['host']) . (isset($base['port']) ? ':' . $base['port'] : '');
    if ($hostRef !== $hostBase) {
        return null;
    }

    $prefijo = rtrim($base['path'] ?? '', '/');       // "/polla" en local, "" en el VPS
    if (strpos($ref['path'], $prefijo . '/') !== 0) {
        return null;
    }
    $ruta = substr($ref['path'], strlen($prefijo));   // "/portal/jugar.php"
    if (!preg_match('#^(/[a-z0-9_]+)+\.php$#', $ruta)) {
        return null;
    }

    // A la pantalla del formulario, no de vuelta al handler.
    if ($prefijo . $ruta === ($_SERVER['SCRIPT_NAME'] ?? '')) {
        return null;
    }

    $query = isset($ref['query']) && $ref['query'] !== '' ? '?' . $ref['query'] : '';

    return rtrim(APP_URL, '/') . $ruta . $query;
}

/** La pantalla de inicio del "mundo" de la sesion abierta (panel, portal o vendedor). */
function inicioDeLaSesionActual(): string
{
    switch (session_name()) {
        case 'POLLA_CLIENTE':  return APP_URL . '/portal/index.php';
        case 'POLLA_VENDEDOR': return APP_URL . '/vendedor/index.php';
        default:               return APP_URL . '/admin/index.php';
    }
}

/**
 * Un POST que llega SIN sesion: vencio mientras el formulario estaba
 * abierto. La llaman requireLogin()/requireCliente() justo antes de
 * mandar al login.
 *
 * Deja el aviso para el login (que lee los avisos de las tres sesiones)
 * y, si el formulario salio de una pantalla de esta app, guarda lo
 * tipeado y a donde volver: despues de entrar de nuevo, destinoTrasLogin()
 * lo lleva de vuelta ahi con todo cargado. Nada se guarda en la base: se
 * vuelve a confirmar a mano, ya con sesion.
 */
function guardarFormularioSinSesion(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }

    $origen = paginaDeOrigen();
    if ($origen !== null) {
        setOld($_POST);
        // Con vencimiento: si en vez de volver a entrar enseguida lo hace
        // otro dia, no tiene sentido que aparezca en un formulario viejo.
        $_SESSION['volver_tras_login'] = ['url' => $origen, 'hasta' => time() + 30 * 60];
    }

    setFlash('warning', $origen !== null
        ? 'Tu sesión venció. Entrá de nuevo y vas a volver a lo que estabas cargando, sin perder nada.'
        : 'Tu sesión venció. Entrá de nuevo para seguir.');
}

/**
 * A donde mandar a alguien que acaba de iniciar sesion: la pantalla que
 * guardo guardarFormularioSinSesion() si todavia vale, o $porDefecto.
 * Se usa una sola vez: el login siguiente ya va al inicio de siempre.
 */
function destinoTrasLogin(string $porDefecto): string
{
    $volver = $_SESSION['volver_tras_login'] ?? null;
    unset($_SESSION['volver_tras_login']);

    if (is_array($volver) && is_string($volver['url'] ?? null) && (int) ($volver['hasta'] ?? 0) >= time()) {
        return $volver['url'];
    }

    // Vencida o inexistente: tampoco tiene que quedar el formulario viejo
    // esperando en la sesion.
    if ($volver !== null) {
        flushOld();
    }

    return $porDefecto;
}


// ============================================================
// CSRF
// ============================================================

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(?string $token): bool
{
    return is_string($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}


// ============================================================
// Flash messages
// ============================================================

function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Guarda lo que el usuario habia tipeado para repoblar el form tras un error.
 *
 * Nunca guarda claves: ni las del alta de usuarios/vendedores (password,
 * password2) ni las de "Cambiar contraseña" (actual, nueva, repetida).
 * Estas ultimas importan desde que requirePost() repuebla cualquier
 * formulario, sea cual sea, cuando vence la sesion.
 */
function setOld(array $data): void
{
    unset(
        $data['csrf_token'],
        $data['password'], $data['password2'],
        $data['actual'], $data['nueva'], $data['repetida']
    );
    $_SESSION['old'] = $data;
}

function old(string $campo, $default = '')
{
    return $_SESSION['old'][$campo] ?? $default;
}

function flushOld(): void
{
    unset($_SESSION['old']);
}


// ============================================================
// Presentacion
// ============================================================

/** htmlspecialchars corto, para usar en todas las vistas. */
function e($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 7 -> "07"  ·  formato de la quiniela. */
function num2($numero): string
{
    return str_pad((string) (int) $numero, 2, '0', STR_PAD_LEFT);
}

function formatPesos($monto): string
{
    return '$' . number_format((float) $monto, 0, ',', '.');
}

function formatPesosDec($monto): string
{
    return '$' . number_format((float) $monto, 2, ',', '.');
}

/** "2026-03-14 21:05:00" -> "14/03 21:05" */
function formatFechaHora(?string $sql): string
{
    if (!$sql) {
        return '-';
    }
    $ts = strtotime($sql);
    return $ts ? date('d/m H:i', $ts) : '-';
}

function formatFecha(?string $sql): string
{
    if (!$sql) {
        return '-';
    }
    $ts = strtotime($sql);
    return $ts ? date('d/m/Y', $ts) : '-';
}

/**
 * Nombre del dia en castellano. PHP formatea en ingles salvo que el
 * servidor tenga el locale es_AR instalado, cosa que en un cPanel
 * pelado no se puede dar por sentada.
 */
function nombreDia($fecha): string
{
    $dias = [1 => 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo'];

    $iso = $fecha instanceof DateTimeInterface
        ? (int) $fecha->format('N')
        : (int) date('N', is_int($fecha) ? $fecha : strtotime((string) $fecha));

    return $dias[$iso] ?? '';
}

/** "2026-09-08" -> "Martes 08/09" */
function formatFechaDia(?string $sql): string
{
    if (!$sql) {
        return '-';
    }
    $ts = strtotime($sql);
    return $ts ? nombreDia($ts) . ' ' . date('d/m', $ts) : '-';
}

/** Marca activo el item del menu que corresponde a la pantalla actual. */
function navActivo(string $seccion): string
{
    $actual = $GLOBALS['navSeccion'] ?? '';
    return $actual === $seccion ? ' active' : '';
}
