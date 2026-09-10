<?php

namespace Polla\Support;

/**
 * Credenciales correctas mismas, pero la cuenta esta desactivada.
 *
 * Distinta de la ValidacionException generica (usuario/clave incorrectos)
 * para que el login unificado no la confunda con "no matcheo" y siga
 * probando la otra tabla con un mensaje que ya no corresponde.
 */
class CuentaInactivaException extends ValidacionException
{
}
