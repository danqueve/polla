<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Login del panel administrativo (admin | supervisor).
 *
 * El login de clientes vive aparte, en ClienteService, porque son dos
 * mundos distintos: distinta tabla, distinta sesion y distinto portal.
 */
class AuthService
{
    private PDO $db;

    /**
     * Hash bcrypt de una cadena aleatoria descartada. Se verifica contra el
     * cuando el usuario no existe para que el login tarde lo mismo que uno
     * real y no se pueda deducir por tiempo que usuarios estan dados de alta.
     */
    private const HASH_SENUELO = '$2y$10$eLaF/qM6H2MQXg/7vOD61..P0t.FfMWM/zmaw3FYFLJePAJ2ejNxa';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Valida credenciales y deja la sesion abierta.
     *
     * @throws ValidacionException si el usuario no existe, esta inactivo
     *                             o la clave no coincide.
     */
    public function login(string $usuario, string $password): array
    {
        $usuario = trim($usuario);

        if ($usuario === '' || $password === '') {
            throw ValidacionException::de('Completa usuario y contrasena.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, usuario, nombre, password_hash, rol, activo
               FROM usuarios
              WHERE usuario = :usuario
              LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        $fila = $stmt->fetch();

        // Mismo mensaje para usuario inexistente y clave incorrecta: no le
        // regalamos al que prueba credenciales la pista de cuales existen.
        $generico = 'Usuario o contrasena incorrectos.';

        if (!$fila) {
            password_verify($password, self::HASH_SENUELO);
            throw ValidacionException::de($generico);
        }

        if (!password_verify($password, $fila['password_hash'])) {
            throw ValidacionException::de($generico);
        }

        if ((int) $fila['activo'] !== 1) {
            throw ValidacionException::de('Tu usuario esta desactivado. Hablalo con el administrador.');
        }

        // Rehash si el costo por defecto de PHP cambio desde el alta.
        if (password_needs_rehash($fila['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
                     ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $fila['id']]);
        }

        $this->db->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id')
                 ->execute([':id' => $fila['id']]);

        $this->abrirSesion($fila);

        return $fila;
    }

    private function abrirSesion(array $usuario): void
    {
        // Evita fijacion de sesion: el id previo al login se descarta.
        // El guard es por si algo ya escribio salida (CLI, un warning suelto):
        // regenerar ahi solo tira un warning y no aporta nada.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id']      = (int) $usuario['id'];
        $_SESSION['user_usuario'] = $usuario['usuario'];
        $_SESSION['user_nombre']  = $usuario['nombre'];
        $_SESSION['user_rol']     = $usuario['rol'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /**
     * Cambio de clave del propio usuario logueado.
     *
     * @throws ValidacionException
     */
    public function cambiarPassword(int $usuarioId, string $actual, string $nueva, string $repetida): void
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $usuarioId]);
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($actual, $hash)) {
            throw ValidacionException::de('La contrasena actual no es correcta.');
        }

        UsuarioService::validarPassword($nueva, $repetida);

        $this->db->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
                 ->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $usuarioId]);
    }
}
