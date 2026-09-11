<?php

namespace Polla\Services;

use PDO;
use Polla\Support\ValidacionException;

/**
 * Liquidacion de comisiones [Fase 11]. Exclusiva del administrador (los
 * handlers lo protegen con requireAdmin()).
 *
 * No hay vinculo entre una liquidacion y comisiones puntuales: es un
 * saldo tipo libro mayor. Liquidar registra el saldo pendiente del
 * momento como una fila nueva y, de ahi en mas,
 * ComisionService::saldoPendiente() (que resta liquidaciones de
 * comisiones) vuelve a dar $0 hasta que se acredite comision nueva.
 */
class LiquidacionService
{
    private PDO $db;
    private ComisionService $comisiones;

    public function __construct(PDO $db, ?ComisionService $comisiones = null)
    {
        $this->db         = $db;
        $this->comisiones = $comisiones ?? new ComisionService($db);
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db);
    }

    /**
     * Liquida el saldo pendiente ACTUAL de un referidor. Corre dentro de
     * su propia transaccion para que leer el saldo e insertar la
     * liquidacion sean una sola operacion atomica frente a una comision
     * que se acredite justo en el medio.
     *
     * @return float El monto liquidado.
     * @throws ValidacionException si no hay saldo pendiente.
     */
    public function liquidar(string $tipo, int $referidorId, int $liquidadoPor): float
    {
        $this->db->beginTransaction();
        try {
            $saldo = $this->comisiones->saldoPendiente($tipo, $referidorId);

            if ($saldo <= 0) {
                throw ValidacionException::de('No hay saldo pendiente para liquidar.');
            }

            $this->db->prepare(
                'INSERT INTO liquidaciones (referidor_tipo, referidor_id, monto, liquidado_por)
                 VALUES (:tipo, :id, :monto, :usuario)'
            )->execute([
                ':tipo'    => $tipo,
                ':id'      => $referidorId,
                ':monto'   => $saldo,
                ':usuario' => $liquidadoPor,
            ]);

            $this->db->commit();
            return $saldo;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Historial de liquidaciones ya cobradas por un referidor, de la mas
     * nueva a la mas vieja.
     *
     * @return array<int,array>
     */
    public function historial(string $tipo, int $referidorId, int $limite = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT l.id, l.monto, l.fecha, u.nombre AS liquidado_por_nombre
               FROM liquidaciones l
               LEFT JOIN usuarios u ON u.id = l.liquidado_por
              WHERE l.referidor_tipo = :tipo AND l.referidor_id = :id
              ORDER BY l.fecha DESC
              LIMIT ' . (int) $limite
        );
        $stmt->execute([':tipo' => $tipo, ':id' => $referidorId]);
        return $stmt->fetchAll();
    }
}
