<?php
/**
 * Genera una simulacion para hacer una muestra del sistema (NO es un
 * smoke test): 30 clientes inventados, 4 semanas de actividad (3
 * cerradas + la actual, abierta), un ganador con el piso garantizado
 * en accion, una semana sin ganador que arrastra, una promo de
 * paquete aplicada, y clientes autorregistrados pendientes de
 * aprobar.
 *
 * Pensado para correr una sola vez sobre una base recien instalada
 * (schema.sql + seed.sql), con clientes/ciclos/jugadas vacios. A
 * diferencia de los scripts db/*.sql, este no resetea nada al
 * terminar: los datos quedan para mostrar en vivo.
 *
 * Uso: php demo/simulacion_demo.php
 *
 * Si preferis cargar el resultado ya generado en vez de correr esto
 * de nuevo (que arma nombres/numeros distintos cada vez), aplica
 * schema.sql + seed.sql + demo/datos_demo.sql en ese orden.
 */
require_once __DIR__ . '/../config/app.php';

use Polla\Services\ClienteRegistroService;
use Polla\Services\ClienteService;
use Polla\Services\JugadaService;
use Polla\Services\PromocionService;
use Polla\Services\SorteoService;

$db = getPDO();

function paso(string $t): void { echo PHP_EOL . "== $t ==" . PHP_EOL; }
function ok(string $t): void { echo "  OK   $t" . PHP_EOL; }

// ── 0. Clave del admin conocida, para poder entrar a mirar ────────
// Solo para uso local/demo: no correr esto contra una base con datos
// reales, pisa la clave del admin sin pedir confirmacion.

paso('0. Clave del admin');
$claveAdmin = 'demo2026';
$db->prepare("UPDATE usuarios SET password_hash = ? WHERE usuario = 'admin'")
   ->execute([password_hash($claveAdmin, PASSWORD_DEFAULT)]);
ok("usuario=admin · clave=$claveAdmin");

// ── 1. Reemplazar el ciclo #1 vacio por uno con fecha historica ───
// (esta vacio -0 jugadas, 0 sorteos- asi que no se pierde nada real;
// hace falta para poder construir 3 semanas de historia ANTES de
// llegar a "esta semana" usando el motor real de cierre de ciclos).

paso('1. Preparar el ciclo inicial con fecha historica');
$cicloVacio = $db->query("SELECT id, (SELECT COUNT(*) FROM jugadas WHERE ciclo_id = ciclos.id) AS n
                             FROM ciclos WHERE estado = 'abierto'")->fetch();
if ($cicloVacio && (int) $cicloVacio['n'] === 0) {
    $db->prepare('DELETE FROM pozo_ciclo WHERE ciclo_id = ?')->execute([$cicloVacio['id']]);
    $db->prepare('DELETE FROM ciclos WHERE id = ?')->execute([$cicloVacio['id']]);
    ok('ciclo abierto vacio anterior (#' . $cicloVacio['id'] . ') reemplazado');
} elseif ($cicloVacio) {
    fwrite(STDERR, "El ciclo abierto actual ya tiene jugadas, no lo toco. Revisar manualmente." . PHP_EOL);
    exit(1);
}

$lunes1 = new DateTimeImmutable('monday this week -3 weeks');
$db->prepare("INSERT INTO ciclos (numero, fecha_inicio, fecha_fin, estado) VALUES (1,?,?,'abierto')")
   ->execute([$lunes1->format('Y-m-d'), $lunes1->modify('+4 days')->format('Y-m-d')]);
$db->prepare('INSERT INTO pozo_ciclo (ciclo_id, monto_arrastrado, monto_acumulado, monto_pagado) VALUES (?,0,0,0)')
   ->execute([(int) $db->lastInsertId()]);
ok('ciclo #1 arranca el ' . $lunes1->format('d/m/Y') . ' (hace 3 semanas)');

// ── 2. Treinta clientes ────────────────────────────────────────────

paso('2. Treinta clientes');

$nombres = [
    'Ramon Quevedo','Marta Gimenez','Carlos Acuña','Silvia Romero','Jorge Paz',
    'Lucia Ibañez','Hector Molina','Patricia Soria','Daniel Ferreyra','Graciela Ponce',
    'Sergio Herrera','Monica Diaz','Ruben Toledo','Alicia Nuñez','Miguel Aguero',
    'Norma Cabrera','Oscar Juarez','Claudia Rios','Alberto Sosa','Beatriz Medina',
    'Raul Farias','Elena Vargas','Francisco Luna','Teresa Ortega','Julio Campos',
    'Susana Reinoso','Adrian Brito','Marcela Suarez','Ricardo Villalba','Liliana Pereyra',
];
$dnisUsados = [];
function dniAlAzar(array &$usados): string {
    do { $d = (string) random_int(20000000, 45999999); } while (isset($usados[$d]));
    $usados[$d] = true;
    return $d;
}
function telefonoAlAzar(): string {
    return '381 ' . random_int(400, 599) . ' ' . random_int(1000, 9999);
}

$clientes = new ClienteService($db);
$registro = ClienteRegistroService::crearDesde($db);
$adminId  = (int) $db->query("SELECT id FROM usuarios WHERE usuario = 'admin'")->fetchColumn();

$idsAprobados = [];

// 20 de alta manual (staff), aprobados al instante.
for ($i = 0; $i < 20; $i++) {
    $id = $clientes->crear([
        'dni'      => dniAlAzar($dnisUsados),
        'nombre'   => $nombres[$i],
        'telefono' => telefonoAlAzar(),
    ], $adminId);
    $idsAprobados[] = $id;
}
ok('20 clientes de alta manual (aprobados)');

// 5 autorregistrados, aprobados por el staff.
for ($i = 20; $i < 25; $i++) {
    $id = $registro->registrar([
        'dni'      => dniAlAzar($dnisUsados),
        'nombre'   => $nombres[$i],
        'telefono' => telefonoAlAzar(),
    ]);
    $registro->aprobar($id);
    $idsAprobados[] = $id;
}
ok('5 clientes autorregistrados y ya aprobados');

// 5 autorregistrados, quedan pendientes de aprobar (para la cola).
for ($i = 25; $i < 30; $i++) {
    $registro->registrar([
        'dni'      => dniAlAzar($dnisUsados),
        'nombre'   => $nombres[$i],
        'telefono' => telefonoAlAzar(),
    ]);
}
ok('5 clientes autorregistrados, quedan PENDIENTES de aprobar');

// ── 3. Promocion de paquete activa ────────────────────────────────

paso('3. Promocion de paquete');
$promos = new PromocionService($db);
$promoId = $promos->crear(['cantidad_jugadas' => '4', 'precio_total' => '7000'], $adminId);
ok('promo activa: 4 jugadas por $7.000 (precio de lista $8.000)');

// ── Utilidades de simulacion ──────────────────────────────────────

function numerosAlAzar(): array {
    $todos = range(0, 99);
    shuffle($todos);
    return array_slice($todos, 0, 10);
}

/** Arma un extracto de 20 que haga ganar exactamente a $ganadoraNumeros (o a ninguna, si es null). */
function extractoDe(?array $ganadoraNumeros, array $jugadasDelCiclo): array {
    do {
        $todos = range(0, 99);
        shuffle($todos);
        if ($ganadoraNumeros !== null) {
            $resto    = array_values(array_diff($todos, $ganadoraNumeros));
            $extracto = array_merge($ganadoraNumeros, array_slice($resto, 0, 10));
        } else {
            $extracto = array_slice($todos, 0, 20);
        }
        // Verificar que ninguna OTRA jugada activa del ciclo tambien
        // quede adentro por casualidad (para que la semana tenga
        // exactamente el resultado que la simulacion quiere contar).
        $limpio = true;
        foreach ($jugadasDelCiclo as $numeros) {
            if ($ganadoraNumeros !== null && $numeros === $ganadoraNumeros) continue;
            if (count(array_diff($numeros, $extracto)) === 0) { $limpio = false; break; }
        }
    } while (!$limpio);
    return $extracto;
}

$jugadasSvc = JugadaService::crearDesde($db);
$sorteos    = SorteoService::crearDesde($db);

/**
 * fechasDelProximoCiclo() (CicloService) siempre "atrapa" hasta la
 * semana real actual apenas la semana siguiente natural del ultimo
 * cierre queda en el pasado -es una salvaguarda contra abrir ciclos
 * viejos si el sistema estuvo sin uso, pero rompe una simulacion que
 * arma historia hacia atras. Por eso, despues de auto-abrirse cada
 * ciclo siguiente, se le corrigen las fechas por afuera del service
 * antes de cargarle nada.
 */
function fijarFechasCiclo(PDO $db, int $cicloId, DateTimeImmutable $lunes): void {
    $db->prepare('UPDATE ciclos SET fecha_inicio = ?, fecha_fin = ? WHERE id = ?')
       ->execute([$lunes->format('Y-m-d'), $lunes->modify('+4 days')->format('Y-m-d'), $cicloId]);
}

function cargarJugadas(JugadaService $svc, array $clienteIds, int $cantidad, int $cargadoPor, ?int $promocionId = null): array {
    $numerosDeLaSemana = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $cliente = $clienteIds[array_rand($clienteIds)];
        $numeros = numerosAlAzar();
        $svc->crearVarias($cliente, [array_map('num2', $numeros)], $cargadoPor, $promocionId);
        $numerosDeLaSemana[] = $numeros;
    }
    return $numerosDeLaSemana;
}

// ── 4. Semana 1 (hace 3 semanas): sin ganador, arrastra ───────────

paso('4. Semana 1 — hace 3 semanas, SIN ganador (arrastra)');
$s1 = cargarJugadas($jugadasSvc, $idsAprobados, 10, $adminId);
ok('10 jugadas cargadas');

for ($d = 0; $d < 5; $d++) {
    $fecha = $lunes1->modify("+$d days")->format('Y-m-d');
    $r = $sorteos->registrar($fecha, array_map('num2', extractoDe(null, $s1)), $adminId);
}
ok('5 sorteos cargados (lunes a viernes), ' . ($r['cerro_ciclo'] ? 'cerro ' . $r['estado_cierre'] : 'NO cerro') .
   ' · pozo arrastrado: ' . formatPesos($r['arrastre']));

$lunes2 = new DateTimeImmutable('monday this week -2 weeks');
fijarFechasCiclo($db, (int) $r['ciclo_nuevo_id'], $lunes2);
ok('ciclo #' . $r['ciclo_nuevo_id'] . ' (semana 2) recorregido al ' . $lunes2->format('d/m/Y'));

// ── 5. Semana 2 (hace 2 semanas): CON ganador, piso garantizado ───

paso('5. Semana 2 — hace 2 semanas, CON ganador (entra el piso garantizado)');
$s2 = cargarJugadas($jugadasSvc, $idsAprobados, 8, $adminId);

// Una jugada ganadora especifica, para el cliente que abre la lista.
$clienteGanador   = $idsAprobados[0];
$numerosGanadores = numerosAlAzar();
$jugadasSvc->crearVarias($clienteGanador, [array_map('num2', $numerosGanadores)], $adminId);
$s2[] = $numerosGanadores;
ok('8 jugadas mas + 1 jugada que va a ganar (' . implode(' ', array_map('num2', $numerosGanadores)) . ')');

$fechaGana = $lunes2->modify('+2 days')->format('Y-m-d'); // miercoles: corte a mitad de semana
$r = $sorteos->registrar($fechaGana, array_map('num2', extractoDe($numerosGanadores, $s2)), $adminId);
$pozoRow = $db->query("SELECT p.monto_acumulado, p.monto_piso_aplicado, p.monto_pagado
                          FROM pozo_ciclo p WHERE p.ciclo_id = " . (int) $r['ciclo_id'])->fetch();
ok('sorteo del miercoles ' . $fechaGana . ': ' . count($r['ganadores']) . ' ganador(es)');
ok('real acumulado ' . formatPesos($pozoRow['monto_acumulado']) . ' -> pagado ' . formatPesos($pozoRow['monto_pagado'])
   . ' (piso vigente ' . formatPesos($pozoRow['monto_piso_aplicado']) . ')');

$lunes3 = new DateTimeImmutable('monday this week -1 week');
fijarFechasCiclo($db, (int) $r['ciclo_nuevo_id'], $lunes3);
ok('ciclo #' . $r['ciclo_nuevo_id'] . ' (semana 3) recorregido al ' . $lunes3->format('d/m/Y'));

// ── 6. Semana 3 (hace 1 semana): con promo aplicada, sin ganador ──

paso('6. Semana 3 — hace 1 semana, con la promo aplicada, SIN ganador (arrastra)');
$s3 = cargarJugadas($jugadasSvc, $idsAprobados, 11, $adminId);

// Paquete de 4 jugadas con la promo, para un solo cliente.
$clientePromo = $idsAprobados[array_rand($idsAprobados)];
$paquete = [];
for ($i = 0; $i < 4; $i++) {
    $numeros   = numerosAlAzar();
    $paquete[] = array_map('num2', $numeros);
    $s3[]      = $numeros;
}
$jugadasSvc->crearVarias($clientePromo, $paquete, $adminId, $promoId);
ok('11 jugadas mas + 1 paquete de 4 con la promo aplicada');

for ($d = 0; $d < 5; $d++) {
    $fecha = $lunes3->modify("+$d days")->format('Y-m-d');
    $r = $sorteos->registrar($fecha, array_map('num2', extractoDe(null, $s3)), $adminId);
}
ok('5 sorteos cargados, ' . ($r['cerro_ciclo'] ? 'cerro ' . $r['estado_cierre'] : 'NO cerro') .
   ' · pozo arrastrado a la semana actual: ' . formatPesos($r['arrastre']));

// ── 7. Semana actual (abierta): mas jugadas, sin sorteos todavia ──

paso('7. Semana actual — abierta, para cargar el sorteo en vivo durante la muestra');
cargarJugadas($jugadasSvc, $idsAprobados, 10, $adminId);
ok('10 jugadas mas cargadas en el ciclo abierto (sin sorteos cargados todavia)');

// ── Resumen final ──────────────────────────────────────────────────

paso('RESUMEN');
$cli = $db->query("SELECT estado, COUNT(*) c FROM clientes GROUP BY estado")->fetchAll(PDO::FETCH_KEY_PAIR);
echo '  Clientes: ' . ($cli['aprobado'] ?? 0) . ' aprobados, ' . ($cli['pendiente'] ?? 0) . ' pendientes' . PHP_EOL;

$ciclos = $db->query("SELECT c.numero, c.fecha_inicio, c.fecha_fin, c.estado,
                              p.monto_acumulado, p.monto_pagado
                         FROM ciclos c LEFT JOIN pozo_ciclo p ON p.ciclo_id = c.id
                        ORDER BY c.numero")->fetchAll();
foreach ($ciclos as $c) {
    echo '  Ciclo ' . $c['numero'] . ' (' . $c['fecha_inicio'] . ' a ' . $c['fecha_fin'] . '): '
       . $c['estado'] . ' · acumulado ' . formatPesos($c['monto_acumulado'])
       . ($c['monto_pagado'] > 0 ? ' · pagado ' . formatPesos($c['monto_pagado']) : '') . PHP_EOL;
}

echo PHP_EOL . '  Login admin: usuario=admin · clave=' . $claveAdmin . PHP_EOL;
echo '  URL: http://localhost/polla/' . PHP_EOL;
