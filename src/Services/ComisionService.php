<?php

namespace Polla\Services;

use PDO;

/**
 * Comisiones por referidos [Fase 11].
 *
 * Un cliente puede venir referido por un vendedor o por un supervisor
 * (clientes.referido_por_tipo/referido_por_id). Cada vez que UNA JUGADA
 * DE ESE CLIENTE se confirma y paga (nunca al registrarse: ver 17.3 de
 * la especificacion), se acredita al referidor un porcentaje global
 * (parametros.comision_jugada_porcentaje) del importe de esa jugada.
 *
 * acreditarSiCorresponde() es el unico metodo de escritura de esta
 * clase, y es el gancho que llaman JugadaService::crearVarias() y
 * SolicitudService::confirmar() en el momento exacto en que una jugada
 * pasa a confirmada -- ver esos dos archivos para el detalle de donde
 * se invoca. El resto de la clase son consultas de lectura para los
 * paneles de vendedor, supervisor y admin.
 */
class ComisionService
{
    private PDO $db;
    private ParametroService $parametros;

    public function __construct(PDO $db, ?ParametroService $parametros = null)
    {
        $this->db         = $db;
        $this->parametros = $parametros ?? new ParametroService($db);
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    // ── El gancho ────────────────────────────────────────────

    /**
     * Si el cliente tiene referidor y el porcentaje vigente es mayor a
     * cero, acredita la comision de esta jugada. Si no tiene referidor,
     * o el porcentaje esta en 0, no hace nada -- no vale la pena una
     * fila de $0 en la tabla (Fase 11, punto explicito del pedido).
     *
     * UNIQUE(jugada_id) en la tabla es la red de seguridad: si por
     * error este metodo se llamara dos veces para la misma jugada, la
     * segunda insercion choca y no duplica la comision. Se llama
     * siempre dentro de la transaccion que confirma la jugada, asi que
     * un rollback de esa transaccion tambien deshace la comision.
     */
    public function acreditarSiCorresponde(int $clienteId, int $jugadaId, float $importeJugada): void
    {
        $stmt = $this->db->prepare(
            'SELECT referido_por_tipo, referido_por_id FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        if (!$cliente || $cliente['referido_por_tipo'] === null) {
            return;
        }

        $porcentaje = $this->parametros->comisionJugadaPorcentaje();
        if ($porcentaje <= 0) {
            return;
        }

        $monto = round($importeJugada * $porcentaje / 100, 2);

        $this->db->prepare(
            'INSERT INTO comisiones (referidor_tipo, referidor_id, jugada_id, monto, porcentaje_aplicado)
             VALUES (:tipo, :id, :jugada, :monto, :porcentaje)'
        )->execute([
            ':tipo'       => $cliente['referido_por_tipo'],
            ':id'         => $cliente['referido_por_id'],
            ':jugada'     => $jugadaId,
            ':monto'      => $monto,
            ':porcentaje' => $porcentaje,
        ]);
    }

    // ── Registro con referido (registro.php) ────────────────

    /**
     * Resuelve un codigo de referido contra vendedores O supervisores
     * (usuarios.rol='supervisor'). Null si el codigo no existe o
     * pertenece a un vendedor/supervisor desactivado -- registro.php lo
     * ignora en silencio en ese caso, nunca bloquea el registro.
     *
     * @return array{tipo:string, id:int, nombre:string}|null
     */
    public function resolverCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre FROM vendedores WHERE codigo_referido = :c AND activo = 1 LIMIT 1'
        );
        $stmt->execute([':c' => $codigo]);
        if ($fila = $stmt->fetch()) {
            return ['tipo' => 'vendedor', 'id' => (int) $fila['id'], 'nombre' => $fila['nombre']];
        }

        $stmt = $this->db->prepare(
            "SELECT id, nombre FROM usuarios
              WHERE codigo_referido = :c AND rol = 'supervisor' AND activo = 1
              LIMIT 1"
        );
        $stmt->execute([':c' => $codigo]);
        if ($fila = $stmt->fetch()) {
            return ['tipo' => 'supervisor', 'id' => (int) $fila['id'], 'nombre' => $fila['nombre']];
        }

        return null;
    }

    // ── Vista propia (vendedor/index.php, admin/referidos/mios.php) ──

    /**
     * Clientes referidos por este referidor, con cuantas jugadas
     * confirmadas cargo cada uno. Del mas nuevo al mas viejo.
     *
     * @return array<int,array>
     */
    public function listarReferidos(string $tipo, int $referidorId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.nombre, c.nro_cliente, c.fecha_alta,
                    (SELECT COUNT(*) FROM jugadas j
                      WHERE j.cliente_id = c.id AND j.estado_pago = 'confirmada') AS jugadas_total
               FROM clientes c
              WHERE c.referido_por_tipo = :tipo AND c.referido_por_id = :id
              ORDER BY c.fecha_alta DESC"
        );
        $stmt->execute([':tipo' => $tipo, ':id' => $referidorId]);
        return $stmt->fetchAll();
    }

    /** Saldo pendiente = comisiones acreditadas - liquidaciones ya cobradas. */
    public function saldoPendiente(string $tipo, int $referidorId): float
    {
        $stmt = $this->db->prepare(
            'SELECT
                 (SELECT COALESCE(SUM(monto), 0) FROM comisiones
                   WHERE referidor_tipo = :tipo1 AND referidor_id = :id1)
               - (SELECT COALESCE(SUM(monto), 0) FROM liquidaciones
                   WHERE referidor_tipo = :tipo2 AND referidor_id = :id2)'
        );
        $stmt->execute([':tipo1' => $tipo, ':id1' => $referidorId, ':tipo2' => $tipo, ':id2' => $referidorId]);
        return (float) $stmt->fetchColumn();
    }

    /** Total historico acreditado (antes de descontar liquidaciones), para el detalle. */
    public function totalAcreditado(string $tipo, int $referidorId): float
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM comisiones WHERE referidor_tipo = :tipo AND referidor_id = :id'
        );
        $stmt->execute([':tipo' => $tipo, ':id' => $referidorId]);
        return (float) $stmt->fetchColumn();
    }

    // ── Vista global (admin/referidos/index.php) ────────────

    /**
     * Todos los referidores (vendedores activos + supervisores con
     * codigo), con su cantidad de referidos, jugadas generadas y saldo
     * pendiente. Une dos consultas (una por tabla de origen) porque no
     * hay una tabla comun de "referidores" -- es la misma relacion
     * polimorfica que clientes.referido_por_tipo.
     *
     * @return array<int,array>
     */
    public function listarTodosLosReferidores(): array
    {
        $vendedores = $this->db->query(
            "SELECT 'vendedor' AS tipo, v.id, v.nombre, v.codigo_referido,
                    (SELECT COUNT(*) FROM clientes c
                      WHERE c.referido_por_tipo = 'vendedor' AND c.referido_por_id = v.id) AS referidos_total,
                    (SELECT COUNT(*) FROM comisiones co
                      WHERE co.referidor_tipo = 'vendedor' AND co.referidor_id = v.id) AS jugadas_total,
                    (SELECT COALESCE(SUM(monto), 0) FROM comisiones co
                      WHERE co.referidor_tipo = 'vendedor' AND co.referidor_id = v.id) AS acreditado,
                    (SELECT COALESCE(SUM(monto), 0) FROM liquidaciones l
                      WHERE l.referidor_tipo = 'vendedor' AND l.referidor_id = v.id) AS liquidado
               FROM vendedores v
              WHERE v.activo = 1"
        )->fetchAll();

        $supervisores = $this->db->query(
            "SELECT 'supervisor' AS tipo, u.id, u.nombre, u.codigo_referido,
                    (SELECT COUNT(*) FROM clientes c
                      WHERE c.referido_por_tipo = 'supervisor' AND c.referido_por_id = u.id) AS referidos_total,
                    (SELECT COUNT(*) FROM comisiones co
                      WHERE co.referidor_tipo = 'supervisor' AND co.referidor_id = u.id) AS jugadas_total,
                    (SELECT COALESCE(SUM(monto), 0) FROM comisiones co
                      WHERE co.referidor_tipo = 'supervisor' AND co.referidor_id = u.id) AS acreditado,
                    (SELECT COALESCE(SUM(monto), 0) FROM liquidaciones l
                      WHERE l.referidor_tipo = 'supervisor' AND l.referidor_id = u.id) AS liquidado
               FROM usuarios u
              WHERE u.rol = 'supervisor' AND u.codigo_referido IS NOT NULL AND u.activo = 1"
        )->fetchAll();

        $todos = array_merge($vendedores, $supervisores);
        foreach ($todos as &$fila) {
            $fila['saldo_pendiente'] = round((float) $fila['acreditado'] - (float) $fila['liquidado'], 2);
        }
        unset($fila);

        usort($todos, static fn($a, $b) => $b['saldo_pendiente'] <=> $a['saldo_pendiente']);

        return $todos;
    }

    /** Nombre de un referidor puntual, para pantallas de detalle. */
    public function nombreDe(string $tipo, int $id): ?string
    {
        $tabla  = $tipo === 'vendedor' ? 'vendedores' : 'usuarios';
        $stmt = $this->db->prepare("SELECT nombre FROM `$tabla` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $nombre = $stmt->fetchColumn();
        return $nombre !== false ? $nombre : null;
    }
}
