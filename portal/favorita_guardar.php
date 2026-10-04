<?php
/**
 * Handler POST: guarda una jugada favorita del cliente.
 *
 * Dos modos, segun lo que mande el formulario:
 *  - jugada_id: "Guardar como favorita" sobre una jugada ya jugada
 *    (Mis jugadas / Historial). Los numeros salen de la base y solo se
 *    acepta una jugada del propio cliente.
 *  - numeros[]: "Nueva favorita" en Mis favoritas, con casillas
 *    tipeadas y un nombre opcional.
 */
require_once __DIR__ . '/../config/portal.php';

use Polla\Services\CicloService;
use Polla\Services\FavoritaService;
use Polla\Support\ValidacionException;

requireCliente();
requirePost();

$clienteId = (int) clienteActualId();
$tipoJuego = array_key_exists($_POST['tipo_juego'] ?? '', CicloService::TIPOS)
    ? $_POST['tipo_juego']
    : CicloService::TIPO_SEMANAL;

// A que pantalla se vuelve: solo las que existen, nunca una URL que
// venga del navegador.
$volverA = in_array($_POST['volver'] ?? '', ['index', 'historial', 'favoritas'], true)
    ? $_POST['volver']
    : 'favoritas';
$destino = APP_URL . '/portal/' . $volverA . '.php?tipo=' . $tipoJuego;

$nombre = isset($_POST['nombre']) && is_string($_POST['nombre']) ? $_POST['nombre'] : null;

try {
    $favoritas = FavoritaService::crearDesde(getPDO());

    if (!empty($_POST['jugada_id'])) {
        $favoritas->guardarDesdeJugada($clienteId, (int) $_POST['jugada_id'], $nombre);
        setFlash('success', 'Guardada en tus favoritas.');
    } else {
        $numeros = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];
        $favoritas->guardar($clienteId, $numeros, $tipoJuego, $nombre);
        flushOld();
        setFlash('success', 'Favorita guardada.');
    }
} catch (ValidacionException $e) {
    if (empty($_POST['jugada_id'])) {
        // Repuebla las casillas y el nombre para no hacerlo tipear de nuevo.
        setOld([
            'fav_numeros' => is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [],
            'fav_nombre'  => $nombre,
        ]);
    }
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . $destino);
exit;
