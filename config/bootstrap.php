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
// Peticiones POST
// ============================================================

/**
 * Corta la ejecucion si la peticion no es POST con CSRF valido.
 * Sirve igual al panel y al portal: opera sobre la sesion que este
 * abierta, sea cual sea.
 */
function requirePost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit('Metodo no permitido.');
    }
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('La sesion expiro. Volve a entrar y reintenta.');
    }
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

/** Guarda lo que el usuario habia tipeado para repoblar el form tras un error. */
function setOld(array $data): void
{
    unset($data['csrf_token'], $data['password'], $data['password2']);
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
