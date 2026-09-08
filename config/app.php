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
