<?php

namespace Polla\Services;

use PDO;

/**
 * Log de auditoria inmutable: registra acciones sensibles para
 * trazabilidad operativa.
 *
 * No lanza excepciones a proposito: si falla el INSERT de auditoria,
 * se loguea a error_log y la operacion original sigue adelante.
 * Un log roto no debe frenar la operacion que estaba auditando.
 */
class AuditoriaService
{
    // ── Claves de accion (usar estas constantes, no strings sueltos) ──

    // Login
    public const LOGIN_OK              = 'login_ok';
    public const LOGIN_FALLIDO         = 'login_fallido';
    public const LOGIN_BLOQUEADO       = 'login_bloqueado';

    // Clientes
    public const CLIENTE_CREADO        = 'cliente_creado';
    public const CLIENTE_EDITADO       = 'cliente_editado';
    public const CLIENTE_ELIMINADO     = 'cliente_eliminado';
    public const CLIENTE_APROBADO      = 'cliente_aprobado';
    public const CLIENTE_RECHAZADO     = 'cliente_rechazado';
    public const CLIENTE_CLAVE_RESET   = 'cliente_clave_reset';

    // Sorteos
    public const SORTEO_CARGADO        = 'sorteo_cargado';
    public const SORTEO_CORREGIDO      = 'sorteo_corregido';
    public const SORTEO_ELIMINADO      = 'sorteo_eliminado';

    // Jugadas
    public const JUGADA_CARGADA        = 'jugada_cargada';
    public const JUGADA_ELIMINADA      = 'jugada_eliminada';

    // Solicitudes
    public const SOLICITUD_CONFIRMADA  = 'solicitud_confirmada';
    public const SOLICITUD_RECHAZADA   = 'solicitud_rechazada';

    // Liquidaciones
    public const LIQUIDACION_GENERADA  = 'liquidacion_generada';

    // Usuarios
    public const USUARIO_CREADO        = 'usuario_creado';
    public const USUARIO_EDITADO       = 'usuario_editado';
    public const USUARIO_ELIMINADO     = 'usuario_eliminado';
    public const CLAVE_CAMBIADA        = 'clave_cambiada';

    // Vendedores
    public const VENDEDOR_CREADO       = 'vendedor_creado';
    public const VENDEDOR_EDITADO      = 'vendedor_editado';
    public const VENDEDOR_ELIMINADO    = 'vendedor_eliminado';

    // Promociones
    public const PROMOCION_GUARDADA    = 'promocion_guardada';

    // Configuracion
    public const CONFIG_ACTUALIZADA    = 'config_actualizada';

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Registra una accion de auditoria.
     *
     * @param string      $accion    Constante de esta clase
     * @param string|null $entidad   Tabla afectada (clientes, sorteos...)
     * @param int|null    $entidadId ID del registro afectado
     * @param string|null $actorTipo usuario|cliente|vendedor|sistema
     * @param int|null    $actorId   ID del actor
     * @param array|null  $detalle   Contexto extra (se guarda como JSON)
     */
    public function registrar(
        string  $accion,
        ?string $entidad   = null,
        ?int    $entidadId = null,
        ?string $actorTipo = null,
        ?int    $actorId   = null,
        ?array  $detalle   = null
    ): void {
        try {
            $this->db->prepare(
                'INSERT INTO auditoria (accion, entidad, entidad_id, actor_tipo, actor_id, ip, detalle)
                 VALUES (:accion, :entidad, :entidad_id, :actor_tipo, :actor_id, :ip, :detalle)'
            )->execute([
                ':accion'     => $accion,
                ':entidad'    => $entidad,
                ':entidad_id' => $entidadId,
                ':actor_tipo' => $actorTipo,
                ':actor_id'   => $actorId,
                ':ip'         => self::ip(),
                ':detalle'    => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[auditoria] No se pudo registrar ' . $accion . ': ' . $e->getMessage());
        }
    }

    /**
     * Atajo para acciones de login (no hay actor conocido todavia).
     */
    public function loginOk(string $actorTipo, int $actorId, string $identificador): void
    {
        $this->registrar(self::LOGIN_OK, null, null, $actorTipo, $actorId, ['identificador' => $identificador]);
    }

    public function loginFallido(string $identificador): void
    {
        $this->registrar(self::LOGIN_FALLIDO, null, null, null, null, ['identificador' => $identificador]);
    }

    public function loginBloqueado(string $identificador): void
    {
        $this->registrar(self::LOGIN_BLOQUEADO, null, null, null, null, ['identificador' => $identificador]);
    }

    private static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? 'cli';
    }
}
