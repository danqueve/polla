<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * ABM de usuarios del panel. Reservado al administrador
 * (los handlers lo protegen con requireAdmin()).
 */
class UsuarioService
{
    public const ROLES = ['admin' => 'Administrador', 'supervisor' => 'Supervisor'];

    private const PASSWORD_MIN = 8;

    /**
     * Sin 0/O/1/I/L: mismo alfabeto y largo que SolicitudService/
     * VendedorService usan para sus codigos cortos [Fase 11].
     */
    private const ALFABETO_CODIGO   = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LARGO_CODIGO      = 6;
    private const REINTENTOS_CODIGO = 10;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int,array> */
    public function listar(): array
    {
        return $this->db->query(
            'SELECT id, usuario, nombre, rol, codigo_referido, activo, ultimo_acceso, creado_en
               FROM usuarios
              ORDER BY activo DESC, rol ASC, nombre ASC'
        )->fetchAll();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, usuario, nombre, rol, codigo_referido, activo, ultimo_acceso, creado_en
               FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * @param array{usuario:string,nombre:string,rol:string,password:string,password2:string,activo?:mixed} $datos
     * @throws ValidacionException
     */
    public function crear(array $datos): int
    {
        $usuario = strtolower(trim($datos['usuario'] ?? ''));
        $nombre  = trim($datos['nombre'] ?? '');
        $rol     = $datos['rol'] ?? '';

        $errores = [];
        if (!preg_match('/^[a-z0-9._-]{3,50}$/', $usuario)) {
            $errores[] = 'El usuario debe tener entre 3 y 50 caracteres: letras, numeros, punto, guion o guion bajo.';
        }
        if ($nombre === '') {
            $errores[] = 'Cargá el nombre y apellido.';
        }
        if (!isset(self::ROLES[$rol])) {
            $errores[] = 'Elegí un rol valido.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        self::validarPassword($datos['password'] ?? '', $datos['password2'] ?? '');

        if ($this->existeUsuario($usuario)) {
            throw ValidacionException::de('Ya existe un usuario con ese nombre de acceso.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (usuario, nombre, password_hash, rol, activo)
             VALUES (:usuario, :nombre, :hash, :rol, :activo)'
        );
        $stmt->execute([
            ':usuario' => $usuario,
            ':nombre'  => $nombre,
            ':hash'    => password_hash($datos['password'], PASSWORD_DEFAULT),
            ':rol'     => $rol,
            ':activo'  => !empty($datos['activo']) ? 1 : 0,
        ]);

        $id = (int) $this->db->lastInsertId();

        // Fase 11: todo supervisor tiene codigo de referido desde que
        // nace, sin que nadie tenga que acordarse de generarlo aparte.
        $this->asignarCodigoReferidoSiFalta($id);

        return $id;
    }

    /**
     * Edita nombre, rol y estado. La clave solo se toca si viene cargada.
     *
     * @throws ValidacionException
     */
    public function actualizar(int $id, array $datos): void
    {
        $actual = $this->buscarPorId($id);
        if (!$actual) {
            throw ValidacionException::de('El usuario no existe.');
        }

        $nombre = trim($datos['nombre'] ?? '');
        $rol    = $datos['rol'] ?? '';
        $activo = !empty($datos['activo']) ? 1 : 0;

        $errores = [];
        if ($nombre === '') {
            $errores[] = 'Cargá el nombre y apellido.';
        }
        if (!isset(self::ROLES[$rol])) {
            $errores[] = 'Elegí un rol valido.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        // No dejar el sistema sin ningun admin activo por el que entrar.
        $dejaDeSerAdmin = $actual['rol'] === 'admin' && ($rol !== 'admin' || $activo === 0);
        if ($dejaDeSerAdmin && $this->contarAdminsActivos($id) === 0) {
            throw ValidacionException::de('Tiene que quedar al menos un administrador activo.');
        }

        $this->db->prepare(
            'UPDATE usuarios SET nombre = :nombre, rol = :rol, activo = :activo WHERE id = :id'
        )->execute([':nombre' => $nombre, ':rol' => $rol, ':activo' => $activo, ':id' => $id]);

        if (!empty($datos['password'])) {
            self::validarPassword($datos['password'], $datos['password2'] ?? '');
            $this->db->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id')
                     ->execute([':h' => password_hash($datos['password'], PASSWORD_DEFAULT), ':id' => $id]);
        }

        // Fase 11: si paso a supervisor (o ya lo era pero nunca tuvo
        // codigo, ej. una fila migrada a mano) y todavia no tiene uno,
        // se lo asigna aca. Nunca se lo saca si deja de ser supervisor:
        // un codigo inactivo no hace nada, y sacarlo podria romper un
        // link que alguien ya tiene compartido si vuelve a serlo.
        $this->asignarCodigoReferidoSiFalta($id);
    }

    /**
     * Genera y guarda un codigo_referido para este usuario si es
     * supervisor y todavia no tiene uno. No hace nada para un admin, ni
     * para un supervisor que ya tiene codigo. Con reintento ante
     * colision, mismo patron que SolicitudService::numero_registro.
     *
     * @return string|null El codigo asignado, o null si no correspondia.
     */
    public function asignarCodigoReferidoSiFalta(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT rol, codigo_referido FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        if (!$fila || $fila['rol'] !== 'supervisor' || $fila['codigo_referido'] !== null) {
            return null;
        }

        $update = $this->db->prepare('UPDATE usuarios SET codigo_referido = :c WHERE id = :id');

        for ($intento = 1; $intento <= self::REINTENTOS_CODIGO; $intento++) {
            $codigo = $this->generarCodigoReferido();
            try {
                $update->execute([':c' => $codigo, ':id' => $id]);
                return $codigo;
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                // Colision del codigo: seguimos al siguiente intento.
            }
        }

        throw ValidacionException::de(
            'No se pudo generar un código de referido único después de '
            . self::REINTENTOS_CODIGO . ' intentos. Reintentá en unos segundos.'
        );
    }

    private function generarCodigoReferido(): string
    {
        $letras = self::ALFABETO_CODIGO;
        $max    = strlen($letras) - 1;

        $codigo = '';
        for ($i = 0; $i < self::LARGO_CODIGO; $i++) {
            $codigo .= $letras[random_int(0, $max)];
        }

        return $codigo;
    }

    /**
     * Baja de un usuario. Si ya cargo clientes, jugadas o sorteos, las FK
     * lo dejan referenciado (ON DELETE SET NULL) y perderiamos el rastro
     * de quien cargo que, asi que en ese caso se desactiva en vez de borrar.
     *
     * @return string 'borrado' | 'desactivado'
     * @throws ValidacionException
     */
    public function eliminar(int $id, int $idUsuarioActual): string
    {
        if ($id === $idUsuarioActual) {
            throw ValidacionException::de('No podes borrar tu propio usuario.');
        }

        $usuario = $this->buscarPorId($id);
        if (!$usuario) {
            throw ValidacionException::de('El usuario no existe.');
        }

        if ($usuario['rol'] === 'admin' && $this->contarAdminsActivos($id) === 0) {
            throw ValidacionException::de('Tiene que quedar al menos un administrador activo.');
        }

        if ($this->tieneMovimientos($id)) {
            $this->db->prepare('UPDATE usuarios SET activo = 0 WHERE id = :id')->execute([':id' => $id]);
            return 'desactivado';
        }

        $this->db->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $id]);
        return 'borrado';
    }

    private function tieneMovimientos(int $id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT
                 (SELECT COUNT(*) FROM jugadas  WHERE cargado_por = :id1)
               + (SELECT COUNT(*) FROM sorteos  WHERE cargado_por = :id2)
               + (SELECT COUNT(*) FROM clientes WHERE alta_por    = :id3)'
        );
        $stmt->execute([':id1' => $id, ':id2' => $id, ':id3' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function existeUsuario(string $usuario): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM usuarios WHERE usuario = :u LIMIT 1');
        $stmt->execute([':u' => $usuario]);
        return (bool) $stmt->fetchColumn();
    }

    /** Cuenta los admin activos, excluyendo al que se esta por modificar. */
    private function contarAdminsActivos(int $excluirId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND activo = 1 AND id <> :id"
        );
        $stmt->execute([':id' => $excluirId]);
        return (int) $stmt->fetchColumn();
    }

    /** @throws ValidacionException */
    public static function validarPassword(string $nueva, string $repetida): void
    {
        $errores = [];
        if (mb_strlen($nueva) < self::PASSWORD_MIN) {
            $errores[] = 'La contrasena tiene que tener al menos ' . self::PASSWORD_MIN . ' caracteres.';
        }
        if ($nueva !== $repetida) {
            $errores[] = 'Las dos contrasenas no coinciden.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }
    }
}
