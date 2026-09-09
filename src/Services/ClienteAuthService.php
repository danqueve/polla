<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Login del portal del cliente.
 *
 * Nada que ver con AuthService, que es el del panel: otra tabla, otra
 * sesion y otro conjunto de permisos. Que sean dos clases separadas es
 * a proposito; no hay una jerarquia comun por la que un cliente pueda
 * terminar pasando por una guarda del panel.
 *
 * La clave es siempre el DNI vigente del cliente: no hay clave propia
 * ni pantalla de cambio. Login es la unica operacion de esta clase.
 */
class ClienteAuthService
{
    /** Hash senuelo para que el login tarde lo mismo con un DNI inexistente. */
    private const HASH_SENUELO = '$2y$10$eLaF/qM6H2MQXg/7vOD61..P0t.FfMWM/zmaw3FYFLJePAJ2ejNxa';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Valida DNI + clave (que es el mismo DNI) y deja abierta la sesion
     * del portal.
     *
     * @throws ValidacionException
     */
    public function login(string $dni, string $password): array
    {
        $dni = ClienteService::normalizarDni($dni);

        if ($dni === '' || $password === '') {
            throw ValidacionException::de('Completá tu DNI y tu contraseña.');
        }

        $stmt = $this->db->prepare(
            'SELECT id, nro_cliente, dni, nombre, password_hash, activo
               FROM clientes
              WHERE dni = :dni
              LIMIT 1'
        );
        $stmt->execute([':dni' => $dni]);
        $cliente = $stmt->fetch();

        // Mismo mensaje para DNI inexistente y clave incorrecta.
        $generico = 'El DNI o la contraseña no son correctos.';

        if (!$cliente) {
            password_verify($password, self::HASH_SENUELO);
            throw ValidacionException::de($generico);
        }

        if (!password_verify($password, $cliente['password_hash'])) {
            throw ValidacionException::de($generico);
        }

        if ((int) $cliente['activo'] !== 1) {
            throw ValidacionException::de('Tu acceso esta dado de baja. Hablá con Decena de Oro.');
        }

        // El hash siempre corresponde al DNI: si cambio el costo por
        // defecto de bcrypt desde el alta, se rehashea con ese mismo DNI.
        if (password_needs_rehash($cliente['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE clientes SET password_hash = :h WHERE id = :id')
                     ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $cliente['id']]);
        }

        $this->db->prepare('UPDATE clientes SET ultimo_acceso = NOW() WHERE id = :id')
                 ->execute([':id' => $cliente['id']]);

        $this->abrirSesion($cliente);

        return $cliente;
    }

    private function abrirSesion(array $cliente): void
    {
        // Evita fijacion de sesion. El guard es por si algo ya escribio
        // salida (CLI, un warning suelto): regenerar ahi solo tira un aviso.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['cliente_id']     = (int) $cliente['id'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'];
        $_SESSION['cliente_nro']    = $cliente['nro_cliente'];
    }
}
