<?php

namespace Polla\Services;

use DateTimeImmutable;
use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * Dias marcados "sin sorteo por feriado provincial".
 *
 * Sin esto, un feriado que cae en dia habil de la Nocturna (lunes a
 * viernes) o un sabado entero deja el ciclo esperando para siempre un
 * sorteo que nunca se va a cargar: CotejoService::secuenciaCompleta()
 * exige un sorteo por cada dia del rango del ciclo, y sin uno la
 * semana (o el sabado) nunca se declara completa ni cierra "sin
 * ganador" -- se queda abierta y bloquea que la siguiente arranque.
 *
 * Marcar un feriado es en si un evento de cierre, igual que cargar un
 * sorteo: si con el la secuencia de un ciclo ya abierto queda
 * completa, hay que recotejarlo ahi mismo, porque no va a existir
 * ningun sorteo que dispare ese cotejo despues. Ver
 * admin/feriados/guardar.php, que es quien orquesta ese recotejo.
 */
class FeriadoService
{
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
     * Marca una fecha como feriado (sin sorteo ese dia).
     *
     * @throws ValidacionException
     */
    public function marcar(string $fechaCruda, string $motivoCrudo, ?int $usuarioId): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fechaCruda));
        if (!$fecha) {
            throw ValidacionException::de('Elegí la fecha del feriado.');
        }

        $motivo = trim($motivoCrudo);
        if ($motivo === '') {
            throw ValidacionException::de('Contá el motivo del feriado (ej: "Feriado provincial").');
        }
        if (mb_strlen($motivo) > 160) {
            throw ValidacionException::de('El motivo es demasiado largo (máximo 160 caracteres).');
        }

        $fechaFmt = $fecha->format('Y-m-d');

        // Si ya hay un sorteo cargado ese dia, marcarlo feriado seria
        // una contradiccion (¿hubo sorteo o no?) que ademas nunca haria
        // falta: secuenciaCompleta() ya lo da por cubierto con el
        // sorteo real.
        $yaHaySorteo = $this->db->prepare('SELECT COUNT(*) FROM sorteos WHERE fecha = :fecha');
        $yaHaySorteo->execute([':fecha' => $fechaFmt]);
        if ((int) $yaHaySorteo->fetchColumn() > 0) {
            throw ValidacionException::de(
                'El ' . $fecha->format('d/m/Y') . ' ya tiene un sorteo cargado: no puede ser feriado.'
            );
        }

        try {
            $this->db->prepare(
                'INSERT INTO feriados (fecha, motivo, creado_por) VALUES (:fecha, :motivo, :usuario)'
            )->execute([':fecha' => $fechaFmt, ':motivo' => $motivo, ':usuario' => $usuarioId]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'uk_feriados_fecha') !== false) {
                throw ValidacionException::de('Esa fecha ya está marcada como feriado.');
            }
            throw $e;
        }

        return $fecha;
    }

    /** @throws ValidacionException */
    public function quitar(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM feriados WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            throw ValidacionException::de('Ese feriado no existe (puede que ya lo hayan quitado).');
        }
    }

    /**
     * Los mas recientes primero, pasados y futuros mezclados -- son
     * pocos por año, no hace falta paginar.
     */
    public function listar(int $limite = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT f.*, u.nombre AS creado_por_nombre
               FROM feriados f
               LEFT JOIN usuarios u ON u.id = f.creado_por
              ORDER BY f.fecha DESC
              LIMIT ' . (int) $limite
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function esFeriado(DateTimeImmutable $fecha): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM feriados WHERE fecha = :fecha');
        $stmt->execute([':fecha' => $fecha->format('Y-m-d')]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Fechas feriado dentro de un rango, en 'Y-m-d'. Para no consultar
     * dia por dia al recorrer una semana en
     * CotejoService::secuenciaCompleta().
     *
     * @return string[]
     */
    public function feriadosEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $stmt = $this->db->prepare(
            'SELECT fecha FROM feriados WHERE fecha BETWEEN :desde AND :hasta'
        );
        $stmt->execute([':desde' => $desde->format('Y-m-d'), ':hasta' => $hasta->format('Y-m-d')]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
