<?php
// ============================================================
// config/app.php - Configuracion global, sesion y helpers
//
// Todo punto de entrada (auth/, admin/, portal/) arranca con:
//     require_once __DIR__ . '/../config/app.php';
// ============================================================

define('APP_NAME',    'Polla Semanal Los Quevedo');
define('APP_SHORT',   'Los Quevedo');
define('APP_VERSION', '1.0.0');
define('BASE_PATH',   dirname(__DIR__));

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

// ── Sesion ──────────────────────────────────────────────────
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_samesite', 'Lax');
if (APP_ENV === 'production') {
    ini_set('session.cookie_secure', '1');
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
// Autenticacion del panel administrativo
// ============================================================

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): array
{
    return [
        'id'      => $_SESSION['user_id']      ?? null,
        'usuario' => $_SESSION['user_usuario'] ?? '',
        'nombre'  => $_SESSION['user_nombre']  ?? '',
        'rol'     => $_SESSION['user_rol']     ?? '',
    ];
}

function currentUserId(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['user_rol'] ?? '') === 'admin';
}

/** Cualquier usuario del panel: admin o supervisor. */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/** Acciones reservadas al admin (borrados, usuarios, parametros). */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        setFlash('danger', 'Esa accion es exclusiva del administrador.');
        header('Location: ' . APP_URL . '/admin/index.php');
        exit;
    }
}

/** Corta la ejecucion si la peticion no es POST con CSRF valido. */
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
