<?php

namespace Polla\Support;

/**
 * Hasta donde llega lo que un usuario puede ver en los reportes.
 *
 * El admin ve todo el negocio; el supervisor, unicamente lo que cargo
 * el mismo. En vez de dejar esa decision suelta en cada consulta, el
 * alcance se construye una sola vez desde la sesion y se le pasa al
 * constructor de ReporteService. Como el service no se puede instanciar
 * sin uno, no existe una consulta de reportes sin alcance aplicado:
 * olvidarse del filtro no es un descuido posible, es un error de tipos.
 */
final class AlcanceReporte
{
    private bool $esAdmin;
    private int  $usuarioId;

    private function __construct(bool $esAdmin, int $usuarioId)
    {
        $this->esAdmin   = $esAdmin;
        $this->usuarioId = $usuarioId;
    }

    /** Alcance total: todo el negocio. Solo para rol admin. */
    public static function total(int $usuarioId): self
    {
        return new self(true, $usuarioId);
    }

    /** Alcance acotado a lo que cargo este usuario. */
    public static function propio(int $usuarioId): self
    {
        return new self(false, $usuarioId);
    }

    /**
     * Arma el alcance a partir del rol de la sesion.
     * Cualquier rol que no sea exactamente 'admin' queda acotado: si
     * manana aparece un rol nuevo, por defecto ve menos, no mas.
     */
    public static function desdeSesion(int $usuarioId, string $rol): self
    {
        return $rol === 'admin' ? self::total($usuarioId) : self::propio($usuarioId);
    }

    public function esAdmin(): bool
    {
        return $this->esAdmin;
    }

    public function usuarioId(): int
    {
        return $this->usuarioId;
    }

    /**
     * Condicion SQL que acota una consulta, o null si el alcance es total.
     *
     * @param string $columna Columna de autoria de la tabla que se consulta:
     *                        j.cargado_por para jugadas, s.cargado_por para
     *                        sorteos, c.alta_por para clientes.
     */
    public function condicion(string $columna): ?string
    {
        return $this->esAdmin ? null : $columna . ' = :alcance_usuario';
    }

    /**
     * Parametros que acompanan a condicion(). Vacio cuando el alcance es
     * total, para no bindear un placeholder que la consulta no tiene.
     *
     * @return array<string,int>
     */
    public function parametros(): array
    {
        return $this->esAdmin ? [] : [':alcance_usuario' => $this->usuarioId];
    }

    /** Rotulo para encabezar las pantallas y los CSV. */
    public function rotulo(): string
    {
        return $this->esAdmin ? 'Todo el negocio' : 'Solo lo que cargaste vos';
    }
}
