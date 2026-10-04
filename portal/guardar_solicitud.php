<?php
/** Handler POST de "armar jugada". Genera la solicitud pendiente de pago. */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\FavoritaService;
use Polla\Services\SolicitudService;
use Polla\Support\ValidacionException;

requireCliente();
requirePost();

$clienteId   = (int) clienteActualId();
$gruposPost  = is_array($_POST['grupos'] ?? null) ? $_POST['grupos'] : [];
$promocionId = !empty($_POST['promocion_id']) ? (int) $_POST['promocion_id'] : null;
$tipoJuego   = array_key_exists($_POST['tipo_juego'] ?? '', CicloService::TIPOS)
    ? $_POST['tipo_juego']
    : CicloService::TIPO_SEMANAL;
$volver      = APP_URL . '/portal/jugar.php?tipo=' . $tipoJuego;

// Las dos listas se arman en la misma pasada: $quiereFavorita[$i] es el
// tilde "Guardar tambien como favorita" de la jugada $listasDeNumeros[$i],
// aunque las claves de grupos[] no sean correlativas.
$listasDeNumeros = [];
$quiereFavorita  = [];
foreach ($gruposPost as $grupo) {
    $listasDeNumeros[] = is_array($grupo['numeros'] ?? null) ? $grupo['numeros'] : [];
    $quiereFavorita[]  = is_array($grupo) && !empty($grupo['favorita']);
}

try {
    $db        = getPDO();
    $resultado = SolicitudService::crearDesde($db)->crear($clienteId, $listasDeNumeros, $promocionId, $tipoJuego);

    // La solicitud ya esta guardada. Las favoritas son un extra: pase lo
    // que pase aca, NUNCA tiene que perderse el codigo de pago que el
    // cliente acaba de generar -- por eso va aparte, con su propio
    // try/catch, y solo avisa.
    try {
        $favoritas = FavoritaService::crearDesde($db);
        $yaGuardadas = $favoritas->canonicasDelCliente($clienteId, $tipoJuego);
        $guardadas = 0;
        $problemas = [];

        foreach ($listasDeNumeros as $i => $numeros) {
            if (empty($quiereFavorita[$i])) {
                continue;
            }
            $canonica = FavoritaService::canonica($numeros);
            if (isset($yaGuardadas[$canonica])) {
                continue; // ya era favorita: es lo que queria, no hay nada que avisar
            }
            try {
                $favoritas->guardar($clienteId, $numeros, $tipoJuego);
                $yaGuardadas[$canonica] = true;
                $guardadas++;
            } catch (ValidacionException $e) {
                $problemas[] = implode(' ', $e->errores());
            }
        }

        if ($problemas) {
            setFlash('warning', 'Tu código se generó bien, pero no pudimos guardar una favorita: ' . implode(' ', $problemas));
        } elseif ($guardadas > 0) {
            setFlash('success', $guardadas === 1
                ? 'Guardamos tu jugada como favorita. La vas a encontrar en «Favoritas».'
                : 'Guardamos ' . $guardadas . ' jugadas como favoritas. Las vas a encontrar en «Favoritas».');
        }
    } catch (Throwable $e) {
        error_log('[polla] No se pudieron guardar las favoritas de la solicitud ' . $resultado['solicitud_id'] . ': ' . $e->getMessage());
    }

    flushOld();

    header('Location: ' . APP_URL . '/portal/solicitud.php?id=' . $resultado['solicitud_id']);
    exit;

} catch (ValidacionException $e) {
    setOld(['grupos' => $gruposPost]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $volver);
    exit;
}
