<?php
/**
 * Fase 11 - Genera el codigo_referido para los supervisores que ya
 * existian antes de esta fase (los nuevos lo sacan solos, ver
 * UsuarioService::crear()/actualizar()).
 *
 * Correr UNA VEZ despues de aplicar
 * db/migrations/2026-09-11_fase11_vendedores_referidos.sql.
 *
 * Uso: php db/migrations/2026-09-11_generar_codigos_supervisores.php
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\UsuarioService;

$db       = getPDO();
$usuarios = new UsuarioService($db);

$supervisores = $db->query(
    "SELECT id, nombre FROM usuarios WHERE rol = 'supervisor' AND codigo_referido IS NULL"
)->fetchAll();

if (!$supervisores) {
    echo "Ningun supervisor sin codigo_referido. Nada que hacer." . PHP_EOL;
    exit;
}

foreach ($supervisores as $s) {
    $codigo = $usuarios->asignarCodigoReferidoSiFalta((int) $s['id']);
    echo $s['nombre'] . ' (#' . $s['id'] . ') -> ' . $codigo . PHP_EOL;
}

echo count($supervisores) . ' supervisor(es) actualizados.' . PHP_EOL;
