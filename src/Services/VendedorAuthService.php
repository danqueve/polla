<?php

namespace Polla\Services;

use PDO;
use Polla\Support\CuentaInactivaException;
use Polla\Support\ValidacionException;

/**
 * Login del panel del vendedor [Fase 11].
 *
 * Tercera tabla de credenciales del sistema, ademas de usuarios (panel
 * admin/supervisor) y clientes (portal). Un vendedor no juega ni carga
 * jugadas: solo capta referidos. Clave propia (no es el DNI como en
 * clientes), igual mecanismo que AuthService/ClienteAuthService.
 */
class VendedorAuthService
{
    /** Hash senuelo para que el login tarde lo mismo con un usuario inexistente. */
    private const HASH_SENUELO = '$2y$10$eLaF/qM6H2MQXg/7vOD61..P0t.FfMWM/zmaw3FYFLJePAJ2ejNxa';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Igual que login(), pero sin tocar la sesion: la usa el login
     * unificado para probar esta tabla despues de usuarios y clientes,
     * antes de decidir que sesion abrir.
     *
     * @throws ValidacionException
     * @throws CuentaInactivaException
     */
    public function verificar(string $identificador, string $password): array
    {
        $identificador = trim($identificador);

        if ($identificador === '' || $password === '') {
            throw ValidacionException::de('Completá tu usuario y tu contraseña.');
        }

        // El vendedor entra con su DNI, igual criterio de "identificador
        // numerico" que ya usa el cliente -- mas facil de recordar que
        // un nombre de usuario aparte.
        $dni = ClienteService::normalizarDni($identificador);

        $stmt = $this->db->prepare(
            'SELECT id, nombre, dni, password_hash, codigo_referido, activo
               FROM vendedores
              WHERE dni = :dni
              LIMIT 1'
        );
        $stmt->execute([':dni' => $dni]);
        $vendedor = $stmt->fetch();

        $generico = 'Usuario/DNI o contraseña incorrectos.';

        if (!$vendedor) {
            password_verify($password, self::HASH_SENUELO);
            throw ValidacionException::de($generico);
        }

        if (!password_verify($password, $vendedor['password_hash'])) {
            throw ValidacionException::de($generico);
        }

        if ((int) $vendedor['activo'] !== 1) {
            throw new CuentaInactivaException([
                'Tu acceso de vendedor esta dado de baja. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.',
            ]);
        }

        if (password_needs_rehash($vendedor['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->prepare('UPDATE vendedores SET password_hash = :h WHERE id = :id')
                     ->execute([':h' => password_hash($password, PASSWORD_DEFAULT), ':id' => $vendedor['id']]);
        }

        return $vendedor;
    }

    public function abrirSesion(array $vendedor): void
    {
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['vendedor_id']              = (int) $vendedor['id'];
        $_SESSION['vendedor_nombre']           = $vendedor['nombre'];
        $_SESSION['vendedor_codigo_referido']  = $vendedor['codigo_referido'];
    }
}
