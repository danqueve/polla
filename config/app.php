<?php
// ============================================================
// config/app.php - Sesion y permisos del panel administrativo
//
// Todo punto de entrada de auth/ y admin/ arranca con:
//     require_once __DIR__ . '/../config/app.php';
//
// El portal del cliente NO usa este archivo: usa config/portal.php,
// que abre una sesion con otro nombre de cookie. Por eso un cliente
// que adivine una URL de /admin no llega a ningun lado: para estas
// paginas su sesion directamente no existe.
// ============================================================

require_once __DIR__ . '/bootstrap.php';

// Sesion del panel, con el nombre de cookie por defecto.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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
        // Si es un formulario que se envio con la sesion ya vencida, se
        // guarda lo tipeado para devolverlo despues de entrar de nuevo.
        guardarFormularioSinSesion();
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


// ============================================================
// Avisos del panel
// ============================================================

/**
 * Lo que esta esperando que el staff lo atienda: pagos de clientes sin
 * confirmar y autorregistros sin aprobar. Lo leen la campanita de la
 * topbar, los contadores del menu lateral y la hoja "Mas" del celular.
 *
 * Antes cada pantalla tenia que calcularlo y dejarlo en una variable
 * ($solicitudesPendientes), y solo el Tablero lo hacia: en las otras
 * 39 pantallas la campanita nunca se encendia aunque hubiera pagos
 * esperando. Ahora lo calcula el propio menu, una vez por pedido.
 *
 * Admin y supervisor pueden confirmar pagos y aprobar altas, asi que
 * los dos ven los mismos numeros.
 *
 * @return array{pagos:int, altas:int}
 */
function pendientesPanel(): array
{
    static $pendientes = null;

    if ($pendientes === null) {
        $db = getPDO();
        $pendientes = [
            'pagos' => \Polla\Services\SolicitudService::crearDesde($db)->contarPendientes(),
            'altas' => \Polla\Services\ClienteRegistroService::crearDesde($db)->contarPendientes(),
        ];
    }

    return $pendientes;
}
