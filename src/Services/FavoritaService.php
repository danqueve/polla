<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * Jugadas favoritas: combinaciones que el cliente guarda para volver a
 * jugarlas sin tipear numero por numero.
 *
 * Una favorita NO es una jugada: no cuesta nada, no suma al pozo ni
 * participa de ningun cotejo. Es solo una lista de numeros guardada
 * con un nombre opcional, que el formulario de carga precarga en las
 * casillas; despues se confirma por el camino de siempre
 * (JugadaService / SolicitudService), con sus mismas validaciones.
 *
 * Los numeros se guardan en forma canonica (enteros ascendentes
 * separados por coma, "5,12,18,..."), la misma que devuelve
 * GROUP_CONCAT(numero ORDER BY numero) para una jugada real. Con eso
 * el UNIQUE de la tabla evita duplicados y "esta jugada ya es
 * favorita" se resuelve comparando dos strings.
 *
 * TODOS los metodos filtran por cliente_id en la propia consulta: un
 * cliente nunca puede ver, renombrar ni borrar las favoritas de otro,
 * aunque mande un id ajeno en el POST.
 */
class FavoritaService
{
    /** Tope por cliente (entre los dos juegos). Evita una lista infinita. */
    public const MAXIMO_POR_CLIENTE = 20;

    private const LARGO_MAXIMO_NOMBRE = 40;

    private PDO $db;
    private JugadaService $jugadas;

    public function __construct(PDO $db, JugadaService $jugadas)
    {
        $this->db      = $db;
        $this->jugadas = $jugadas;
    }

    public static function crearDesde(PDO $db): self
    {
        return new self($db, JugadaService::crearDesde($db));
    }

    // ── Forma canonica ──────────────────────────────────────

    /**
     * @param array<int|string> $numeros
     * @return string "5,12,18" -- enteros ascendentes, sin espacios.
     */
    public static function canonica(array $numeros): string
    {
        $enteros = array_map('intval', $numeros);
        sort($enteros);

        return implode(',', $enteros);
    }

    /**
     * @return int[]
     */
    public static function explotar(string $canonica): array
    {
        return $canonica === '' ? [] : array_map('intval', explode(',', $canonica));
    }

    // ── Lectura ─────────────────────────────────────────────

    /**
     * Las favoritas de un cliente para un juego, las mas nuevas primero.
     *
     * @return array<int,array{id:int, nombre:?string, numeros:int[], canonica:string}>
     */
    public function listar(int $clienteId, string $tipoJuego): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, numeros
               FROM jugadas_favoritas
              WHERE cliente_id = :cliente AND tipo_juego = :tipo
              ORDER BY id DESC'
        );
        $stmt->execute([':cliente' => $clienteId, ':tipo' => $tipoJuego]);

        return array_map([self::class, 'armarFila'], $stmt->fetchAll());
    }

    /**
     * Una favorita puntual, solo si es del cliente. null si no existe o
     * es de otro (no se distingue a proposito: no hay que revelar que el
     * id existe).
     *
     * @return array{id:int, nombre:?string, tipo_juego:string, numeros:int[], canonica:string}|null
     */
    public function buscar(int $clienteId, int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, numeros, tipo_juego
               FROM jugadas_favoritas
              WHERE id = :id AND cliente_id = :cliente'
        );
        $stmt->execute([':id' => $id, ':cliente' => $clienteId]);

        $fila = $stmt->fetch();

        return $fila ? self::armarFila($fila) + ['tipo_juego' => $fila['tipo_juego']] : null;
    }

    /** Cuantas favoritas tiene el cliente, entre los dos juegos (el tope es por cliente). */
    public function contar(int $clienteId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM jugadas_favoritas WHERE cliente_id = :cliente');
        $stmt->execute([':cliente' => $clienteId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Las combinaciones ya guardadas, para saber de un vistazo cuales de
     * las jugadas que se estan mostrando ya son favoritas.
     *
     * @return array<string,true> Set indexado por forma canonica.
     */
    public function canonicasDelCliente(int $clienteId, string $tipoJuego): array
    {
        $stmt = $this->db->prepare(
            'SELECT numeros FROM jugadas_favoritas WHERE cliente_id = :cliente AND tipo_juego = :tipo'
        );
        $stmt->execute([':cliente' => $clienteId, ':tipo' => $tipoJuego]);

        $set = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $canonica) {
            $set[$canonica] = true;
        }

        return $set;
    }

    // ── Escritura ───────────────────────────────────────────

    /**
     * Guarda una combinacion nueva.
     *
     * Valida con JugadaService::validarNumeros(): las mismas reglas que
     * una jugada de verdad (cantidad segun el juego, 00 a 99, sin
     * repetidos). Una favorita invalida es una jugada que despues no
     * se va a poder confirmar.
     *
     * @param string[] $numerosCrudos
     * @return int Id de la favorita nueva.
     * @throws ValidacionException
     */
    public function guardar(int $clienteId, array $numerosCrudos, string $tipoJuego, ?string $nombre = null): int
    {
        $tipoJuego = array_key_exists($tipoJuego, CicloService::TIPOS) ? $tipoJuego : CicloService::TIPO_SEMANAL;
        $numeros   = $this->jugadas->validarNumeros($numerosCrudos, $tipoJuego);
        $nombre    = $this->limpiarNombre($nombre);
        $canonica  = self::canonica($numeros);

        // El duplicado se avisa ANTES que el tope: con la lista llena,
        // volver a guardar una que ya esta no pierde nada, y decir
        // "llegaste al maximo" seria mentirle al cliente.
        if (isset($this->canonicasDelCliente($clienteId, $tipoJuego)[$canonica])) {
            throw ValidacionException::de('Ya tenés esa combinación en tus favoritas.');
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM jugadas_favoritas WHERE cliente_id = :cliente');
        $stmt->execute([':cliente' => $clienteId]);
        if ((int) $stmt->fetchColumn() >= self::MAXIMO_POR_CLIENTE) {
            throw ValidacionException::de(
                'Llegaste al máximo de ' . self::MAXIMO_POR_CLIENTE . ' favoritas. Borrá alguna para guardar otra.'
            );
        }

        try {
            $this->db->prepare(
                'INSERT INTO jugadas_favoritas (cliente_id, tipo_juego, nombre, numeros)
                 VALUES (:cliente, :tipo, :nombre, :numeros)'
            )->execute([
                ':cliente' => $clienteId,
                ':tipo'    => $tipoJuego,
                ':nombre'  => $nombre,
                ':numeros' => $canonica,
            ]);
        } catch (PDOException $e) {
            // Respaldo para la carrera de dos pedidos simultaneos: el
            // chequeo de arriba no es atomico, el UNIQUE si.
            if (strpos($e->getMessage(), 'uk_favorita') !== false) {
                throw ValidacionException::de('Ya tenés esa combinación en tus favoritas.');
            }
            throw $e;
        }

        return (int) $this->db->lastInsertId();
    }

    /**
     * Guarda como favorita los numeros de una jugada ya jugada.
     *
     * @return int Id de la favorita nueva.
     * @throws ValidacionException
     */
    public function guardarDesdeJugada(int $clienteId, int $jugadaId, ?string $nombre = null): int
    {
        $jugada = $this->jugadas->numerosDeJugadaDelCliente($clienteId, $jugadaId);
        if (!$jugada) {
            throw ValidacionException::de('Esa jugada no existe o no es tuya.');
        }

        return $this->guardar($clienteId, $jugada['numeros'], $jugada['tipo_juego'], $nombre);
    }

    /**
     * Cambia el nombre (o lo borra, si viene vacio).
     *
     * @throws ValidacionException
     */
    public function renombrar(int $clienteId, int $id, ?string $nombre): void
    {
        $stmt = $this->db->prepare(
            'UPDATE jugadas_favoritas SET nombre = :nombre WHERE id = :id AND cliente_id = :cliente'
        );
        $stmt->execute([':nombre' => $this->limpiarNombre($nombre), ':id' => $id, ':cliente' => $clienteId]);

        // rowCount() cuenta filas CAMBIADAS: ponerle el mismo nombre que ya
        // tenia da 0 aunque exista. Se distingue "no existe" de "no cambio"
        // mirando si la fila es del cliente.
        if ($stmt->rowCount() === 0 && !$this->buscar($clienteId, $id)) {
            throw ValidacionException::de('Esa favorita no existe.');
        }
    }

    /** @throws ValidacionException */
    public function eliminar(int $clienteId, int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM jugadas_favoritas WHERE id = :id AND cliente_id = :cliente');
        $stmt->execute([':id' => $id, ':cliente' => $clienteId]);

        if ($stmt->rowCount() === 0) {
            throw ValidacionException::de('Esa favorita no existe (puede que ya la hayas borrado).');
        }
    }

    // ── Internos ────────────────────────────────────────────

    /** Recorta; vacio pasa a null (sin nombre, se muestran los numeros). */
    private function limpiarNombre(?string $nombre): ?string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            return null;
        }
        if (mb_strlen($nombre) > self::LARGO_MAXIMO_NOMBRE) {
            throw ValidacionException::de(
                'El nombre es demasiado largo (máximo ' . self::LARGO_MAXIMO_NOMBRE . ' caracteres).'
            );
        }

        return $nombre;
    }

    /**
     * @param array{id:mixed, nombre:?string, numeros:string} $fila
     * @return array{id:int, nombre:?string, numeros:int[], canonica:string}
     */
    private static function armarFila(array $fila): array
    {
        return [
            'id'       => (int) $fila['id'],
            'nombre'   => $fila['nombre'],
            'numeros'  => self::explotar($fila['numeros']),
            'canonica' => $fila['numeros'],
        ];
    }
}
