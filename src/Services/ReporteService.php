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
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $where[] = 'cy.tipo = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM sorteos s JOIN ciclos cy ON cy.id = s.ciclo_id WHERE ' . implode(' AND ', $where)
        );
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

    // ── Vista "todo el staff" para el supervisor ─────────────
    //
    // Un supervisor tiene alcance propio() en el resto de la clase, pero
    // necesita comparar su recaudacion contra el total y contra sus
    // compañeros. Estos dos metodos ignoran el alcance a proposito -no
    // porque el alcance este mal, sino porque su pregunta no es "cuanto
    // cargue yo" sino "como viene el total y quien va cargando cuanto"-,
    // mismo criterio que auditoria() (que en cambio rechaza al no-admin)
    // y usuariosParaFiltro() (que devuelve vacio): la excepcion al
    // alcance queda documentada aca, no colada en la llamada.

    /**
     * Total recaudado por todo el staff, sin importar quien cargo cada
     * jugada. A diferencia de resumen(), ignora el alcance a proposito:
     * un supervisor necesita este numero para compararlo contra lo que
     * el mismo cargo, aunque el resto de sus consultas sigan acotadas.
     */
    public function recaudadoGlobal(FiltroReporte $filtro): array
    {
        $where  = ["j.estado <> 'anulada'"];
        $params = [];
        if ($filtro->desde)   { $where[] = 'DATE(j.fecha_carga) >= :desde'; $params[':desde'] = $filtro->desde; }
        if ($filtro->hasta)   { $where[] = 'DATE(j.fecha_carga) <= :hasta'; $params[':hasta'] = $filtro->hasta; }
        if ($filtro->cicloId) { $where[] = 'j.ciclo_id = :ciclo';           $params[':ciclo'] = $filtro->cicloId; }
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $where[] = 'j.tipo_juego = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }

        $sql = "SELECT COUNT(*) AS jugadas, COALESCE(SUM(j.importe),0) AS recaudado
                  FROM jugadas j WHERE " . implode(' AND ', $where);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() ?: ['jugadas' => 0, 'recaudado' => 0];
    }

    /**
     * Una fila por usuario del staff con lo que cargo, sin acotar por
     * alcance: el objetivo es precisamente comparar entre todos.
     *
     * LEFT JOIN para que un usuario sin cargas todavia aparezca en 0 en
     * vez de desaparecer de la lista. Los filtros de $filtro van en el
     * ON, no en un WHERE: puestos en WHERE, el LEFT JOIN se comporta
     * como INNER JOIN y los usuarios en 0 se pierden justo cuando hay
     * un filtro aplicado, que es cuando mas interesa verlos.
     *
     * No se filtra por u.activo = 1: si alguien dado de baja tiene
     * jugadas viejas dentro del filtro, tienen que seguir contando aca
     * para que la suma de esta tabla cuadre con recaudadoGlobal().
     */
    public function recaudacionPorUsuario(FiltroReporte $filtro): array
    {
        $on     = ['j.cargado_por = u.id', "j.estado <> 'anulada'"];
        $params = [];
        if ($filtro->desde)   { $on[] = 'DATE(j.fecha_carga) >= :desde'; $params[':desde'] = $filtro->desde; }
        if ($filtro->hasta)   { $on[] = 'DATE(j.fecha_carga) <= :hasta'; $params[':hasta'] = $filtro->hasta; }
        if ($filtro->cicloId) { $on[] = 'j.ciclo_id = :ciclo';           $params[':ciclo'] = $filtro->cicloId; }
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $on[] = 'j.tipo_juego = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }

        $sql = "SELECT u.id, u.nombre, u.rol, u.activo,
                       COUNT(j.id) AS jugadas, COALESCE(SUM(j.importe),0) AS recaudado
                  FROM usuarios u
                  LEFT JOIN jugadas j ON " . implode(' AND ', $on) . "
                 GROUP BY u.id, u.nombre, u.rol, u.activo
                 ORDER BY u.activo DESC, recaudado DESC, u.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
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
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $where[] = 'j.tipo_juego = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }

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
     * La rama de jugadas filtra estado_pago = 'confirmada' [Fase 6]:
     * una jugada que un cliente armo desde el portal y todavia esta
     * pendiente de pago (o fue rechazada) no tiene cargado_por -nadie
     * del staff la tipeo- y apareceria acá como "usuario borrado" en
     * vez de reflejar lo que realmente paso.
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
        // El tipo de juego solo aplica a jugadas y sorteos -las altas de
        // cliente no son de ningun juego en particular, asi que esa rama
        // se muestra siempre entera sin importar el filtro.
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $rangoJ .= ' AND j.tipo_juego = :tipoj1';
            $rangoS .= ' AND cy.tipo = :tipoj2';
            $params += [':tipoj1' => $filtro->tipoJuego, ':tipoj2' => $filtro->tipoJuego];
        }

        $sql = "
            SELECT 'jugada' AS tipo, j.id AS referencia, j.fecha_carga AS cuando,
                   u.nombre AS quien, u.rol,
                   CONCAT('Jugada de ', c.nombre, ' por ', FORMAT(j.importe, 0)) AS detalle
              FROM jugadas j
              JOIN clientes c      ON c.id = j.cliente_id
              LEFT JOIN usuarios u ON u.id = j.cargado_por
             WHERE j.estado_pago = 'confirmada' $rangoJ

            UNION ALL

            SELECT 'sorteo', s.id, s.creado_en,
                   u.nombre, u.rol,
                   CONCAT('Extracto del ', DATE_FORMAT(s.fecha, '%d/%m/%Y'))
              FROM sorteos s
              JOIN ciclos cy        ON cy.id = s.ciclo_id
              LEFT JOIN usuarios u  ON u.id  = s.cargado_por
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

    /**
     * Ciclos para el desplegable, de un tipo de juego dado (o de todos si
     * $tipoJuego es null). `numero` se repite entre semanal y sabado, asi
     * que mezclarlos sin filtro mostraria "Ciclo 5" dos veces.
     */
    public function ciclosParaFiltro(?string $tipoJuego = null, int $limite = 52): array
    {
        if ($tipoJuego === null) {
            return $this->db->query(
                'SELECT id, numero, fecha_inicio, fecha_fin, estado, tipo
                   FROM ciclos ORDER BY numero DESC LIMIT ' . (int) $limite
            )->fetchAll();
        }

        $stmt = $this->db->prepare(
            'SELECT id, numero, fecha_inicio, fecha_fin, estado, tipo
               FROM ciclos WHERE tipo = :tipo ORDER BY numero DESC LIMIT ' . (int) $limite
        );
        $stmt->execute([':tipo' => $tipoJuego]);

        return $stmt->fetchAll();
    }

    // ── Estadísticas de números ─────────────────────────────

    /**
     * Frecuencia, ranking calientes/frios y atraso de cada numero
     * (00-99) dentro del periodo filtrado. Se recalcula en cada llamada
     * -sin cache-: el volumen de sorteos/sorteo_numeros es chico incluso
     * con años de historial, asi que un GROUP BY simple alcanza.
     *
     * Sin acotar por alcance ni por $filtro->clienteId/usuarioId a
     * proposito: los numeros que salieron en un sorteo son un dato del
     * sorteo, no de quien tipeo el extracto ni de que cliente jugo. Por
     * eso esta pantalla se instancia siempre con AlcanceReporte::total()
     * (ver admin/reportes/numeros.php), para que la vea el supervisor
     * igual que el admin.
     *
     * @return array{
     *     total_sorteos:int,
     *     numeros:array<int,array{numero:int,apariciones:int,porcentaje:float,atraso:?int}>,
     *     calientes:array<int,array>, frios:array<int,array>,
     *     atrasados:array<int,array>, nunca:int[]
     * }
     */
    public function estadisticasNumeros(FiltroReporte $filtro): array
    {
        $where  = ['1 = 1'];
        $params = [];
        if ($filtro->desde) { $where[] = 's.fecha >= :desde'; $params[':desde'] = $filtro->desde; }
        if ($filtro->hasta) { $where[] = 's.fecha <= :hasta'; $params[':hasta'] = $filtro->hasta; }
        if ($filtro->cicloId) { $where[] = 's.ciclo_id = :ciclo'; $params[':ciclo'] = $filtro->cicloId; }
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $where[] = 'cy.tipo = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }
        $condiciones = implode(' AND ', $where);

        // 1) Sorteos del periodo en orden cronologico real (fecha, turno)
        //    -mismo criterio que SorteoService::listarPorCiclo()/
        //    recotejarCiclo(): en sabado los 5 turnos comparten fecha, en
        //    semanal el turno es siempre 1- para poder calcular el
        //    "atraso" de cada numero como posicion dentro de la secuencia.
        $stmt = $this->db->prepare(
            "SELECT s.id FROM sorteos s JOIN ciclos cy ON cy.id = s.ciclo_id
              WHERE $condiciones ORDER BY s.fecha ASC, s.turno ASC"
        );
        $stmt->execute($params);
        $sorteoIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $totalSorteos = count($sorteoIds);
        if ($totalSorteos === 0) {
            return [
                'total_sorteos' => 0, 'numeros' => [], 'calientes' => [],
                'frios' => [], 'atrasados' => [], 'nunca' => range(0, 99),
            ];
        }

        $indice = [];
        foreach ($sorteoIds as $pos => $id) {
            $indice[$id] = $pos + 1;
        }

        // 2) Pares (numero, sorteo_id) DISTINCT: el extracto puede repetir
        //    un numero en dos posiciones del MISMO sorteo (a diferencia de
        //    una jugada), y eso no debe contar como una aparicion extra.
        $stmt = $this->db->prepare(
            "SELECT DISTINCT sn.numero, sn.sorteo_id
               FROM sorteo_numeros sn
               JOIN sorteos s ON s.id = sn.sorteo_id
               JOIN ciclos cy ON cy.id = s.ciclo_id
              WHERE $condiciones"
        );
        $stmt->execute($params);

        $apariciones  = array_fill(0, 100, 0);
        $ultimoIndice = array_fill(0, 100, 0); // 0 = no aparecio nunca en el periodo
        foreach ($stmt->fetchAll() as $fila) {
            $n   = (int) $fila['numero'];
            $pos = $indice[(int) $fila['sorteo_id']] ?? 0;
            $apariciones[$n]++;
            if ($pos > $ultimoIndice[$n]) {
                $ultimoIndice[$n] = $pos;
            }
        }

        $numeros = [];
        $nunca   = [];
        for ($n = 0; $n <= 99; $n++) {
            $atraso = $ultimoIndice[$n] > 0 ? $totalSorteos - $ultimoIndice[$n] : null;
            $numeros[$n] = [
                'numero'      => $n,
                'apariciones' => $apariciones[$n],
                'porcentaje'  => $apariciones[$n] / $totalSorteos * 100,
                'atraso'      => $atraso, // null = nunca broto en este periodo
            ];
            if ($atraso === null) {
                $nunca[] = $n;
            }
        }

        $porApariciones = static fn(array $a, array $b): int =>
            $a['apariciones'] <=> $b['apariciones'] ?: $b['numero'] <=> $a['numero'];

        $calientes = $numeros;
        usort($calientes, static fn($a, $b) => -$porApariciones($a, $b));
        $calientes = array_slice($calientes, 0, 10);

        $frios = $numeros;
        usort($frios, $porApariciones);
        $frios = array_slice($frios, 0, 10);

        // Atrasados: solo entre los que SI aparecieron alguna vez en el
        // periodo -con pocos sorteos cargados la mayoria de los 100
        // numeros puede no haber salido nunca todavia, y mezclarlos aca
        // (todos "empatados" al maximo posible) no aporta nada; van
        // aparte en 'nunca'.
        $atrasados = array_values(array_filter($numeros, static fn($f) => $f['atraso'] !== null));
        usort($atrasados, static fn($a, $b) => $b['atraso'] <=> $a['atraso'] ?: $a['numero'] <=> $b['numero']);
        $atrasados = array_slice($atrasados, 0, 10);

        return [
            'total_sorteos' => $totalSorteos,
            'numeros'       => $numeros,
            'calientes'     => $calientes,
            'frios'         => $frios,
            'atrasados'     => $atrasados,
            'nunca'         => $nunca,
        ];
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
        if ($filtro->tipoJuego !== FiltroReporte::TIPO_JUEGO_TODOS) {
            $where[] = 'j.tipo_juego = :tipo_juego';
            $params[':tipo_juego'] = $filtro->tipoJuego;
        }

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
