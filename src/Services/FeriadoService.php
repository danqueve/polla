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
     * Revisa que se pueda marcar el feriado, SIN guardar nada: fecha
     * valida, motivo y que no este ya marcada.
     *
     * Existe aparte de marcar() porque admin/feriados/guardar.php anula
     * el sorteo que ese dia ya tuviera cargado ANTES de guardar el
     * feriado, y borrar un sorteo no se deshace: todo lo que pueda
     * fallar por datos mal escritos tiene que fallar antes.
     *
     * @throws ValidacionException
     */
    public function validar(string $fechaCruda, string $motivoCrudo): DateTimeImmutable
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', trim($fechaCruda));
        if (!$fecha || $fecha->format('Y-m-d') !== trim($fechaCruda)) {
            throw ValidacionException::de('Elegí la fecha del feriado.');
        }

        $motivo = trim($motivoCrudo);
        if ($motivo === '') {
            throw ValidacionException::de('Contá el motivo del feriado (ej: "Feriado provincial").');
        }
        if (mb_strlen($motivo) > 160) {
            throw ValidacionException::de('El motivo es demasiado largo (máximo 160 caracteres).');
        }

        if ($this->esFeriado($fecha)) {
            throw ValidacionException::de('Esa fecha ya está marcada como feriado.');
        }

        return $fecha;
    }

    /**
     * Marca una fecha como feriado (sin sorteo ese dia).
     *
     * Si ese dia todavia tiene un sorteo cargado, el que llama tiene que
     * haberlo anulado antes (SorteoService::anularDeFecha()): un dia no
     * puede ser feriado y tener extracto a la vez.
     *
     * @throws ValidacionException
     */
    public function marcar(string $fechaCruda, string $motivoCrudo, ?int $usuarioId): DateTimeImmutable
    {
        $fecha  = $this->validar($fechaCruda, $motivoCrudo);
        $motivo = trim($motivoCrudo);

        $fechaFmt = $fecha->format('Y-m-d');

        // Red de seguridad: si entre anular y guardar alguien cargo otro
        // sorteo ese dia, no se deja la contradiccion.
        $yaHaySorteo = $this->db->prepare('SELECT COUNT(*) FROM sorteos WHERE fecha = :fecha');
        $yaHaySorteo->execute([':fecha' => $fechaFmt]);
        if ((int) $yaHaySorteo->fetchColumn() > 0) {
            throw ValidacionException::de(
                'El ' . $fecha->format('d/m/Y') . ' tiene un sorteo cargado: anulalo antes de marcarlo feriado.'
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
