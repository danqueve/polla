<?php
/**
 * Vincula con su cliente a los vendedores que ya existian antes de esta
 * mejora (cliente_id todavia NULL). Los nuevos se vinculan solos desde
 * VendedorService::crear().
 *
 * Correr UNA VEZ despues de aplicar
 * db/migrations/2026-09-11_vendedor_cliente_link.sql.
 *
 * Uso: php db/migrations/2026-09-11_vincular_vendedores_existentes.php
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\VendedorService;

$db        = getPDO();
$vendedores = new VendedorService($db);

$pendientes = $db->query(
    'SELECT id, nombre FROM vendedores WHERE cliente_id IS NULL'
)->fetchAll();

if (!$pendientes) {
    echo "Ningun vendedor sin cliente vinculado. Nada que hacer." . PHP_EOL;
    exit;
}

foreach ($pendientes as $v) {
    $resultado = $vendedores->vincularSiFalta((int) $v['id']);
    $detalle   = $resultado['nuevo'] ? 'cliente nuevo' : 'cliente existente';
    echo $v['nombre'] . ' (#' . $v['id'] . ') -> cliente #' . $resultado['id']
        . ' N° ' . $resultado['nro_cliente'] . ' (' . $detalle . ')' . PHP_EOL;
}

echo count($pendientes) . ' vendedor(es) vinculados.' . PHP_EOL;
