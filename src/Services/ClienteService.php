<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * ABM de clientes.
 *
 * El alta genera el nro_cliente (AAAA-NNNNNN: anio + 6 digitos al azar) y
 * deja la clave del portal igual al DNI. La clave es siempre el DNI
 * vigente, sin excepciones ni cambio posible: actualizar() regenera el
 * hash cada vez que el DNI se edita, asi que las dos columnas nunca
 * quedan desincronizadas.
 */
class ClienteService
{
    /** Reintentos ante colision del numero aleatorio con uno ya existente. */
    private const REINTENTOS_NRO = 10;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Listado con busqueda por nombre, DNI o numero de cliente.
     *
     * @return array<int,array>
     */
    public function listar(string $busqueda = '', bool $soloActivos = false, int $limite = 200): array
    {
        $where  = [];
        $params = [];

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
        if ($soloActivos) {
            $where[] = 'c.activo = 1';
        }

        $sql = 'SELECT c.id, c.nro_cliente, c.dni, c.nombre, c.telefono,
                       c.activo, c.fecha_alta,
                       COUNT(j.id) AS jugadas_total
                  FROM clientes c
                  LEFT JOIN jugadas j ON j.cliente_id = c.id
                 ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
                 GROUP BY c.id
                 ORDER BY c.activo DESC, c.nombre ASC
                 LIMIT ' . (int) $limite;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nro_cliente, dni, nombre, telefono, activo,
                    fecha_alta, ultimo_acceso
               FROM clientes WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Clientes activos para poblar el selector del formulario de jugada. */
    public function listarActivosParaSelect(): array
    {
        return $this->db->query(
            'SELECT id, nro_cliente, dni, nombre
               FROM clientes
              WHERE activo = 1
              ORDER BY nombre ASC'
        )->fetchAll();
    }

    /**
     * Alta de cliente. Devuelve el id nuevo.
     *
     * @param array{dni:string,nombre:string,telefono?:string} $datos
     * @throws ValidacionException
     */
    public function crear(array $datos, ?int $altaPor = null): int
    {
        $dni      = self::normalizarDni($datos['dni'] ?? '');
        $nombre   = trim($datos['nombre'] ?? '');
        $telefono = self::normalizarTelefono($datos['telefono'] ?? '');

        $this->validarDatos($dni, $nombre, $telefono, telefonoObligatorio: true);

        if ($this->existeDni($dni)) {
            throw ValidacionException::de('Ya hay un cliente cargado con el DNI ' . $dni . '.');
        }

        // Alta manual: aprobada al instante, el staff ya valido los datos.
        return $this->insertar($dni, $nombre, $telefono, 'aprobado', 'manual', $altaPor);
    }

    /**
     * Alta por autorregistro publico: la cuenta nace pendiente de
     * aprobacion. Comparte con crear() la validacion, el chequeo de DNI
     * duplicado, el reintento del nro_cliente y la clave (siempre el
     * DNI); lo unico que cambia es el origen y el estado inicial.
     *
     * @param array{dni:string,nombre:string,telefono?:string} $datos
     * @throws ValidacionException
     */
    public function crearAutorregistro(array $datos): int
    {
        $dni      = self::normalizarDni($datos['dni'] ?? '');
        $nombre   = trim($datos['nombre'] ?? '');
        $telefono = self::normalizarTelefono($datos['telefono'] ?? '');

        $this->validarDatos($dni, $nombre, $telefono);

        if ($this->existeDni($dni)) {
            throw ValidacionException::de('Ya hay un cliente cargado con ese DNI.');
        }

        return $this->insertar($dni, $nombre, $telefono, 'pendiente', 'autorregistro', null);
    }

    /**
     * Inserta la fila con reintento ante colision del nro_cliente al azar:
     * si dos altas simultaneas sacan el mismo numero, el UNIQUE de la
     * tabla frena una y el reintento le genera otro numero distinto.
     *
     * La clave siempre es el DNI: se hashea aca mismo, no hay otra via
     * de entrada para el password_hash inicial.
     *
     * @throws ValidacionException
     */
    private function insertar(
        string $dni,
        string $nombre,
        string $telefono,
        string $estado,
        string $origenAlta,
        ?int $altaPor
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO clientes (nro_cliente, dni, nombre, telefono, password_hash,
                                   activo, estado, origen_alta, alta_por)
             VALUES (:nro, :dni, :nombre, :telefono, :hash, 1, :estado, :origen, :alta_por)'
        );

        for ($intento = 1; $intento <= self::REINTENTOS_NRO; $intento++) {
            try {
                $stmt->execute([
                    ':nro'      => $this->generarNroCliente(),
                    ':dni'      => $dni,
                    ':nombre'   => $nombre,
                    ':telefono' => $telefono !== '' ? $telefono : null,
                    ':hash'     => password_hash($dni, PASSWORD_DEFAULT),
                    ':estado'   => $estado,
                    ':origen'   => $origenAlta,
                    ':alta_por' => $altaPor,
                ]);
                return (int) $this->db->lastInsertId();
            } catch (PDOException $e) {
                if (!self::esDuplicado($e)) {
                    throw $e;
                }
                if (self::duplicadoEs($e, 'uk_clientes_dni')) {
                    throw ValidacionException::de('Ya hay un cliente cargado con el DNI ' . $dni . '.');
                }
                // Colision del numero de cliente: seguimos al siguiente intento.
            }
        }

        throw ValidacionException::de(
            'No se pudo generar un número de cliente único después de '
            . self::REINTENTOS_NRO . ' intentos. Reintentá en unos segundos.'
        );
    }

    /**
     * Edicion. El DNI se puede corregir (es tambien el usuario y la
     * clave del portal): si cambia, el password_hash se regenera acá
     * mismo a partir del DNI nuevo, para que la clave nunca quede
     * desincronizada.
     *
     * @throws ValidacionException
     */
    public function actualizar(int $id, array $datos): void
    {
        $cliente = $this->buscarPorId($id);
        if (!$cliente) {
            throw ValidacionException::de('El cliente no existe.');
        }

        $dni      = self::normalizarDni($datos['dni'] ?? '');
        $nombre   = trim($datos['nombre'] ?? '');
        $telefono = self::normalizarTelefono($datos['telefono'] ?? '');

        $this->validarDatos($dni, $nombre, $telefono);

        if ($this->existeDni($dni, $id)) {
            throw ValidacionException::de('Ya hay otro cliente con el DNI ' . $dni . '.');
        }

        $sql    = 'UPDATE clientes SET dni = :dni, nombre = :nombre, telefono = :telefono, activo = :activo';
        $params = [
            ':dni'      => $dni,
            ':nombre'   => $nombre,
            ':telefono' => $telefono !== '' ? $telefono : null,
            ':activo'   => !empty($datos['activo']) ? 1 : 0,
            ':id'       => $id,
        ];

        if ($dni !== $cliente['dni']) {
            $sql .= ', password_hash = :hash';
            $params[':hash'] = password_hash($dni, PASSWORD_DEFAULT);
        }

        $this->db->prepare($sql . ' WHERE id = :id')->execute($params);
    }

    /**
     * Resincroniza la clave del portal con el DNI actual. Con la clave
     * siempre regenerada al editar el DNI (actualizar()), esto no
     * debería hacer falta nunca — queda como red de seguridad manual
     * para un cliente cuyo password_hash haya quedado desincronizado
     * por alguna via anterior a este criterio.
     */
    public function resetearClave(int $id): string
    {
        $cliente = $this->buscarPorId($id);
        if (!$cliente) {
            throw ValidacionException::de('El cliente no existe.');
        }

        $this->db->prepare(
            'UPDATE clientes SET password_hash = :h WHERE id = :id'
        )->execute([':h' => password_hash($cliente['dni'], PASSWORD_DEFAULT), ':id' => $id]);

        return $cliente['dni'];
    }

    /**
     * Baja. Un cliente con jugadas no se borra (arrastraria el historial del
     * pozo): se desactiva y deja de aparecer para cargar jugadas nuevas.
     *
     * @return string 'borrado' | 'desactivado'
     * @throws ValidacionException
     */
    public function eliminar(int $id): string
    {
        if (!$this->buscarPorId($id)) {
            throw ValidacionException::de('El cliente no existe.');
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM jugadas WHERE cliente_id = :id');
        $stmt->execute([':id' => $id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $this->db->prepare('UPDATE clientes SET activo = 0 WHERE id = :id')->execute([':id' => $id]);
            return 'desactivado';
        }

        $this->db->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $id]);
        return 'borrado';
    }

    // ── Internos ────────────────────────────────────────────

    /**
     * $telefonoObligatorio: true solo en el alta manual (crear()) -- ahi
     * el staff esta cargando el dato de primera mano, asi que se le
     * exige completo. La edicion y el autorregistro publico lo siguen
     * dejando opcional (un cliente de antes de este cambio puede no
     * tener telefono cargado, y no corresponde bloquearle otra edicion
     * por eso).
     *
     * @throws ValidacionException
     */
    private function validarDatos(string $dni, string $nombre, string $telefono, bool $telefonoObligatorio = false): void
    {
        $errores = [];

        if (!preg_match('/^\d{7,9}$/', $dni)) {
            $errores[] = 'El DNI tiene que ser un numero de 7 a 9 digitos, sin puntos.';
        }
        if (mb_strlen($nombre) < 3) {
            $errores[] = 'Cargá el nombre y apellido del cliente.';
        }
        if (mb_strlen($nombre) > 120) {
            $errores[] = 'El nombre es demasiado largo.';
        }
        if ($telefonoObligatorio && $telefono === '') {
            $errores[] = 'Cargá el teléfono del cliente.';
        }
        if ($telefono !== '' && !preg_match('/^[\d\s()+-]{6,30}$/', $telefono)) {
            $errores[] = 'El telefono solo puede tener numeros, espacios y los signos + - ( ).';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }

    /**
     * Numero de cliente nuevo: anio actual + 6 digitos al azar
     * (ej. 2026-048372, o 2026-000481 si el azar da un numero chico).
     *
     * No es correlativo a proposito: al ser aleatorio, la unicidad no la
     * garantiza esta funcion sino el indice UNIQUE de la tabla
     * (uk_clientes_nro); el llamador (crear()) reintenta si el insert
     * choca con uno ya existente.
     */
    private function generarNroCliente(): string
    {
        $anio      = date('Y');
        $aleatorio = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return $anio . '-' . $aleatorio;
    }

    private function existeDni(string $dni, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT 1 FROM clientes WHERE dni = :dni';
        $params = [':dni' => $dni];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excluirId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    public static function normalizarDni(string $dni): string
    {
        return preg_replace('/\D/', '', $dni) ?? '';
    }

    public static function normalizarTelefono(string $telefono): string
    {
        return trim(preg_replace('/\s+/', ' ', $telefono) ?? '');
    }

    private static function esDuplicado(PDOException $e): bool
    {
        return $e->getCode() === '23000';
    }

    private static function duplicadoEs(PDOException $e, string $indice): bool
    {
        return strpos($e->getMessage(), $indice) !== false;
    }
}
