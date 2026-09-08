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
 */
class ClienteAuthService
{
    /**
     * Minimo mas corto que el del panel (8). La clave del cliente protege
     * datos de solo lectura sobre sus propias jugadas, y quien la usa es
     * gente tipeando en un celular una vez por semana.
     */
    private const PASSWORD_MIN = 6;

    /** Hash senuelo para que el login tarde lo mismo con un DNI inexistente. */
    private const HASH_SENUELO = '$2y$10$eLaF/qM6H2MQXg/7vOD61..P0t.FfMWM/zmaw3FYFLJePAJ2ejNxa';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Valida DNI + clave y deja abierta la sesion del portal.
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
            'SELECT id, nro_cliente, dni, nombre, password_hash, debe_cambiar_clave, activo
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
            throw ValidacionException::de('Tu acceso esta dado de baja. Hablá con Los Quevedo.');
        }

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

    /**
     * Cambio de clave del cliente logueado.
     *
     * En el cambio obligatorio del primer ingreso no se pide la clave
     * actual: el cliente acaba de escribirla en el login para llegar
     * hasta aca, y volver a pedirle el DNI como "clave actual" es
     * justo lo que confunde a quien no es tecnico. En un cambio
     * voluntario posterior si se pide.
     *
     * @throws ValidacionException
     */
    public function cambiarPassword(
        int $clienteId,
        string $nueva,
        string $repetida,
        ?string $actual = null
    ): void {
        $stmt = $this->db->prepare(
            'SELECT dni, password_hash FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            throw ValidacionException::de('No encontramos tu cuenta.');
        }

        if ($actual !== null && !password_verify($actual, $cliente['password_hash'])) {
            throw ValidacionException::de('La contraseña actual no es correcta.');
        }

        $this->validarPassword($nueva, $repetida, $cliente['dni']);

        $this->db->prepare(
            'UPDATE clientes
                SET password_hash = :h, debe_cambiar_clave = 0
              WHERE id = :id'
        )->execute([':h' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $clienteId]);
    }

    /**
     * @param string $dni Para impedir que la clave nueva sea otra vez el DNI,
     *                    que dejaria el cambio obligatorio sin ningun efecto.
     * @throws ValidacionException
     */
    private function validarPassword(string $nueva, string $repetida, string $dni): void
    {
        $errores = [];

        if (mb_strlen($nueva) < self::PASSWORD_MIN) {
            $errores[] = 'La contraseña tiene que tener al menos ' . self::PASSWORD_MIN . ' caracteres.';
        }
        if ($nueva !== $repetida) {
            $errores[] = 'Las dos contraseñas no coinciden.';
        }
        if ($nueva === $dni) {
            $errores[] = 'La contraseña nueva no puede ser tu DNI. Elegí otra.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }
}
