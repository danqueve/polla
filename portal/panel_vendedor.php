<?php
/**
 * Salto sin clave del portal del cliente al panel de vendedor, para el
 * cliente que ademas tiene una cuenta de vendedor vinculada. Simetrico
 * a vendedor/jugar.php: el vinculo se resuelve siempre a partir de
 * quien ya esta autenticado como cliente en esta sesion, nunca de un
 * id que llegue por GET/POST.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\VendedorAuthService;
use Polla\Services\VendedorService;

requireCliente();

$db       = getPDO();
$vendedor = (new VendedorService($db))->buscarPorClienteId(clienteActualId());

if (!$vendedor || (int) $vendedor['activo'] !== 1) {
    setFlash('danger', 'No tenés (o ya no tenés) un panel de vendedor activo. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
    header('Location: ' . APP_URL . '/portal/index.php');
    exit;
}

cambiarASesion('POLLA_VENDEDOR');
(new VendedorAuthService($db))->abrirSesion($vendedor);

header('Location: ' . APP_URL . '/vendedor/index.php');
exit;
