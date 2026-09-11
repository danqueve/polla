<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Autorregistro publico de clientes y cola de aprobacion.
 *
 * La fila en `clientes` la sigue creando ClienteService (mismo
 * nro_cliente al azar, mismo reintento ante colision, misma validacion
 * de DNI/nombre/telefono, misma clave = DNI); esta clase se ocupa de lo
 * que es propio del autorregistro: el circuito pendiente ->
 * aprobado/rechazado.
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
     * admin o supervisor la apruebe. La clave es el DNI, igual que en
     * el alta manual.
     *
     * $referidoPor [Fase 11]: ver ClienteService::crearAutorregistro().
     *
     * @param array{dni:string,nombre:string,telefono?:string} $datos
     * @param array{tipo:string,id:int}|null $referidoPor
     * @return int Id del cliente nuevo.
     * @throws ValidacionException
     */
    public function registrar(array $datos, ?array $referidoPor = null): int
    {
        return $this->clientes->crearAutorregistro($datos, $referidoPor);
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
