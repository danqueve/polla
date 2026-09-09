<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Autorregistro publico de clientes y cola de aprobacion.
 *
 * La fila en `clientes` la sigue creando ClienteService (mismo
 * nro_cliente al azar, mismo reintento ante colision, misma validacion
 * de DNI/nombre/telefono); esta clase se ocupa de lo que es propio del
 * autorregistro: la clave que elige la persona (no el DNI, como en el
 * alta manual) y el circuito pendiente -> aprobado/rechazado.
 */
class ClienteRegistroService
{
    private PDO $db;
    private ClienteService $clientes;

    public function __construct(PDO $db, ?ClienteService $clientes = null)
    {
        $this->db       = $db;
        $this->clientes = $clientes ?? new ClienteService($db);
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Autorregistro publico. La cuenta nace `pendiente`: no puede operar
     * (ni cargarle jugadas, ni ver datos en el portal) hasta que un
     * admin o supervisor la apruebe.
     *
     * @param array{dni:string,nombre:string,telefono?:string} $datos
     * @return int Id del cliente nuevo.
     * @throws ValidacionException
     */
    public function registrar(array $datos, string $password, string $repetida): int
    {
        $dni = ClienteService::normalizarDni($datos['dni'] ?? '');
        $this->validarPassword($password, $repetida, $dni);

        $hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->clientes->crearAutorregistro($datos, $hash);
    }

    /** @throws ValidacionException */
    private function validarPassword(string $password, string $repetida, string $dni): void
    {
        $errores = [];
        $minimo  = ClienteAuthService::PASSWORD_MIN;

        if (mb_strlen($password) < $minimo) {
            $errores[] = 'La contraseña tiene que tener al menos ' . $minimo . ' caracteres.';
        }
        if ($password !== $repetida) {
            $errores[] = 'Las dos contraseñas no coinciden.';
        }
        if ($dni !== '' && $password === $dni) {
            $errores[] = 'La contraseña no puede ser tu DNI. Elegí otra.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }

    // ── Cola de aprobacion ──────────────────────────────────

    /**
     * Solicitudes esperando revision, de la mas vieja a la mas nueva
     * (asi no se pierde la primera que llego al final de una lista larga).
     *
     * @return array<int,array>
     */
    public function listarPendientes(): array
    {
        return $this->db->query(
            "SELECT id, nro_cliente, dni, nombre, telefono, fecha_alta
               FROM clientes
              WHERE estado = 'pendiente'
              ORDER BY fecha_alta ASC"
        )->fetchAll();
    }

    public function contarPendientes(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM clientes WHERE estado = 'pendiente'"
        )->fetchColumn();
    }

    /**
     * Aprueba la solicitud: el cliente ya puede operar con normalidad.
     * Admin y supervisor pueden aprobar por igual.
     *
     * @throws ValidacionException
     */
    public function aprobar(int $id): void
    {
        $this->buscarPendiente($id);

        $this->db->prepare("UPDATE clientes SET estado = 'aprobado' WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    /**
     * Rechaza la solicitud. Queda la fila en `clientes` con
     * estado='rechazado' (no se borra) para que quede rastro y el DNI
     * siga bloqueado contra un nuevo intento sin que un admin lo libere
     * a mano. Exclusivo del administrador: lo exige la pantalla que
     * llama a este metodo, con requireAdmin().
     *
     * @throws ValidacionException
     */
    public function rechazar(int $id): void
    {
        $this->buscarPendiente($id);

        $this->db->prepare("UPDATE clientes SET estado = 'rechazado' WHERE id = :id")
                  ->execute([':id' => $id]);
    }

    /** @throws ValidacionException */
    private function buscarPendiente(int $id): void
    {
        $stmt = $this->db->prepare('SELECT estado FROM clientes WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $estado = $stmt->fetchColumn();

        if ($estado === false) {
            throw ValidacionException::de('La solicitud no existe.');
        }
        if ($estado !== 'pendiente') {
            throw ValidacionException::de('Esa solicitud ya fue resuelta.');
        }
    }
}
