<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Rate limiting para login: limita intentos fallidos por identificador + IP.
 *
 * Umbral: MAX_INTENTOS en VENTANA_MINUTOS. Al superar el umbral, tira
 * ValidacionException con un mensaje generico (sin filtrar cuanto falta).
 * Cada chequeo purga filas viejas de paso, asi que no hace falta un cron.
 */
class LoginThrottle
{
    private const MAX_INTENTOS     = 5;
    private const VENTANA_MINUTOS  = 15;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Chequea si el identificador+IP esta bloqueado. Llamar ANTES de
     * verificar credenciales.
     *
     * @throws ValidacionException si supera el umbral
     */
    public function verificar(string $identificador, string $ip): void
    {
        $this->purgar();

        $stmt = $this->db->prepare(
            'SELECT intentos FROM login_intentos
              WHERE identificador = :ident AND ip = :ip
                AND ultimo_intento > DATE_SUB(NOW(), INTERVAL :ventana MINUTE)
              LIMIT 1'
        );
        $stmt->execute([
            ':ident'   => $identificador,
            ':ip'      => $ip,
            ':ventana' => self::VENTANA_MINUTOS,
        ]);
        $intentos = (int) $stmt->fetchColumn();

        if ($intentos >= self::MAX_INTENTOS) {
            throw ValidacionException::de(
                'Demasiados intentos. Esperá unos minutos antes de reintentar.'
            );
        }
    }

    /**
     * Registra un intento fallido. Llamar despues de que falle la
     * verificacion de credenciales.
     */
    public function registrarFallo(string $identificador, string $ip): void
    {
        $this->db->prepare(
            'INSERT INTO login_intentos (identificador, ip, intentos, ultimo_intento)
             VALUES (:ident, :ip, 1, NOW())
             ON DUPLICATE KEY UPDATE intentos = intentos + 1, ultimo_intento = NOW()'
        )->execute([':ident' => $identificador, ':ip' => $ip]);
    }

    /**
     * Limpia el contador al loguearse con exito (para que un fallo viejo
     * no penalice la proxima vez).
     */
    public function limpiar(string $identificador, string $ip): void
    {
        $this->db->prepare(
            'DELETE FROM login_intentos WHERE identificador = :ident AND ip = :ip'
        )->execute([':ident' => $identificador, ':ip' => $ip]);
    }

    /**
     * Purga filas mas viejas que la ventana. Corre en cada verificar(),
     * asi que la tabla se auto-limpia sin cron.
     */
    private function purgar(): void
    {
        $this->db->exec(
            'DELETE FROM login_intentos
              WHERE ultimo_intento < DATE_SUB(NOW(), INTERVAL ' . (self::VENTANA_MINUTOS * 2) . ' MINUTE)'
        );
    }
}
