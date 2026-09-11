<?php
/**
 * Salto sin clave del panel de vendedor al portal del cliente. Todo
 * vendedor tiene una cuenta de cliente vinculada (VendedorService::
 * crear() se la resuelve o se la crea sola), asi que este es el unico
 * lugar donde se resuelve ese vinculo: nunca a partir de un id que
 * llegue por GET/POST, siempre a partir de quien ya esta autenticado
 * como vendedor en esta sesion.
 */
require_once __DIR__ . '/../config/vendedor.php';

use Polla\Services\ClienteAuthService;
use Polla\Services\ClienteService;
use Polla\Services\VendedorService;

requireVendedor();

$db       = getPDO();
$vendedor = (new VendedorService($db))->buscarPorId(vendedorActualId());

if (!$vendedor || $vendedor['cliente_id'] === null) {
    setFlash('danger', 'Todavía no tenés una cuenta de cliente vinculada para jugar. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
    header('Location: ' . APP_URL . '/vendedor/index.php');
    exit;
}

$cliente = (new ClienteService($db))->buscarPorId((int) $vendedor['cliente_id']);

if (!$cliente || (int) $cliente['activo'] !== 1) {
    setFlash('danger', 'Tu cuenta de cliente está dada de baja. Hablá con Decena de Oro al ' . CONTACTO_WHATSAPP_LEGIBLE . '.');
    header('Location: ' . APP_URL . '/vendedor/index.php');
    exit;
}

cambiarASesion('POLLA_CLIENTE');
(new ClienteAuthService($db))->abrirSesion($cliente);

header('Location: ' . APP_URL . '/portal/index.php');
exit;
