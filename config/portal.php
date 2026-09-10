<?php
// ============================================================
// config/portal.php - Sesion y permisos del portal del cliente
//
// Todo punto de entrada de portal/ arranca con:
//     require_once __DIR__ . '/../config/portal.php';
//
// La separacion con el panel no es una condicion que se evalua:
// es que la cookie se llama distinto. El panel lee PHPSESSID y el
// portal lee POLLA_CLIENTE, asi que la sesion de un cliente es
// literalmente invisible para las paginas de /admin, y viceversa.
// ============================================================

require_once __DIR__ . '/bootstrap.php';

/** Nombre de cookie propio: esto es lo que aisla las dos sesiones. */
define('PORTAL_SESSION_NAME', 'POLLA_CLIENTE');

if (session_status() === PHP_SESSION_NONE) {
    session_name(PORTAL_SESSION_NAME);
    session_start();
}


// ============================================================
// Sesion del cliente
// ============================================================

function clienteLogueado(): bool
{
    return isset($_SESSION['cliente_id']);
}

function clienteActualId(): ?int
{
    return isset($_SESSION['cliente_id']) ? (int) $_SESSION['cliente_id'] : null;
}

function clienteActual(): array
{
    return [
        'id'          => $_SESSION['cliente_id']    ?? null,
        'nombre'      => $_SESSION['cliente_nombre'] ?? '',
        'nro_cliente' => $_SESSION['cliente_nro']    ?? '',
    ];
}

/**
 * Guarda de todas las pantallas del portal.
 *
 * Ademas de exigir sesion, relee de la base en cada request `activo`:
 * si lo dieron de baja, la sesion se cierra en el acto en vez de seguir
 * andando hasta que venza sola. Es una lectura por clave primaria, asi
 * que el costo es despreciable y evita confiar en un flag guardado en
 * la sesion que puede quedar viejo.
 *
 * @param bool $permitirPendiente true solo en portal/pendiente.php, que
 *                                es la unica pantalla accesible con la
 *                                cuenta todavia sin aprobar.
 */
function requireCliente(bool $permitirPendiente = false): void
{
    if (!clienteLogueado()) {
        header('Location: ' . APP_URL . '/portal/login.php');
        exit;
    }

    $stmt = getPDO()->prepare(
        'SELECT activo, estado FROM clientes WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => clienteActualId()]);
    $fila = $stmt->fetch();

    if (!$fila || (int) $fila['activo'] !== 1) {
        cerrarSesionCliente();
        setFlash('danger', 'Tu acceso fue dado de baja. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
        header('Location: ' . APP_URL . '/portal/login.php');
        exit;
    }

    if ($fila['estado'] === 'rechazado') {
        cerrarSesionCliente();
        setFlash('danger', 'Tu solicitud de alta fue rechazada. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
        header('Location: ' . APP_URL . '/portal/login.php');
        exit;
    }

    if ($fila['estado'] === 'pendiente' && !$permitirPendiente) {
        header('Location: ' . APP_URL . '/portal/pendiente.php');
        exit;
    }
}

/**
 * Cierra la sesion del portal sin tocar la del panel: borra solo la
 * cookie POLLA_CLIENTE.
 */
function cerrarSesionCliente(): void
{
    $flash = $_SESSION['flash'] ?? null;
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();

    // Reabrimos para poder dejarle un mensaje al que vuelve al login.
    session_name(PORTAL_SESSION_NAME);
    session_start();
    session_regenerate_id(true);
    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
}
