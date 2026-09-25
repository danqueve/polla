<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;
use Throwable;

/**
 * Carga y consulta de jugadas.
 *
 * Una jugada son 10 numeros distintos entre 00 y 99 por el monto
 * vigente. Al confirmarla, el 60% se suma al pozo del ciclo abierto y
 * el 40% queda como gastos/ganancias. Todo eso ocurre en una sola
 * transaccion: o se guardan la jugada, sus 10 numeros y el aporte al
 * pozo, o no se guarda nada.
 *
 * crearVarias() es el camino de siempre: el staff carga, cobra y
 * confirma en el mismo paso. crearPendientes() es el camino de la
 * Fase 6: el cliente arma la jugada desde el portal pero queda sin
 * ciclo y sin sumar al pozo hasta que el staff confirma el pago via
 * SolicitudService.
 */
class JugadaService
{
    private PDO $db;
    private ParametroService $parametros;
    private CicloService $ciclos;
    private PozoService $pozo;
    private HorarioCargaService $horario;
    private ComisionService $comisiones;

    public function __construct(
        PDO $db,
        ParametroService $parametros,
        CicloService $ciclos,
        PozoService $pozo,
        HorarioCargaService $horario,
        ComisionService $comisiones
    ) {
        $this->db         = $db;
        $this->parametros = $parametros;
        $this->ciclos     = $ciclos;
        $this->pozo       = $pozo;
        $this->horario    = $horario;
        $this->comisiones = $comisiones;
    }

    /** Fabrica: arma el service con sus dependencias ya cableadas. */
    public static function crearDesde(PDO $db): self
    {
        $parametros = new ParametroService($db);
        return new self(
            $db,
            $parametros,
            new CicloService($db),
            new PozoService($db),
            new HorarioCargaService($parametros),
            new ComisionService($db, $parametros)
        );
    }

    /**
     * Registra una jugada pagada en el ciclo abierto.
     *
     * @param string[] $numerosCrudos Los 10 valores tal como vinieron del form.
     * @return int Id de la jugada nueva.
     * @throws ValidacionException
     */
    public function crear(
        int $clienteId,
        array $numerosCrudos,
        ?int $cargadoPor,
        string $tipoJuego = CicloService::TIPO_SEMANAL
    ): int {
        return $this->crearVarias($clienteId, [$numerosCrudos], $cargadoPor, null, $tipoJuego)[0];
    }

    /**
     * Registra varias jugadas para el mismo cliente en una sola operacion
     * (compra conjunta): todas al ciclo activo y todas marcadas con el
     * mismo grupo_compra para trazabilidad en reportes (no afecta el
     * cotejo ni el calculo del pozo, que siguen siendo por jugada
     * individual). El importe de cada una es el monto vigente, o -si se
     * aplica una promocion (Fase 7)- el precio del paquete dividido la
     * cantidad (ver resolverImportes()).
     *
     * Valida los N sets completos antes de grabar cualquiera: una jugada
     * mal cargada no debe dejar a las otras ya cobradas.
     *
     * @param array<int,string[]> $listasDeNumeros Un set de numeros crudos por jugada.
     * @param int|null $promocionId Promocion activa a aplicar, o null para
     *                              cobrar precio de lista (el de siempre).
     *                              Exclusiva del juego semanal.
     * @return int[] Ids de las jugadas creadas, en el mismo orden que $listasDeNumeros.
     * @throws ValidacionException
     */
    public function crearVarias(
        int $clienteId,
        array $listasDeNumeros,
        ?int $cargadoPor,
        ?int $promocionId = null,
        string $tipoJuego = CicloService::TIPO_SEMANAL
    ): array {
        if (!$listasDeNumeros) {
            throw ValidacionException::de('Cargá al menos una jugada.');
        }

        $this->horario->exigirAbierto($tipoJuego);

        $cliente = $this->buscarClienteActivo($clienteId);

        $numerosPorJugada = [];
        $errores          = [];
        foreach (array_values($listasDeNumeros) as $i => $crudos) {
            try {
                $numerosPorJugada[] = $this->validarNumeros($crudos, $tipoJuego);
            } catch (ValidacionException $e) {
                $prefijo = count($listasDeNumeros) > 1 ? 'Jugada ' . ($i + 1) . ': ' : '';
                foreach ($e->errores() as $error) {
                    $errores[] = $prefijo . $error;
                }
            }
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        $ciclo    = $this->ciclos->obtenerCicloParaCarga($tipoJuego);
        $importes = $this->resolverImportes(count($numerosPorJugada), $promocionId, $tipoJuego);
        $grupo    = self::uuid4();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO jugadas (cliente_id, tipo_juego, ciclo_id, importe, aporte_pozo, aporte_gastos,
                                      pagada, estado_pago, origen_carga, grupo_compra, promocion_id,
                                      estado, cargado_por)
                 VALUES (:cliente, :tipo_juego, :ciclo, :importe, :pozo, :gastos,
                         1, 'confirmada', 'staff', :grupo, :promocion, 'activa', :usuario)"
            );
            $stmtNum = $this->db->prepare(
                'INSERT INTO jugada_numeros (jugada_id, numero) VALUES (:jugada, :numero)'
            );

            $ids = [];
            foreach ($numerosPorJugada as $i => $numeros) {
                $reparto = $this->parametros->repartir($importes[$i]);

                $stmt->execute([
                    ':cliente'    => $cliente['id'],
                    ':tipo_juego' => $tipoJuego,
                    ':ciclo'      => $ciclo['id'],
                    ':importe'    => $importes[$i],
                    ':pozo'       => $reparto['pozo'],
                    ':gastos'     => $reparto['gastos'],
                    ':grupo'      => $grupo,
                    ':promocion'  => $promocionId,
                    ':usuario'    => $cargadoPor,
                ]);

                $jugadaId = (int) $this->db->lastInsertId();
                $ids[]    = $jugadaId;

                foreach ($numeros as $numero) {
                    $stmtNum->execute([':jugada' => $jugadaId, ':numero' => $numero]);
                }

                $this->pozo->acumular((int) $ciclo['id'], $reparto['pozo']);

                // Fase 11: la jugada nace ya confirmada en este camino
                // (staff), asi que es el momento exacto de acreditar la
                // comision del referido, si corresponde.
                $this->comisiones->acreditarSiCorresponde((int) $cliente['id'], $jugadaId, $importes[$i]);
            }

            $this->db->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Resuelve el importe de cada jugada de un lote de $cantidad
     * [Fase 7]: el monto vigente para todas, o -si se pasa el id de
     * una promocion- el precio total del paquete dividido la cantidad,
     * repartido sin perder ni inventar centavos (mismo mecanismo que
     * el reparto de premios entre ganadores).
     *
     * La promocion tiene que estar activa y su cantidad_jugadas tiene
     * que coincidir EXACTAMENTE con $cantidad: si el cliente agrego o
     * saco una jugada despues de que se le sugirio la promo sin
     * actualizar cual aplica, esto lo frena en vez de cobrar mal.
     *
     * Las promociones son exclusivas del juego semanal: el juego de
     * sabados siempre cobra precio de lista (importeJugadaSabado()).
     *
     * @throws ValidacionException
     * @return float[]
     */
    public function resolverImportes(
        int $cantidad,
        ?int $promocionId,
        string $tipoJuego = CicloService::TIPO_SEMANAL
    ): array {
        if ($tipoJuego === CicloService::TIPO_SABADO) {
            if ($promocionId !== null) {
                throw ValidacionException::de('Las promociones no aplican al juego de sábados.');
            }
            return array_fill(0, $cantidad, $this->parametros->importeJugadaSabado());
        }

        if ($promocionId === null) {
            return array_fill(0, $cantidad, $this->parametros->importeJugada());
        }

        $promo = (new PromocionService($this->db))->buscarPorId($promocionId);
        if (!$promo || (int) $promo['activa'] !== 1) {
            throw ValidacionException::de('Esa promoción ya no está disponible. Recargá la página e intentá de nuevo.');
        }
        if ((int) $promo['cantidad_jugadas'] !== $cantidad) {
            throw ValidacionException::de(
                'La promoción es para ' . $promo['cantidad_jugadas'] . ' jugadas, pero se están cargando '
                . $cantidad . '. Ajustá la cantidad o no apliques la promo.'
            );
        }

        return PromocionService::importesPorJugada((float) $promo['precio_total'], $cantidad);
    }

    /**
     * Inserta N jugadas PENDIENTES DE PAGO para una solicitud armada por
     * el cliente desde el portal (Fase 6). A diferencia de crearVarias():
     *
     *  - ciclo_id queda NULL: todavia no pertenecen a ninguna semana.
     *  - pagada = 0 y estado_pago = 'pendiente_pago': no se cobro nada.
     *  - no se acumula al pozo aca. Eso ocurre recien cuando el staff
     *    confirma el pago, en SolicitudService::confirmar(), que le
     *    asigna el ciclo abierto EN ESE momento.
     *  - cargado_por queda NULL: nadie del staff tipeo estos numeros.
     *
     * El llamador (SolicitudService) ya valido cada set de numeros con
     * validarNumeros() y calculo el costo de cada jugada con
     * resolverImportes() al momento de la seleccion; por eso
     * $costosPorJugada se recibe hecho en vez de volver a leer
     * ParametroService/PromocionService aca, para que no pueda haber
     * diferencia entre el monto_total de la solicitud y el importe real
     * de cada jugada si algo cambiara en el medio.
     *
     * @param array<int,int[]> $numerosPorJugada Ya validados por el llamador.
     * @param array<int,array{importe:float,pozo:float,gastos:float}> $costosPorJugada
     *        Mismo orden e indice que $numerosPorJugada -con promocion,
     *        el importe puede variar en centavos de una jugada a otra
     *        por el redondeo del reparto.
     * @param int|null $promocionId Promocion aplicada a todo el lote, o null.
     * @return int[] Ids de las jugadas creadas.
     */
    public function crearPendientes(
        int $clienteId,
        array $numerosPorJugada,
        int $solicitudId,
        array $costosPorJugada,
        ?int $promocionId = null,
        string $tipoJuego = CicloService::TIPO_SEMANAL
    ): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO jugadas (cliente_id, tipo_juego, ciclo_id, importe, aporte_pozo, aporte_gastos,
                                      pagada, estado_pago, origen_carga, solicitud_id, promocion_id, estado)
                 VALUES (:cliente, :tipo_juego, NULL, :importe, :pozo, :gastos,
                         0, 'pendiente_pago', 'cliente', :solicitud, :promocion, 'activa')"
            );
            $stmtNum = $this->db->prepare(
                'INSERT INTO jugada_numeros (jugada_id, numero) VALUES (:jugada, :numero)'
            );

            $ids = [];
            foreach ($numerosPorJugada as $i => $numeros) {
                $costo = $costosPorJugada[$i];

                $stmt->execute([
                    ':cliente'    => $clienteId,
                    ':tipo_juego' => $tipoJuego,
                    ':importe'    => $costo['importe'],
                    ':pozo'       => $costo['pozo'],
                    ':gastos'     => $costo['gastos'],
                    ':solicitud'  => $solicitudId,
                    ':promocion'  => $promocionId,
                ]);

                $jugadaId = (int) $this->db->lastInsertId();
                $ids[]    = $jugadaId;

                foreach ($numeros as $numero) {
                    $stmtNum->execute([':jugada' => $jugadaId, ':numero' => $numero]);
                }
            }

            $this->db->commit();
            return $ids;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Normaliza y valida los numeros del formulario.
     *
     * Acepta "7", "07" y " 7 " como el mismo numero 7; rechaza vacios,
     * no numericos, fuera de rango, repetidos y cantidad distinta a la
     * esperada para ese tipo de juego (10 en el semanal, 5 en sabados —
     * ver ParametroService::numerosPorJugada()/numerosPorJugadaSabado()).
     * Devuelve enteros 0..99 ordenados de menor a mayor.
     *
     * @param string[] $crudos
     * @return int[]
     * @throws ValidacionException
     */
    public function validarNumeros(array $crudos, string $tipoJuego = CicloService::TIPO_SEMANAL): array
    {
        $esperados = $tipoJuego === CicloService::TIPO_SABADO
            ? $this->parametros->numerosPorJugadaSabado()
            : $this->parametros->numerosPorJugada();

        $numeros    = [];
        $repetidos  = [];
        $invalidos  = [];
        $vacios     = 0;

        foreach ($crudos as $crudo) {
            $valor = trim((string) $crudo);

            if ($valor === '') {
                $vacios++;
                continue;
            }
            if (!preg_match('/^\d{1,2}$/', $valor)) {
                $invalidos[] = $valor;
                continue;
            }

            $numero = (int) $valor;
            if (in_array($numero, $numeros, true)) {
                $repetidos[] = str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
                continue;
            }
            $numeros[] = $numero;
        }

        $errores = [];
        if ($invalidos) {
            $errores[] = 'Hay valores que no son numeros de dos cifras: ' . implode(', ', array_unique($invalidos)) . '.';
        }
        if ($repetidos) {
            $errores[] = 'No se puede repetir el mismo numero: ' . implode(', ', array_unique($repetidos)) . '.';
        }
        if ($vacios > 0 && !$invalidos && !$repetidos) {
            $errores[] = 'Faltan ' . $vacios . ' ' . ($vacios === 1 ? 'numero' : 'numeros') . ' para completar los ' . $esperados . '.';
        }
        if (!$errores && count($numeros) !== $esperados) {
            $errores[] = 'La jugada tiene que tener exactamente ' . $esperados . ' numeros distintos.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        sort($numeros);
        return $numeros;
    }

    /**
     * Cuantas jugadas tiene el ciclo, sin traerlas.
     *
     * Para los titulos tipo "Jugadas de Sábado (N)": listarPorCiclo()
     * corta en 200 sin avisar, asi que contar su salida mentia en
     * cuanto un ciclo pasaba ese tope.
     */
    public function contarPorCiclo(int $cicloId, string $busqueda = ''): int
    {
        [$where, $params] = $this->filtrosDeCiclo($cicloId, $busqueda);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM jugadas j
               JOIN clientes c ON c.id = j.cliente_id
              WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Todas las jugadas que siguen participando del ciclo, sin tope.
     *
     * Para los rankings: con el tope de 200 de listarPorCiclo() un ciclo
     * grande podia dejar afuera al que iba primero, y el ranking salia
     * mal sin ningun sintoma. Un ciclo son las jugadas de una semana (o
     * de un sabado), no un historico, asi que traerlas todas es acotado.
     */
    public function todasDelCiclo(int $cicloId): array
    {
        [$where, $params] = $this->filtrosDeCiclo($cicloId, '', false);
        return $this->consultarJugadasDelCiclo($where, $params, PHP_INT_MAX, 0);
    }

    /**
     * WHERE y parametros compartidos por listarPorCiclo(),
     * contarPorCiclo() y todasDelCiclo(). El listado de gestion conserva
     * las anuladas para trazabilidad; los rankings y vistas publicas usan
     * todasDelCiclo() y no las dejan participar.
     *
     * @return array{0: string[], 1: array<string, string|int>}
     */
    private function filtrosDeCiclo(int $cicloId, string $busqueda, bool $incluirAnuladas = true): array
    {
        $where  = ['j.ciclo_id = :ciclo'];
        $params = [':ciclo' => $cicloId];

        if (!$incluirAnuladas) {
            $where[] = "j.estado <> 'anulada'";
        }

        $busqueda = trim($busqueda);
        if ($busqueda !== '') {
            // Sin emulacion de prepares, cada aparicion necesita su propio
            // placeholder: PDO no reutiliza el mismo nombre dos veces.
            $where[] = '(c.nombre LIKE :q1 OR c.dni LIKE :q2 OR c.nro_cliente LIKE :q3)';
            $patron  = '%' . $busqueda . '%';
            $params[':q1'] = $patron;
            $params[':q2'] = $patron;
            $params[':q3'] = $patron;
        }

        return [$where, $params];
    }

    /**
     * Jugadas de un ciclo, con cliente, numeros y permisos de gestion.
     *
     * $offset habilita paginacion sin romper los llamados existentes de
     * tres argumentos. El listado incluye anuladas a proposito: son parte
     * de la trazabilidad, aunque no de los totales ni de los rankings.
     *
     * @return array<int,array>
     */
    public function listarPorCiclo(
        int $cicloId,
        string $busqueda = '',
        int $limite = 200,
        int $offset = 0
    ): array
    {
        [$where, $params] = $this->filtrosDeCiclo($cicloId, $busqueda);
        return $this->consultarJugadasDelCiclo($where, $params, $limite, $offset);
    }

    /**
     * Consulta comun para el listado paginado y la vista completa de
     * rankings. $where/$params vienen de filtrosDeCiclo(), nunca de UI.
     *
     * @param string[] $where
     * @param array<string,string|int> $params
     * @return array<int,array>
     */
    private function consultarJugadasDelCiclo(array $where, array $params, int $limite, int $offset): array
    {
        $limite = max(0, $limite);
        $offset = max(0, $offset);

        // GROUP_CONCAT ordenado trae los numeros en una sola pasada, sin
        // una consulta extra por jugada. El subquery de sorteos no
        // multiplica las filas del GROUP BY y permite explicar el bloqueo
        // con el total real de sorteos cargados.
        $sql = 'SELECT j.id, j.tipo_juego, j.ciclo_id, j.importe, j.aporte_pozo,
                       j.pagada, j.estado_pago, j.estado, j.fecha_carga, j.origen_carga,
                       c.id AS cliente_id, c.nombre AS cliente_nombre,
                       c.nro_cliente, c.dni,
                       cl.numero AS ciclo_numero, cl.estado AS ciclo_estado,
                       (SELECT COUNT(*) FROM sorteos s WHERE s.ciclo_id = j.ciclo_id) AS sorteos_total,
                       u.nombre AS cargado_por_nombre,
                       GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
                  FROM jugadas j
                  JOIN clientes c        ON c.id = j.cliente_id
                  JOIN ciclos cl         ON cl.id = j.ciclo_id
                  LEFT JOIN usuarios u   ON u.id = j.cargado_por
                  LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
                 WHERE ' . implode(' AND ', $where) . '
                 GROUP BY j.id
                 ORDER BY j.fecha_carga DESC, j.id DESC
                 LIMIT ' . $limite . ' OFFSET ' . $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
            $fila['sorteos_total'] = (int) $fila['sorteos_total'];
            $fila = $this->agregarPermisosGestion($fila);
        }
        unset($fila);

        return $filas;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT j.*, c.nombre AS cliente_nombre, c.nro_cliente, c.dni,
                    cl.numero AS ciclo_numero, cl.estado AS ciclo_estado, cl.tipo AS ciclo_tipo,
                    (SELECT COUNT(*) FROM sorteos s WHERE s.ciclo_id = j.ciclo_id) AS sorteos_total,
                    u.nombre AS cargado_por_nombre
               FROM jugadas j
               JOIN clientes c      ON c.id  = j.cliente_id
               LEFT JOIN ciclos cl  ON cl.id = j.ciclo_id
               LEFT JOIN usuarios u ON u.id  = j.cargado_por
              WHERE j.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);

        $jugada = $stmt->fetch();
        if (!$jugada) {
            return null;
        }

        $stmtNum = $this->db->prepare(
            'SELECT numero FROM jugada_numeros WHERE jugada_id = :id ORDER BY numero ASC'
        );
        $stmtNum->execute([':id' => $id]);
        $jugada['numeros'] = array_map('intval', $stmtNum->fetchAll(PDO::FETCH_COLUMN));
        $jugada['sorteos_total'] = (int) $jugada['sorteos_total'];

        return $this->agregarPermisosGestion($jugada);
    }

    /**
     * Hint de solo lectura para formularios: actualizarNumeros() y
     * anular() vuelven a validar bajo bloqueo, por lo que este resultado
     * nunca es la unica barrera de seguridad.
     *
     * @return array{puede_editar:bool,puede_anular:bool,bloqueo_jugada:?string}|null
     */
    public function permisosDeGestion(int $id): ?array
    {
        $jugada = $this->buscarPorId($id);
        if (!$jugada) {
            return null;
        }

        return [
            'puede_editar'  => (bool) $jugada['puede_editar'],
            'puede_anular'  => (bool) $jugada['puede_anular'],
            'bloqueo_jugada' => $jugada['bloqueo_jugada'],
        ];
    }

    /**
     * Ultimas jugadas cargadas, para el tablero.
     *
     * Filtra estado_pago = 'confirmada' [Fase 6]: sin esto, una jugada
     * que un cliente acaba de armar desde el portal (todavia sin pagar,
     * sin ciclo asignado) apareceria aca con sus 10 numeros a la vista,
     * indistinguible de una jugada real ya cobrada.
     *
     * $cicloId acota al ciclo en juego, y es lo que normalmente se
     * quiere: sin el, el tablero mostraba "ultimas jugadas" de toda la
     * historia de ese tipo de juego -- en un sabado recien abierto, las
     * del sabado ANTERIOR, que ya no compiten. Ademas el filtro por
     * ciclo entra por idx_jugadas_ciclo_estado en vez de agrupar y
     * ordenar toda la tabla en una temporal para devolver 5 filas.
     */
    public function ultimas(
        int $limite = 5,
        string $tipoJuego = CicloService::TIPO_SEMANAL,
        ?int $cicloId = null
    ): array {
        $where  = ["j.estado_pago = 'confirmada'", "j.estado <> 'anulada'", 'j.tipo_juego = :tipo_juego'];
        $params = [':tipo_juego' => $tipoJuego];

        if ($cicloId !== null) {
            $where[]           = 'j.ciclo_id = :ciclo';
            $params[':ciclo']  = $cicloId;
        }

        $stmt = $this->db->prepare(
            "SELECT j.id, j.importe, j.fecha_carga,
                    c.nombre AS cliente_nombre, c.nro_cliente,
                    GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
               FROM jugadas j
               JOIN clientes c ON c.id = j.cliente_id
               LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
              WHERE " . implode(' AND ', $where) . "
              GROUP BY j.id
              ORDER BY j.id DESC
              LIMIT " . (int) $limite
        );
        $stmt->execute($params);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotarNumeros($fila['numeros']);
        }

        return $filas;
    }

    /**
     * Reemplaza los numeros de una jugada sin cambiar cliente, importe ni
     * ciclo. El tipo se toma SIEMPRE de la fila almacenada: asi una
     * jugada de sabado sigue exigiendo exactamente sus 5 numeros aunque
     * un POST manipulado intente presentarla como semanal.
     *
     * @param string[] $numerosCrudos
     * @return array Jugada actualizada, incluidos numeros y permisos.
     * @throws ValidacionException
     */
    public function actualizarNumeros(int $id, array $numerosCrudos): array
    {
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $jugada = $this->bloquearJugadaParaGestion($id);
            $this->exigirGestionable($jugada);

            $tipoJuego = (string) $jugada['tipo_juego'];
            if (!array_key_exists($tipoJuego, CicloService::TIPOS)) {
                throw ValidacionException::de('La jugada tiene un tipo de juego inválido y no puede editarse.');
            }

            $numeros = $this->validarNumeros($numerosCrudos, $tipoJuego);
            $this->reemplazarNumeros((int) $jugada['id'], $numeros);

            if ($propia) {
                $this->db->commit();
            }

            $actualizada = $this->buscarPorId($id);
            if (!$actualizada) {
                throw ValidacionException::de('No se pudo recuperar la jugada actualizada.');
            }
            return $actualizada;
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Anula (sin borrar) una jugada antes de que empiece su ciclo. Revierte
     * su aporte al pozo y la comision solo si esa comision sigue pendiente;
     * una comision ya liquidada bloquea toda la operacion.
     *
     * @return array Jugada anulada, con aporte_pozo_revertido y comision_revertida.
     * @throws ValidacionException
     */
    public function anular(int $id): array
    {
        $propia = !$this->db->inTransaction();
        if ($propia) {
            $this->db->beginTransaction();
        }

        try {
            $jugada = $this->bloquearJugadaParaGestion($id);
            $this->exigirGestionable($jugada);

            $aporteRevertido = 0.0;
            if ((int) $jugada['pagada'] === 1 && $jugada['estado_pago'] === 'confirmada') {
                $aporteRevertido = (float) $jugada['aporte_pozo'];
                $this->pozo->descontar((int) $jugada['ciclo_id'], $aporteRevertido);
            }

            $comisionRevertida = $this->comisiones->revertirPorJugadaSiPendiente((int) $jugada['id']);

            $this->db->prepare(
                "UPDATE jugadas SET estado = 'anulada' WHERE id = :id AND estado = 'activa'"
            )->execute([':id' => $jugada['id']]);

            if ($propia) {
                $this->db->commit();
            }

            $anulada = $this->buscarPorId($id);
            if (!$anulada) {
                throw ValidacionException::de('No se pudo recuperar la jugada anulada.');
            }
            $anulada['aporte_pozo_revertido'] = $aporteRevertido;
            $anulada['comision_revertida']    = $comisionRevertida;
            return $anulada;
        } catch (Throwable $e) {
            if ($propia && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Compatibilidad con el endpoint historico: desde ahora nunca borra
     * fisicamente una jugada. Quien aun llame eliminar() recibe las mismas
     * reglas estrictas y una anulacion segura.
     *
     * @throws ValidacionException
     */
    public function eliminar(int $id): void
    {
        $this->anular($id);
    }

    // ── Gestion segura (edicion y anulacion) ──────────────────

    /**
     * Toma el candado del ciclo ANTES que el de la jugada, en el mismo
     * orden que SorteoService. Asi no puede entrar un sorteo entre la
     * validacion y la escritura, ni formarse un deadlock con el cotejo.
     *
     * @return array Fila de jugada bloqueada, con estado de ciclo y total de sorteos.
     * @throws ValidacionException
     */
    private function bloquearJugadaParaGestion(int $id): array
    {
        $stmt = $this->db->prepare('SELECT ciclo_id FROM jugadas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $referencia = $stmt->fetch();

        if (!$referencia) {
            throw ValidacionException::de('La jugada no existe.');
        }

        $cicloId = $referencia['ciclo_id'] === null ? null : (int) $referencia['ciclo_id'];
        $ciclo   = null;
        if ($cicloId !== null) {
            $ciclo = $this->ciclos->bloquearPorId($cicloId);
            if (!$ciclo) {
                throw ValidacionException::de('La jugada apunta a un ciclo inexistente y no puede gestionarse.');
            }
        }

        $stmt = $this->db->prepare('SELECT * FROM jugadas WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $jugada = $stmt->fetch();

        if (!$jugada) {
            throw ValidacionException::de('La jugada ya no existe.');
        }

        $cicloActual = $jugada['ciclo_id'] === null ? null : (int) $jugada['ciclo_id'];
        if ($cicloActual !== $cicloId) {
            // Este caso solo puede darse si una solicitud pendiente se
            // confirmo entre la lectura inicial y el lock. No tomamos su
            // ciclo despues de haber bloqueado la jugada (orden inverso al
            // de SorteoService): se reintenta y la proxima vez se bloquea
            // correctamente ciclo -> jugada.
            throw ValidacionException::de('La jugada cambió de ciclo mientras se intentaba gestionar. Reintentá.');
        }

        $jugada['ciclo_estado'] = $ciclo['estado'] ?? null;
        $jugada['ciclo_tipo']   = $ciclo['tipo'] ?? null;
        $jugada['sorteos_total'] = $cicloId === null ? 0 : $this->contarSorteosDelCiclo($cicloId);

        return $jugada;
    }

    /** @throws ValidacionException */
    private function exigirGestionable(array $jugada): void
    {
        $motivo = $this->motivoBloqueoGestion($jugada);
        if ($motivo !== null) {
            throw ValidacionException::de($motivo);
        }
    }

    /**
     * Motivo comun para los hints de lectura y la defensa transaccional.
     * null significa que la jugada puede editarse o anularse.
     */
    private function motivoBloqueoGestion(array $jugada): ?string
    {
        if (($jugada['ciclo_id'] ?? null) === null) {
            return 'La jugada todavía no está asignada a un ciclo y se gestiona desde Solicitudes.';
        }
        if ((int) ($jugada['pagada'] ?? 0) !== 1 || ($jugada['estado_pago'] ?? '') !== 'confirmada') {
            return 'Solo se pueden gestionar jugadas pagadas y confirmadas.';
        }

        $estadoCiclo = (string) ($jugada['ciclo_estado'] ?? '');
        if (!in_array($estadoCiclo, [CicloService::ESTADO_ABIERTO, CicloService::ESTADO_PROGRAMADO], true)) {
            return 'No se puede gestionar una jugada de un ciclo cerrado.';
        }
        if ((int) ($jugada['sorteos_total'] ?? 0) > 0) {
            return 'No se puede gestionar una jugada después de cargarse el primer sorteo del ciclo.';
        }

        $estado = (string) ($jugada['estado'] ?? '');
        if ($estado === 'anulada') {
            return 'La jugada ya fue anulada.';
        }
        if ($estado !== 'activa') {
            return 'Solo se pueden gestionar jugadas activas.';
        }
        if (!array_key_exists((string) ($jugada['tipo_juego'] ?? ''), CicloService::TIPOS)) {
            return 'La jugada tiene un tipo de juego inválido y no puede gestionarse.';
        }

        return null;
    }

    /** Agrega los permisos como hint de UI, sin sustituir los locks de escritura. */
    private function agregarPermisosGestion(array $jugada): array
    {
        $bloqueo = $this->motivoBloqueoGestion($jugada);
        $jugada['puede_editar']   = $bloqueo === null;
        $jugada['puede_anular']   = $bloqueo === null;
        $jugada['bloqueo_jugada'] = $bloqueo;

        return $jugada;
    }

    private function contarSorteosDelCiclo(int $cicloId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM sorteos WHERE ciclo_id = :ciclo');
        $stmt->execute([':ciclo' => $cicloId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Reemplazo explicito de hijas: algunas instalaciones antiguas tienen
     * jugada_numeros sin FK/cascade. No depende de esa constraint; para
     * garantía completa de rollback la tabla debe estar en InnoDB (ver el
     * preflight de migración incluido).
     *
     * @param int[] $numeros
     */
    private function reemplazarNumeros(int $jugadaId, array $numeros): void
    {
        $this->db->prepare('DELETE FROM jugada_numeros WHERE jugada_id = :id')
            ->execute([':id' => $jugadaId]);

        $stmt = $this->db->prepare(
            'INSERT INTO jugada_numeros (jugada_id, numero) VALUES (:jugada, :numero)'
        );
        foreach ($numeros as $numero) {
            $stmt->execute([':jugada' => $jugadaId, ':numero' => $numero]);
        }
    }

    // ── Internos ────────────────────────────────────────────

    /** @throws ValidacionException */
    private function buscarClienteActivo(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, nro_cliente, activo FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        if (!$cliente) {
            throw ValidacionException::de('Elegí un cliente de la lista.');
        }
        if ((int) $cliente['activo'] !== 1) {
            throw ValidacionException::de('El cliente ' . $cliente['nombre'] . ' esta desactivado.');
        }

        return $cliente;
    }

    /** "3,17,42" -> [3, 17, 42] */
    private static function explotarNumeros(?string $concatenado): array
    {
        if ($concatenado === null || $concatenado === '') {
            return [];
        }
        return array_map('intval', explode(',', $concatenado));
    }

    /** UUIDv4 para agrupar las jugadas de una misma carga conjunta. */
    private static function uuid4(): string
    {
        $datos = random_bytes(16);
        $datos[6] = chr((ord($datos[6]) & 0x0f) | 0x40);
        $datos[8] = chr((ord($datos[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($datos), 4));
    }
}
