<?php

namespace Polla\Support;

use RuntimeException;

/**
 * Error de negocio con un mensaje pensado para mostrarle al usuario.
 *
 * Los Services la lanzan cuando los datos no cumplen una regla; los
 * handlers POST la atrapan, la convierten en flash y redirigen al form.
 * Cualquier otra excepcion es un bug o un problema de infraestructura y
 * no deberia mostrarse tal cual.
 */
class ValidacionException extends RuntimeException
{
    /** @var string[] */
    private array $errores;

    /**
     * @param string[] $errores
     */
    public function __construct(array $errores)
    {
        $this->errores = array_values($errores);
        parent::__construct(implode(' ', $this->errores));
    }

    public static function de(string $mensaje): self
    {
        return new self([$mensaje]);
    }

    /** @return string[] */
    public function errores(): array
    {
        return $this->errores;
    }
}
