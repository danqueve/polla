<?php
// ============================================================
// config/vendedor.php - Sesion y permisos del panel del vendedor
// [Fase 11]
//
// Todo punto de entrada de vendedor/ arranca con:
//     require_once __DIR__ . '/../config/vendedor.php';
//
// Tercer "mundo" de sesion ademas del panel (config/app.php,
// PHPSESSID) y el portal (config/portal.php, POLLA_CLIENTE): cookie
// propia POLLA_VENDEDOR, asi que un vendedor no ve nada de /admin ni
// de /portal, y viceversa -- misma logica de aislamiento que ya usa
// el portal del cliente, aplicada a un mundo mas.
// ============================================================

require_once __DIR__ . '/bootstrap.php';

define('VENDEDOR_SESSION_NAME', 'POLLA_VENDEDOR');

if (session_status() === PHP_SESSION_NONE) {
    session_name(VENDEDOR_SESSION_NAME);
    session_start();
}


// ============================================================
// Sesion del vendedor
// ============================================================

function vendedorLogueado(): bool
{
    return isset($_SESSION['vendedor_id']);
}

function vendedorActualId(): ?int
{
    return isset($_SESSION['vendedor_id']) ? (int) $_SESSION['vendedor_id'] : null;
}

function vendedorActual(): array
{
    return [
        'id'              => $_SESSION['vendedor_id']             ?? null,
        'nombre'          => $_SESSION['vendedor_nombre']          ?? '',
        'codigo_referido' => $_SESSION['vendedor_codigo_referido'] ?? '',
    ];
}

/**
 * Guarda de todas las pantallas de vendedor/.
 *
 * Relee `activo` en cada request, igual criterio que requireCliente():
 * si el admin lo desactiva, la sesion se cierra en el acto en vez de
 * seguir andando hasta que venza sola.
 */
function requireVendedor(): void
{
    if (!vendedorLogueado()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }

    $stmt = getPDO()->prepare('SELECT activo FROM vendedores WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => vendedorActualId()]);
    $activo = $stmt->fetchColumn();

    if ($activo === false || (int) $activo !== 1) {
        cerrarSesionVendedor();
        setFlash('danger', 'Tu acceso fue dado de baja. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

/** Cierra la sesion del vendedor sin tocar el panel ni el portal. */
function cerrarSesionVendedor(): void
{
    $flash = $_SESSION['flash'] ?? null;
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();

    session_name(VENDEDOR_SESSION_NAME);
    session_start();
    session_regenerate_id(true);
    if ($flash) {
        $_SESSION['flash'] = $flash;
    }
}
