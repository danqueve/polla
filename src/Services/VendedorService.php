<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * ABM de vendedores [Fase 11]. Un vendedor no juega ni carga nada: solo
 * capta referidos con su link personal y cobra comision por lo que
 * juegan. Reservado al administrador (los handlers lo protegen con
 * requireAdmin()).
 */
class VendedorService
{
    /**
     * Sin 0/O/1/I/L: mismo alfabeto y largo que SolicitudService usa
     * para numero_registro, por consistencia de "codigo corto para
     * compartir en voz alta o en una pantalla chica".
     */
    private const ALFABETO_CODIGO   = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LARGO_CODIGO      = 6;
    private const REINTENTOS_CODIGO = 10;

    private const PASSWORD_MIN = 8;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int,array> */
    public function listar(): array
    {
        return $this->db->query(
            'SELECT id, nombre, dni, telefono, codigo_referido, activo, fecha_alta
               FROM vendedores
              ORDER BY activo DESC, nombre ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, dni, telefono, codigo_referido, activo, fecha_alta
               FROM vendedores WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, nombre, dni, telefono, codigo_referido, activo, fecha_alta
               FROM vendedores WHERE codigo_referido = :c LIMIT 1'
        );
        $stmt->execute([':c' => $codigo]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Alta de vendedor. Genera el codigo de referido solo, con
     * reintento ante colision.
     *
     * @param array{nombre:string,dni:string,telefono?:string,password:string,password2:string} $datos
     * @throws ValidacionException
     */
    public function crear(array $datos): int
    {
        $nombre   = trim($datos['nombre'] ?? '');
        $dni      = ClienteService::normalizarDni($datos['dni'] ?? '');
        $telefono = ClienteService::normalizarTelefono($datos['telefono'] ?? '');

        $this->validarDatos($nombre, $dni, $telefono);
        self::validarPassword($datos['password'] ?? '', $datos['password2'] ?? '');

        if ($this->existeDni($dni)) {
            throw ValidacionException::de('Ya hay un vendedor cargado con el DNI ' . $dni . '.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO vendedores (nombre, dni, telefono, password_hash, codigo_referido, activo)
             VALUES (:nombre, :dni, :telefono, :hash, :codigo, 1)'
        );

        for ($intento = 1; $intento <= self::REINTENTOS_CODIGO; $intento++) {
            $codigo = $this->generarCodigo();
            try {
                $stmt->execute([
                    ':nombre'   => $nombre,
                    ':dni'      => $dni,
                    ':telefono' => $telefono !== '' ? $telefono : null,
                    ':hash'     => password_hash($datos['password'], PASSWORD_DEFAULT),
                    ':codigo'   => $codigo,
                ]);
                return (int) $this->db->lastInsertId();
            } catch (PDOException $e) {
                if (!self::esDuplicado($e)) {
                    throw $e;
                }
                if (self::duplicadoEs($e, 'uk_vendedores_dni')) {
                    throw ValidacionException::de('Ya hay un vendedor cargado con el DNI ' . $dni . '.');
                }
                // Colision del codigo: seguimos al siguiente intento.
            }
        }

        throw ValidacionException::de(
            'No se pudo generar un código único después de '
            . self::REINTENTOS_CODIGO . ' intentos. Reintentá en unos segundos.'
        );
    }

    /**
     * Edicion. El codigo de referido no se toca aca (es fijo desde el
     * alta: cambiarlo invalidaria los links ya compartidos).
     *
     * @throws ValidacionException
     */
    public function actualizar(int $id, array $datos): void
    {
        $vendedor = $this->buscarPorId($id);
        if (!$vendedor) {
            throw ValidacionException::de('El vendedor no existe.');
        }

        $nombre   = trim($datos['nombre'] ?? '');
        $dni      = ClienteService::normalizarDni($datos['dni'] ?? '');
        $telefono = ClienteService::normalizarTelefono($datos['telefono'] ?? '');
        $activo   = !empty($datos['activo']) ? 1 : 0;

        $this->validarDatos($nombre, $dni, $telefono);

        if ($this->existeDni($dni, $id)) {
            throw ValidacionException::de('Ya hay otro vendedor con el DNI ' . $dni . '.');
        }

        $this->db->prepare(
            'UPDATE vendedores SET nombre = :nombre, dni = :dni, telefono = :telefono, activo = :activo
              WHERE id = :id'
        )->execute([
            ':nombre'   => $nombre,
            ':dni'      => $dni,
            ':telefono' => $telefono !== '' ? $telefono : null,
            ':activo'   => $activo,
            ':id'       => $id,
        ]);

        if (!empty($datos['password'])) {
            self::validarPassword($datos['password'], $datos['password2'] ?? '');
            $this->db->prepare('UPDATE vendedores SET password_hash = :h WHERE id = :id')
                     ->execute([':h' => password_hash($datos['password'], PASSWORD_DEFAULT), ':id' => $id]);
        }
    }

    /**
     * Baja. Si ya tiene referidos o comisiones, no se borra (perderia el
     * rastro de quien capto a quien): se desactiva, mismo criterio que
     * clientes/usuarios.
     *
     * @return string 'borrado' | 'desactivado'
     * @throws ValidacionException
     */
    public function eliminar(int $id): string
    {
        if (!$this->buscarPorId($id)) {
            throw ValidacionException::de('El vendedor no existe.');
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM clientes WHERE referido_por_tipo = 'vendedor' AND referido_por_id = :id"
        );
        $stmt->execute([':id' => $id]);

        if ((int) $stmt->fetchColumn() > 0) {
            $this->db->prepare('UPDATE vendedores SET activo = 0 WHERE id = :id')->execute([':id' => $id]);
            return 'desactivado';
        }

        $this->db->prepare('DELETE FROM vendedores WHERE id = :id')->execute([':id' => $id]);
        return 'borrado';
    }

    // ── Internos ────────────────────────────────────────────

    /** @throws ValidacionException */
    private function validarDatos(string $nombre, string $dni, string $telefono): void
    {
        $errores = [];

        if (mb_strlen($nombre) < 3) {
            $errores[] = 'Cargá el nombre y apellido del vendedor.';
        }
        if (mb_strlen($nombre) > 120) {
            $errores[] = 'El nombre es demasiado largo.';
        }
        if (!preg_match('/^\d{7,9}$/', $dni)) {
            $errores[] = 'El DNI tiene que ser un número de 7 a 9 dígitos, sin puntos.';
        }
        if ($telefono === '') {
            $errores[] = 'Cargá el teléfono del vendedor.';
        } elseif (!preg_match('/^[\d\s()+-]{6,30}$/', $telefono)) {
            $errores[] = 'El teléfono solo puede tener números, espacios y los signos + - ( ).';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }

    /** @throws ValidacionException */
    public static function validarPassword(string $nueva, string $repetida): void
    {
        $errores = [];
        if (mb_strlen($nueva) < self::PASSWORD_MIN) {
            $errores[] = 'La contraseña tiene que tener al menos ' . self::PASSWORD_MIN . ' caracteres.';
        }
        if ($nueva !== $repetida) {
            $errores[] = 'Las dos contraseñas no coinciden.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }

    private function existeDni(string $dni, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT 1 FROM vendedores WHERE dni = :dni';
        $params = [':dni' => $dni];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excluirId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
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

    private static function esDuplicado(PDOException $e): bool
    {
        return $e->getCode() === '23000';
    }

    private static function duplicadoEs(PDOException $e, string $indice): bool
    {
        return strpos($e->getMessage(), $indice) !== false;
    }
}
