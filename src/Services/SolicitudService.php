<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;
use Throwable;

/**
 * Seleccion propia de jugadas por el cliente, con autorizacion de pago
 * por staff (Fase 6).
 *
 * El cliente arma una o varias jugadas desde el portal; quedan
 * agrupadas en una `solicitud` con un codigo corto (numero_registro)
 * que usa para identificarse al pagar. Mientras la solicitud esta
 * pendiente, sus jugadas tienen ciclo_id = NULL y estado_pago =
 * 'pendiente_pago': no pertenecen a ningun ciclo, no suman al pozo y
 * el motor de cotejo las ignora (filtra por ciclo_id).
 *
 * Al confirmar el pago, recien ahi se les asigna el ciclo ABIERTO EN
 * ESE MOMENTO (no el que estaba vigente cuando el cliente armo la
 * jugada) y su 60% se suma al pozo. El candado es el mismo que usa
 * SorteoService (CicloService::bloquearAbierto()): mientras la
 * transaccion de confirmar corre, nadie mas puede tocar el ciclo
 * abierto, asi que dos confirmaciones simultaneas -o una confirmacion
 * justo cuando se esta cerrando la semana con un sorteo- no se pisan.
 */
class SolicitudService
{
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_RECHAZADA  = 'rechazada';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE  => 'Pendiente',
        self::ESTADO_CONFIRMADA => 'Confirmada',
        self::ESTADO_RECHAZADA  => 'Rechazada',
    ];

    /**
     * Sin 0/O/1/I/L: son los caracteres que mas se confunden al leerlos
     * en voz alta o en una pantalla chica.
     */
    private const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LARGO_CODIGO    = 6;
    private const REINTENTOS_CODIGO = 10;

    /**
     * Tope de jugadas por solicitud. El formulario del staff
     * (JugadaService::crearVarias) no tiene limite porque lo usa
     * personal de confianza; esta pantalla la usa un cliente autenticado
     * pero de cara al publico, asi que conviene un tope razonable contra
     * un envio accidental o abusivo. Para mas de esto, otra solicitud.
     */
    public const MAX_JUGADAS_POR_SOLICITUD = 20;

    private PDO $db;
    private JugadaService $jugadas;
    private ParametroService $parametros;
    private CicloService $ciclos;
    private PozoService $pozo;

    public function __construct(
        PDO $db,
        JugadaService $jugadas,
        ParametroService $parametros,
        CicloService $ciclos,
        PozoService $pozo
    ) {
        $this->db         = $db;
        $this->jugadas     = $jugadas;
        $this->parametros = $parametros;
        $this->ciclos     = $ciclos;
        $this->pozo       = $pozo;
    }

    /**
     * Fabrica: comparte una unica instancia de ParametroService/
     * CicloService/PozoService entre esta clase y el JugadaService
     * interno, para que "el monto vigente" se lea una sola vez por
     * request y no pueda haber drift entre el monto_total de la
     * solicitud y el importe de cada jugada.
     */
    public static function crearDesde(PDO $db): self
    {
        $parametros = new ParametroService($db);
        $ciclos     = new CicloService($db);
        $pozo       = new PozoService($db);
        $jugadas    = new JugadaService($db, $parametros, $ciclos, $pozo);

        return new self($db, $jugadas, $parametros, $ciclos, $pozo);
    }

    // ── El cliente arma su jugada ───────────────────────────

    /**
     * Crea la solicitud y sus jugadas pendientes de pago.
     *
     * Valida TODOS los sets de numeros antes de escribir nada (mismo
     * criterio que JugadaService::crearVarias): una jugada mal cargada
     * no debe dejar a las otras a mitad de camino.
     *
     * @param array<int,string[]> $listasDeNumeros Un set de numeros crudos por jugada.
     * @return array{solicitud_id:int, numero_registro:string, cantidad:int, monto_total:float}
     * @throws ValidacionException
     */
    public function crear(int $clienteId, array $listasDeNumeros): array
    {
        if (!$listasDeNumeros) {
            throw ValidacionException::de('Armá al menos una jugada.');
        }
        if (count($listasDeNumeros) > self::MAX_JUGADAS_POR_SOLICITUD) {
            throw ValidacionException::de(
                'Como mucho podés armar ' . self::MAX_JUGADAS_POR_SOLICITUD
                . ' jugadas en una misma solicitud. Para más, generá otra.'
            );
        }

        $numerosPorJugada = [];
        $errores          = [];
        foreach (array_values($listasDeNumeros) as $i => $crudos) {
            try {
                $numerosPorJugada[] = $this->jugadas->validarNumeros($crudos);
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

        $cliente = $this->buscarClienteAprobado($clienteId);

        $importe  = $this->parametros->importeJugada();
        $reparto  = $this->parametros->repartir($importe);
        $cantidad = count($numerosPorJugada);
        $montoTotal = round($importe * $cantidad, 2);

        [$solicitudId, $codigo] = $this->insertarSolicitudConReintento(
            (int) $cliente['id'],
            $cantidad,
            $montoTotal
        );

        try {
            $this->jugadas->crearPendientes($clienteId, $numerosPorJugada, $solicitudId, [
                'importe' => $importe,
                'pozo'    => $reparto['pozo'],
                'gastos'  => $reparto['gastos'],
            ]);
        } catch (Throwable $e) {
            // No dejar la solicitud huerfana: si las jugadas no se
            // pudieron grabar, tampoco tiene que quedar el encabezado.
            $this->db->prepare('DELETE FROM solicitudes WHERE id = :id')->execute([':id' => $solicitudId]);
            throw $e;
        }

        return [
            'solicitud_id'    => $solicitudId,
            'numero_registro' => $codigo,
            'cantidad'        => $cantidad,
            'monto_total'     => $montoTotal,
        ];
    }

    /**
     * Inserta la fila de `solicitudes` con reintento ante colision del
     * codigo al azar, igual criterio que ClienteService con nro_cliente:
     * si dos solicitudes sacan el mismo codigo, el UNIQUE frena una y el
     * reintento le genera otro.
     *
     * @return array{0:int, 1:string} [solicitud_id, numero_registro]
     * @throws ValidacionException
     */
    private function insertarSolicitudConReintento(int $clienteId, int $cantidad, float $montoTotal): array
    {
        $stmt = $this->db->prepare(
            'INSERT INTO solicitudes (cliente_id, numero_registro, cantidad_jugadas, monto_total)
             VALUES (:cliente, :codigo, :cantidad, :monto)'
        );

        for ($intento = 1; $intento <= self::REINTENTOS_CODIGO; $intento++) {
            $codigo = $this->generarCodigo();
            try {
                $stmt->execute([
                    ':cliente'  => $clienteId,
                    ':codigo'   => $codigo,
                    ':cantidad' => $cantidad,
                    ':monto'    => $montoTotal,
                ]);
                return [(int) $this->db->lastInsertId(), $codigo];
            } catch (PDOException $e) {
                if (!self::esDuplicado($e)) {
                    throw $e;
                }
                // Colision del codigo: seguimos al siguiente intento.
            }
        }

        throw ValidacionException::de(
            'No se pudo generar un código único después de '
            . self::REINTENTOS_CODIGO . ' intentos. Reintentá en unos segundos.'
        );
    }

    private function generarCodigo(): string
    {
        $letras = self::ALFABETO_CODIGO;
        $max    = strlen($letras) - 1;

        $codigo = '';
        for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
            $codigo .= $letras[random_int(0, $max)];
        }

        return $codigo;
    }

    /** @throws ValidacionException */
    private function buscarClienteAprobado(int $clienteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, activo, estado FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $clienteId]);
        $cliente = $stmt->fetch();

        // requireCliente() en la pantalla ya filtra esto; se repite aca
        // como defensa en profundidad, no como unica barrera.
        if (!$cliente || (int) $cliente['activo'] !== 1 || $cliente['estado'] !== 'aprobado') {
            throw ValidacionException::de('Tu cuenta no puede armar jugadas en este momento.');
        }

        return $cliente;
    }

    // ── El staff busca, confirma o rechaza ──────────────────

    /**
     * Solicitud por su codigo, con el cliente y sus jugadas (numeros
     * incluidos). Null si el codigo no existe.
     */
    public function buscarPorCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT s.*, c.nombre AS cliente_nombre, c.nro_cliente, c.dni, c.telefono
               FROM solicitudes s
               JOIN clientes c ON c.id = s.cliente_id
              WHERE s.numero_registro = :codigo
              LIMIT 1'
        );
        $stmt->execute([':codigo' => $codigo]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            return null;
        }

        $solicitud['jugadas'] = $this->jugadasDe((int) $solicitud['id']);
        return $solicitud;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, c.nombre AS cliente_nombre, c.nro_cliente, c.dni, c.telefono
               FROM solicitudes s
               JOIN clientes c ON c.id = s.cliente_id
              WHERE s.id = :id
              LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $solicitud = $stmt->fetch();
        if (!$solicitud) {
            return null;
        }

        $solicitud['jugadas'] = $this->jugadasDe((int) $solicitud['id']);
        return $solicitud;
    }

    /** Ultima solicitud pendiente de un cliente, para el aviso en el portal. */
    public function pendientePara(int $clienteId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, numero_registro, cantidad_jugadas, monto_total, fecha_creacion
               FROM solicitudes
              WHERE cliente_id = :cliente AND estado = 'pendiente'
              ORDER BY fecha_creacion DESC
              LIMIT 1"
        );
        $stmt->execute([':cliente' => $clienteId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Cola de solicitudes pendientes, de la mas vieja a la mas nueva
     * (asi no se pierde la primera al final de una lista larga). Para
     * cuando el staff no tiene el codigo a mano.
     */
    public function listarPendientes(int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.numero_registro, s.cantidad_jugadas, s.monto_total, s.fecha_creacion,
                    c.nombre AS cliente_nombre, c.nro_cliente
               FROM solicitudes s
               JOIN clientes c ON c.id = s.cliente_id
              WHERE s.estado = 'pendiente'
              ORDER BY s.fecha_creacion ASC
              LIMIT " . (int) $limite
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function contarPendientes(): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'"
        )->fetchColumn();
    }

    /**
     * Confirma el pago: le asigna el ciclo abierto en ESE momento a
     * cada jugada de la solicitud, suma el 60% de cada una al pozo y
     * marca todo confirmado. Admin y supervisor pueden confirmar.
     *
     * @return array{ciclo_id:int, ciclo_numero:int, cantidad:int, monto_total:float, al_pozo:float}
     * @throws ValidacionException
     */
    public function confirmar(int $solicitudId, int $usuarioId): array
    {
        $this->db->beginTransaction();
        try {
            $solicitud = $this->bloquearPendiente($solicitudId);

            $ciclo = $this->ciclos->bloquearAbierto();
            if (!$ciclo) {
                throw ValidacionException::de(
                    'No hay ningún ciclo abierto. Entrá al tablero para que se abra el de esta semana.'
                );
            }
            $cicloId = (int) $ciclo['id'];

            $stmt = $this->db->prepare(
                "SELECT id, aporte_pozo FROM jugadas
                  WHERE solicitud_id = :solicitud AND estado_pago = 'pendiente_pago'"
            );
            $stmt->execute([':solicitud' => $solicitudId]);
            $filas = $stmt->fetchAll();

            if (!$filas) {
                throw ValidacionException::de('La solicitud no tiene jugadas para confirmar.');
            }

            $ids = array_map(static fn($f) => (int) $f['id'], $filas);
            $marcas = implode(',', array_fill(0, count($ids), '?'));

            $this->db->prepare(
                "UPDATE jugadas
                    SET ciclo_id = ?, pagada = 1, estado_pago = 'confirmada'
                  WHERE id IN ($marcas)"
            )->execute(array_merge([$cicloId], $ids));

            $alPozo = 0.0;
            foreach ($filas as $fila) {
                $alPozo += (float) $fila['aporte_pozo'];
                $this->pozo->acumular($cicloId, (float) $fila['aporte_pozo']);
            }

            $this->db->prepare(
                "UPDATE solicitudes
                    SET estado = 'confirmada', fecha_resolucion = NOW(), resuelto_por = :usuario
                  WHERE id = :id"
            )->execute([':usuario' => $usuarioId, ':id' => $solicitudId]);

            $this->db->commit();

            return [
                'ciclo_id'     => $cicloId,
                'ciclo_numero' => (int) $ciclo['numero'],
                'cantidad'     => count($ids),
                'monto_total'  => (float) $solicitud['monto_total'],
                'al_pozo'      => $alPozo,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Rechaza la solicitud: no toca ningún ciclo ni el pozo, porque
     * nada se llegó a sumar mientras estaba pendiente. Exclusivo del
     * administrador: lo exige tambien la pantalla que llama a este
     * metodo, con requireAdmin().
     *
     * @throws ValidacionException
     */
    public function rechazar(int $solicitudId, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $this->bloquearPendiente($solicitudId);

            $this->db->prepare(
                "UPDATE jugadas SET estado_pago = 'rechazada', estado = 'anulada'
                  WHERE solicitud_id = :id"
            )->execute([':id' => $solicitudId]);

            $this->db->prepare(
                "UPDATE solicitudes
                    SET estado = 'rechazada', fecha_resolucion = NOW(), resuelto_por = :usuario
                  WHERE id = :id"
            )->execute([':usuario' => $usuarioId, ':id' => $solicitudId]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Bloquea la fila de la solicitud (FOR UPDATE) y confirma que
     * siga pendiente. Evita que dos confirmaciones/rechazos simultaneos
     * sobre el mismo codigo se pisen: el segundo espera al primero y se
     * encuentra la solicitud ya resuelta.
     *
     * Solo tiene sentido llamarlo dentro de una transaccion.
     *
     * @throws ValidacionException
     */
    private function bloquearPendiente(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM solicitudes WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $solicitud = $stmt->fetch();

        if (!$solicitud) {
            throw ValidacionException::de('La solicitud no existe.');
        }
        if ($solicitud['estado'] !== self::ESTADO_PENDIENTE) {
            throw ValidacionException::de(
                'Esa solicitud ya fue resuelta (' . (self::ESTADOS[$solicitud['estado']] ?? $solicitud['estado']) . ').'
            );
        }

        return $solicitud;
    }

    private function jugadasDe(int $solicitudId): array
    {
        $stmt = $this->db->prepare(
            'SELECT j.id, j.importe,
                    GROUP_CONCAT(n.numero ORDER BY n.numero ASC) AS numeros
               FROM jugadas j
               LEFT JOIN jugada_numeros n ON n.jugada_id = j.id
              WHERE j.solicitud_id = :id
              GROUP BY j.id
              ORDER BY j.id ASC'
        );
        $stmt->execute([':id' => $solicitudId]);

        $filas = $stmt->fetchAll();
        foreach ($filas as &$fila) {
            $fila['numeros'] = self::explotar($fila['numeros']);
        }

        return $filas;
    }

    private static function explotar(?string $concatenado): array
    {
        if ($concatenado === null || $concatenado === '') {
            return [];
        }
        return array_map('intval', explode(',', $concatenado));
    }

    private static function esDuplicado(PDOException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
