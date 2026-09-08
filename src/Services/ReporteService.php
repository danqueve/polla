<?php

namespace Polla\Services;

use PDO;
use Polla\Support\AlcanceReporte;
use Polla\Support\FiltroReporte;
use Polla\Support\ValidacionException;

/**
 * Reportes de administracion.
 *
 * El alcance entra por el constructor y no se puede cambiar despues, asi
 * que ninguna consulta de esta clase se ejecuta sin el. Para un
 * supervisor, cada metodo agrega su propio filtro de autoria segun la
 * tabla que consulta:
 *
 *   jugadas   -> j.cargado_por
 *   sorteos   -> s.cargado_por
 *   clientes  -> c.alta_por
 *   ganadores -> j.cargado_por de la jugada premiada
 *
 * Los totales del negocio (recaudacion global, reparto 60/40 acumulado)
 * directamente no se calculan cuando el alcance no es de admin: no es
 * que se oculten en la vista, es que la consulta no se corre.
 */
class ReporteService
{
    private PDO $db;
    private AlcanceReporte $alcance;

    public function __construct(PDO $db, AlcanceReporte $alcance)
    {
        $this->db      = $db;
        $this->alcance = $alcance;
    }

    public function alcance(): AlcanceReporte
    {
        return $this->alcance;
    }

    // ── Resumen de tarjetas ─────────────────────────────────

    /**
     * Totales del periodo filtrado, dentro del alcance.
     *
     * Para el admin son los del negocio; para un supervisor, los de sus
     * propias cargas, que es su trabajo y no informacion ajena.
     */
    public function resumen(FiltroReporte $filtro): array
    {
        [$where, $params] = $this->condicionesJugadas($filtro);

        $sql = "SELECT COUNT(*)                        AS jugadas,
                       COUNT(DISTINCT j.cliente_id)    AS clientes,
                       COUNT(DISTINCT j.ciclo_id)      AS ciclos,
                       COALESCE(SUM(j.importe), 0)     AS recaudado,
                       COALESCE(SUM(j.aporte_pozo), 0) AS al_pozo,
                       COALESCE(SUM(j.aporte_gastos),0) AS a_gastos
                  FROM jugadas j
                  JOIN clientes c ON c.id = j.cliente_id
                 WHERE " . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: [
            'jugadas' => 0, 'clientes' => 0, 'ciclos' => 0,
            'recaudado' => 0, 'al_pozo' => 0, 'a_gastos' => 0,
        ];
    }

    /** Cuantos sorteos cargo (el negocio, o el supervisor). */
    public function totalSorteos(FiltroReporte $filtro): int
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($cond = $this->alcance->condicion('s.cargado_por')) {
            $where[] = $cond;
            $params += $this->alcance->parametros();
        }
        if ($filtro->desde) { $where[] = 's.fecha >= :desde'; $params[':desde'] = $filtro->desde; }
        if ($filtro->hasta) { $where[] = 's.fecha <= :hasta'; $params[':hasta'] = $filtro->hasta; }
        if ($filtro->cicloId)   { $where[] = 's.ciclo_id = :ciclo';      $params[':ciclo'] = $filtro->cicloId; }
        if ($filtro->usuarioId) { $where[] = 's.cargado_por = :usuario'; $params[':usuario'] = $filtro->usuarioId; }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sorteos s WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Cuantos clientes dio de alta (el negocio, o el supervisor). */
    public function totalClientes(FiltroReporte $filtro): int
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($cond = $this->alcance->condicion('c.alta_por')) {
            $where[] = $cond;
            $params += $this->alcance->parametros();
        }
        if ($filtro->desde) { $where[] = 'DATE(c.fecha_alta) >= :desde'; $params[':desde'] = $filtro->desde; }
        if ($filtro->hasta) { $where[] = 'DATE(c.fecha_alta) <= :hasta'; $params[':hasta'] = $filtro->hasta; }
        if ($filtro->usuarioId) { $where[] = 'c.alta_por = :usuario'; $params[':usuario'] = $filtro->usuarioId; }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM clientes c WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // ── Recaudacion por ciclo ───────────────────────────────

    /**
     * Una fila por ciclo con su recaudacion, para las barras del tablero.
     *
     * El estado y el pozo del ciclo son datos del ciclo, no de nadie en
     * particular; lo que se acota por alcance es la recaudacion, que sale
     * de las jugadas.
     *
     * @return array<int,array>
     */
    public function porCiclo(FiltroReporte $filtro, int $limite = 20): array
    {
        [$where, $params] = $this->condicionesJugadas($filtro);

        $sql = "SELECT cl.id, cl.numero, cl.fecha_inicio, cl.fecha_fin, cl.estado,
                       p.monto_arrastrado, p.monto_acumulado, p.monto_pagado,
                       COUNT(j.id)                      AS jugadas,
                       COALESCE(SUM(j.importe), 0)      AS recaudado,
                       COALESCE(SUM(j.aporte_pozo), 0)  AS al_pozo,
                       COALESCE(SUM(j.aporte_gastos),0) AS a_gastos,
                       (SELECT COUNT(*) FROM ganadores g WHERE g.ciclo_id = cl.id) AS ganadores
                  FROM jugadas j
                  JOIN clientes c   ON c.id  = j.cliente_id
                  JOIN ciclos   cl  ON cl.id = j.ciclo_id
                  LEFT JOIN pozo_ciclo p ON p.ciclo_id = cl.id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY cl.id
                 ORDER BY cl.numero DESC
                 LIMIT " . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ── Detalle de jugadas ──────────────────────────────────

    /** @return array<int,array> */
    public function jugadas(FiltroReporte $filtro, int $limite = 500): array
    {
        [$where, $params] = $this->condicionesJugadas($filtro);

        $sql = "SELECT j.id, j.importe, j.aporte_pozo, j.aporte_gastos,
                       j.pagada, j.estado, j.fecha_carga,
                       c.nro_cliente, c.dni, c.nombre AS cliente,
                       cl.numero AS ciclo, cl.fecha_inicio, cl.fecha_fin,
                       u.nombre  AS cargado_por,
                       g.monto_premio,
                       GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
                  FROM jugadas j
                  JOIN clientes c        ON c.id  = j.cliente_id
                  JOIN ciclos   cl       ON cl.id = j.ciclo_id
                  LEFT JOIN usuarios u   ON u.id  = j.cargado_por
                  LEFT JOIN ganadores g  ON g.jugada_id = j.id
                  LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY j.id
                 ORDER BY j.fecha_carga DESC, j.id DESC
                 LIMIT " . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotar($fila['numeros']);
        }

        return $filas;
    }

    // ── Ganadores ───────────────────────────────────────────

    /**
     * Ganadores historicos. Para un supervisor, solo los que salieron de
     * jugadas que el cargo.
     *
     * @return array<int,array>
     */
    public function ganadores(FiltroReporte $filtro, int $limite = 500): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($cond = $this->alcance->condicion('j.cargado_por')) {
            $where[] = $cond;
            $params += $this->alcance->parametros();
        }
        if ($filtro->desde)     { $where[] = 's.fecha >= :desde';        $params[':desde']   = $filtro->desde; }
        if ($filtro->hasta)     { $where[] = 's.fecha <= :hasta';        $params[':hasta']   = $filtro->hasta; }
        if ($filtro->clienteId) { $where[] = 'j.cliente_id = :cliente';  $params[':cliente'] = $filtro->clienteId; }
        if ($filtro->cicloId)   { $where[] = 'g.ciclo_id = :ciclo';      $params[':ciclo']   = $filtro->cicloId; }
        if ($filtro->usuarioId) { $where[] = 'j.cargado_por = :usuario'; $params[':usuario'] = $filtro->usuarioId; }

        $sql = "SELECT g.id, g.monto_premio, g.creado_en,
                       j.id AS jugada_id,
                       c.nro_cliente, c.dni, c.nombre AS cliente, c.telefono,
                       cl.numero AS ciclo, cl.fecha_inicio, cl.fecha_fin,
                       s.fecha AS sorteo,
                       u.nombre AS cargado_por,
                       GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
                  FROM ganadores g
                  JOIN jugadas  j  ON j.id  = g.jugada_id
                  JOIN clientes c  ON c.id  = j.cliente_id
                  JOIN ciclos   cl ON cl.id = g.ciclo_id
                  JOIN sorteos  s  ON s.id  = g.sorteo_id
                  LEFT JOIN usuarios u ON u.id = j.cargado_por
                  LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY g.id
                 ORDER BY s.fecha DESC, g.id DESC
                 LIMIT " . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotar($fila['numeros']);
        }

        return $filas;
    }

    // ── Auditoria ───────────────────────────────────────────

    /**
     * Quien cargo que y cuando: jugadas, sorteos y altas de clientes en
     * una sola linea de tiempo.
     *
     * Exclusiva del admin. La guarda esta aca y no solo en la pantalla:
     * si manana alguien enlaza este metodo desde otro lado, sigue sin
     * poder ejecutarlo con un alcance acotado.
     *
     * @throws ValidacionException
     * @return array<int,array>
     */
    public function auditoria(FiltroReporte $filtro, int $limite = 300): array
    {
        if (!$this->alcance->esAdmin()) {
            throw ValidacionException::de('La auditoria es exclusiva del administrador.');
        }

        $params = [];
        $rangoJ = $rangoS = $rangoC = '';

        if ($filtro->desde) {
            $rangoJ .= ' AND DATE(j.fecha_carga) >= :desde1';
            $rangoS .= ' AND DATE(s.creado_en)   >= :desde2';
            $rangoC .= ' AND DATE(c.fecha_alta)  >= :desde3';
            $params += [':desde1' => $filtro->desde, ':desde2' => $filtro->desde, ':desde3' => $filtro->desde];
        }
        if ($filtro->hasta) {
            $rangoJ .= ' AND DATE(j.fecha_carga) <= :hasta1';
            $rangoS .= ' AND DATE(s.creado_en)   <= :hasta2';
            $rangoC .= ' AND DATE(c.fecha_alta)  <= :hasta3';
            $params += [':hasta1' => $filtro->hasta, ':hasta2' => $filtro->hasta, ':hasta3' => $filtro->hasta];
        }
        if ($filtro->usuarioId) {
            $rangoJ .= ' AND j.cargado_por = :usu1';
            $rangoS .= ' AND s.cargado_por = :usu2';
            $rangoC .= ' AND c.alta_por    = :usu3';
            $params += [':usu1' => $filtro->usuarioId, ':usu2' => $filtro->usuarioId, ':usu3' => $filtro->usuarioId];
        }

        $sql = "
            SELECT 'jugada' AS tipo, j.id AS referencia, j.fecha_carga AS cuando,
                   u.nombre AS quien, u.rol,
                   CONCAT('Jugada de ', c.nombre, ' por ', FORMAT(j.importe, 0)) AS detalle
              FROM jugadas j
              JOIN clientes c      ON c.id = j.cliente_id
              LEFT JOIN usuarios u ON u.id = j.cargado_por
             WHERE 1 = 1 $rangoJ

            UNION ALL

            SELECT 'sorteo', s.id, s.creado_en,
                   u.nombre, u.rol,
                   CONCAT('Extracto del ', DATE_FORMAT(s.fecha, '%d/%m/%Y'))
              FROM sorteos s
              LEFT JOIN usuarios u ON u.id = s.cargado_por
             WHERE 1 = 1 $rangoS

            UNION ALL

            SELECT 'cliente', c.id, c.fecha_alta,
                   u.nombre, u.rol,
                   CONCAT('Alta de ', c.nombre, ' (N ', c.nro_cliente, ')')
              FROM clientes c
              LEFT JOIN usuarios u ON u.id = c.alta_por
             WHERE 1 = 1 $rangoC

             ORDER BY cuando DESC
             LIMIT " . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // ── Opciones de los filtros ─────────────────────────────

    /**
     * Clientes para el desplegable. Un supervisor solo ve los que dio de
     * alta el mismo, asi que tampoco puede filtrar por un cliente ajeno.
     */
    public function clientesParaFiltro(): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if ($cond = $this->alcance->condicion('c.alta_por')) {
            $where[] = $cond;
            $params += $this->alcance->parametros();
        }

        $stmt = $this->db->prepare(
            'SELECT c.id, c.nro_cliente, c.nombre
               FROM clientes c
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY c.nombre ASC'
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Usuarios para el desplegable de autoria. Solo tiene sentido para el admin. */
    public function usuariosParaFiltro(): array
    {
        if (!$this->alcance->esAdmin()) {
            return [];
        }

        return $this->db->query(
            'SELECT id, nombre, usuario, rol FROM usuarios ORDER BY nombre ASC'
        )->fetchAll();
    }

    public function ciclosParaFiltro(int $limite = 52): array
    {
        return $this->db->query(
            'SELECT id, numero, fecha_inicio, fecha_fin, estado
               FROM ciclos ORDER BY numero DESC LIMIT ' . (int) $limite
        )->fetchAll();
    }

    // ── Internos ────────────────────────────────────────────

    /**
     * Condiciones y parametros comunes de todo lo que sale de `jugadas`.
     * El filtro de alcance se agrega primero y siempre.
     *
     * @return array{0: string[], 1: array<string,mixed>}
     */
    private function condicionesJugadas(FiltroReporte $filtro): array
    {
        $where  = ["j.estado <> 'anulada'"];
        $params = [];

        if ($cond = $this->alcance->condicion('j.cargado_por')) {
            $where[] = $cond;
            $params += $this->alcance->parametros();
        }
        if ($filtro->desde)     { $where[] = 'DATE(j.fecha_carga) >= :desde'; $params[':desde']   = $filtro->desde; }
        if ($filtro->hasta)     { $where[] = 'DATE(j.fecha_carga) <= :hasta'; $params[':hasta']   = $filtro->hasta; }
        if ($filtro->clienteId) { $where[] = 'j.cliente_id  = :cliente';      $params[':cliente'] = $filtro->clienteId; }
        if ($filtro->cicloId)   { $where[] = 'j.ciclo_id    = :ciclo';        $params[':ciclo']   = $filtro->cicloId; }
        if ($filtro->usuarioId) { $where[] = 'j.cargado_por = :usuario';      $params[':usuario'] = $filtro->usuarioId; }

        return [$where, $params];
    }

    private static function explotar(?string $concatenado): array
    {
        if ($concatenado === null || $concatenado === '') {
            return [];
        }
        return array_map('intval', explode(',', $concatenado));
    }
}
