<?php

namespace Polla\Services;

use PDO;
use PDOException;
use Polla\Support\ValidacionException;

/**
 * Paquetes promocionales de jugadas (Fase 7): N jugadas por un precio
 * total con descuento, ej. 4 jugadas por $10.000 en vez de 4 x $3.000.
 *
 * Como mucho una promocion ACTIVA por cada cantidad_jugadas: lo
 * garantiza el indice UNIQUE de la tabla (uk_promociones_cantidad_activa,
 * sobre una columna generada que solo vale algo cuando activa=1), no
 * una validacion aca — asi que la sugerencia al cargar jugadas es
 * siempre inequivoca: para una cantidad dada, activa() devuelve como
 * mucho una fila.
 */
class PromocionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Todas, activas primero y despues por cantidad, para la pantalla de administracion. */
    public function listar(): array
    {
        return $this->db->query(
            'SELECT p.*, u.nombre AS actualizado_por_nombre
               FROM promociones p
               LEFT JOIN usuarios u ON u.id = p.actualizado_por
              ORDER BY p.activa DESC, p.cantidad_jugadas ASC'
        )->fetchAll();
    }

    /**
     * Promociones activas, indexadas por cantidad_jugadas, para que la
     * pantalla de carga las busque en memoria sin una consulta por
     * cada cambio de cantidad.
     *
     * @return array<int,array>
     */
    public function activasPorCantidad(): array
    {
        $filas = $this->db->query(
            'SELECT * FROM promociones WHERE activa = 1 ORDER BY cantidad_jugadas ASC'
        )->fetchAll();

        $porCantidad = [];
        foreach ($filas as $fila) {
            $porCantidad[(int) $fila['cantidad_jugadas']] = $fila;
        }
        return $porCantidad;
    }

    /** La promo activa para una cantidad exacta, o null si no hay ninguna. */
    public function activaParaCantidad(int $cantidad): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM promociones WHERE activa = 1 AND cantidad_jugadas = :cantidad LIMIT 1'
        );
        $stmt->execute([':cantidad' => $cantidad]);
        return $stmt->fetch() ?: null;
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM promociones WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Reparte precio_total en cantidad partes sin perder ni inventar
     * centavos, mismo mecanismo que PozoService::repartirEnPartesIguales:
     * la suma de los importes es siempre exactamente precio_total.
     *
     * @return float[]
     */
    public static function importesPorJugada(float $precioTotal, int $cantidad): array
    {
        return PozoService::repartirEnPartesIguales($precioTotal, $cantidad);
    }

    /**
     * @param array{cantidad_jugadas:string,precio_total:string} $datos
     * @throws ValidacionException
     */
    public function crear(array $datos, int $actualizadoPor): int
    {
        [$cantidad, $precio] = $this->validarDatos($datos);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO promociones (cantidad_jugadas, precio_total, activa, actualizado_por)
                 VALUES (:cantidad, :precio, 1, :usuario)'
            );
            $stmt->execute([':cantidad' => $cantidad, ':precio' => $precio, ':usuario' => $actualizadoPor]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw $this->traducirError($e, $cantidad);
        }
    }

    /**
     * @param array{cantidad_jugadas:string,precio_total:string,activa?:mixed} $datos
     * @throws ValidacionException
     */
    public function actualizar(int $id, array $datos, int $actualizadoPor): void
    {
        if (!$this->buscarPorId($id)) {
            throw ValidacionException::de('La promoción no existe.');
        }

        [$cantidad, $precio] = $this->validarDatos($datos);
        $activa = !empty($datos['activa']) ? 1 : 0;

        try {
            $this->db->prepare(
                'UPDATE promociones
                    SET cantidad_jugadas = :cantidad, precio_total = :precio,
                        activa = :activa, actualizado_por = :usuario
                  WHERE id = :id'
            )->execute([
                ':cantidad' => $cantidad,
                ':precio'   => $precio,
                ':activa'   => $activa,
                ':usuario'  => $actualizadoPor,
                ':id'       => $id,
            ]);
        } catch (PDOException $e) {
            throw $this->traducirError($e, $cantidad);
        }
    }

    /**
     * Prende o apaga una promo sin tocar sus demas datos. Si se intenta
     * activar una que chocaria con otra ya activa de la misma cantidad,
     * el UNIQUE de la tabla lo frena y acá se traduce a un mensaje claro.
     *
     * @throws ValidacionException
     */
    public function cambiarEstado(int $id, bool $activa, int $actualizadoPor): void
    {
        $promo = $this->buscarPorId($id);
        if (!$promo) {
            throw ValidacionException::de('La promoción no existe.');
        }

        try {
            $this->db->prepare(
                'UPDATE promociones SET activa = :activa, actualizado_por = :usuario WHERE id = :id'
            )->execute([':activa' => $activa ? 1 : 0, ':usuario' => $actualizadoPor, ':id' => $id]);
        } catch (PDOException $e) {
            throw $this->traducirError($e, (int) $promo['cantidad_jugadas']);
        }
    }

    /** @throws ValidacionException */
    private function validarDatos(array $datos): array
    {
        $cantidad = (int) ($datos['cantidad_jugadas'] ?? 0);
        $precio   = (float) ($datos['precio_total'] ?? 0);

        $errores = [];
        if ($cantidad < 2 || $cantidad > 20) {
            $errores[] = 'La cantidad de jugadas del paquete tiene que estar entre 2 y 20.';
        }
        if ($precio <= 0) {
            $errores[] = 'El precio del paquete tiene que ser mayor a cero.';
        }
        if ($errores) {
            throw new ValidacionException($errores);
        }

        return [$cantidad, $precio];
    }

    /**
     * Traduce la colision del UNIQUE a un mensaje de negocio. Cualquier
     * otro error de base se relanza tal cual: no es un problema de
     * validacion, y no hay que disfrazar una falla real de infraestructura
     * ni filtrarle al usuario el mensaje crudo de SQL.
     *
     * @throws PDOException
     */
    private function traducirError(PDOException $e, int $cantidad): ValidacionException
    {
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'uk_promociones_cantidad_activa') !== false) {
            return ValidacionException::de(
                'Ya hay una promoción activa de ' . $cantidad . ' jugadas. '
                . 'Desactivá esa primero, o elegí otra cantidad.'
            );
        }
        throw $e;
    }
}
