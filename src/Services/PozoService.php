<?php

namespace Polla\Services;

use PDO;

/**
 * Pozo del ciclo.
 *
 * Nace en $0 al abrir la semana y crece con el 60% de cada jugada pagada.
 * La liquidacion (repartir el pozo entre los ganadores y volver a $0) es
 * parte del motor de cotejo y se agrega en la fase 2.
 */
class PozoService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Suma al pozo del ciclo el aporte de una jugada.
     *
     * Se hace con un UPDATE relativo (monto = monto + :aporte) y no leyendo
     * y reescribiendo desde PHP, para que dos cargas simultaneas no se
     * pisen el saldo. Se llama dentro de la transaccion de JugadaService.
     */
    public function acumular(int $cicloId, float $aporte): void
    {
        // El INSERT ... ON DUPLICATE cubre el caso de un ciclo viejo que
        // todavia no tenga su fila de pozo.
        $stmt = $this->db->prepare(
            'INSERT INTO pozo_ciclo (ciclo_id, monto_acumulado, monto_pagado)
             VALUES (:ciclo, :aporte, 0)
             ON DUPLICATE KEY UPDATE monto_acumulado = monto_acumulado + VALUES(monto_acumulado)'
        );
        $stmt->execute([':ciclo' => $cicloId, ':aporte' => $aporte]);
    }

    /** Descuenta del pozo (anulacion de una jugada ya contabilizada). */
    public function descontar(int $cicloId, float $aporte): void
    {
        $this->db->prepare(
            'UPDATE pozo_ciclo
                SET monto_acumulado = GREATEST(monto_acumulado - :aporte, 0)
              WHERE ciclo_id = :ciclo'
        )->execute([':aporte' => $aporte, ':ciclo' => $cicloId]);
    }

    public function montoAcumulado(int $cicloId): float
    {
        $stmt = $this->db->prepare('SELECT monto_acumulado FROM pozo_ciclo WHERE ciclo_id = :id');
        $stmt->execute([':id' => $cicloId]);
        return (float) $stmt->fetchColumn();
    }

    public function obtener(int $cicloId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pozo_ciclo WHERE ciclo_id = :id LIMIT 1');
        $stmt->execute([':id' => $cicloId]);

        return $stmt->fetch() ?: [
            'ciclo_id'          => $cicloId,
            'monto_arrastrado'  => 0.0,
            'monto_acumulado'   => 0.0,
            'monto_pagado'      => 0.0,
            'fecha_liquidacion' => null,
        ];
    }

    /**
     * Reparte el pozo entre las jugadas ganadoras y deja el ciclo liquidado.
     *
     * Escribe una fila en `ganadores` por jugada con su monto_premio, y marca
     * el pozo con lo pagado y la fecha. Corre dentro de la transaccion de
     * SorteoService, con el ciclo ya bloqueado.
     *
     * @param int[] $jugadaIds Jugadas ganadoras de este sorteo.
     * @return array<int,float> jugada_id => premio
     */
    public function liquidar(int $cicloId, int $sorteoId, array $jugadaIds): array
    {
        $jugadaIds = array_values($jugadaIds);
        if (!$jugadaIds) {
            return [];
        }

        $monto   = $this->montoAcumulado($cicloId);
        $premios = self::repartirEnPartesIguales($monto, count($jugadaIds));

        $stmt = $this->db->prepare(
            'INSERT INTO ganadores (jugada_id, sorteo_id, ciclo_id, monto_premio)
             VALUES (:jugada, :sorteo, :ciclo, :premio)'
        );

        $asignados = [];
        foreach ($jugadaIds as $i => $jugadaId) {
            $stmt->execute([
                ':jugada' => $jugadaId,
                ':sorteo' => $sorteoId,
                ':ciclo'  => $cicloId,
                ':premio' => $premios[$i],
            ]);
            $asignados[$jugadaId] = $premios[$i];
        }

        $this->db->prepare(
            'UPDATE pozo_ciclo
                SET monto_pagado = :pagado, fecha_liquidacion = NOW()
              WHERE ciclo_id = :ciclo'
        )->execute([':pagado' => $monto, ':ciclo' => $cicloId]);

        return $asignados;
    }

    /**
     * Divide un monto en $n partes iguales sin perder ni inventar centavos.
     *
     * Trabaja en centavos enteros: reparte la base a todos y los centavos
     * que sobran de a uno entre los primeros. Asi la suma de las partes es
     * siempre exactamente el monto original, y $10.000 entre 3 da
     * 3333.34 + 3333.33 + 3333.33 en vez de tres veces 3333.33 y un centavo
     * que no cierra en la caja.
     *
     * @return float[]
     */
    public static function repartirEnPartesIguales(float $monto, int $partes): array
    {
        if ($partes < 1) {
            return [];
        }

        $centavos = (int) round($monto * 100);
        $base     = intdiv($centavos, $partes);
        $sobrante = $centavos - ($base * $partes);

        $resultado = [];
        for ($i = 0; $i < $partes; $i++) {
            $resultado[] = ($base + ($i < $sobrante ? 1 : 0)) / 100;
        }

        return $resultado;
    }
}
