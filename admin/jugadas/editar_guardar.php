<?php
/** Guarda la corrección de números de una jugada. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id        = (int) ($_POST['id'] ?? 0);
$numeros   = is_array($_POST['numeros'] ?? null) ? $_POST['numeros'] : [];
$volverA   = (int) ($_POST['volver_a'] ?? 0);
$tipoCrudo = $_POST['tipo'] ?? null;
$tipo      = is_string($tipoCrudo) && array_key_exists($tipoCrudo, CicloService::TIPOS)
    ? $tipoCrudo
    : CicloService::TIPO_SEMANAL;
$busqueda  = trim((string) ($_POST['q'] ?? ''));
$pagina    = max(1, (int) ($_POST['pagina'] ?? 1));

$destinoListado = static function (int $cicloId, string $tipoJuego, string $q, int $numeroPagina): string {
    $params = ['tipo' => $tipoJuego];
    if ($cicloId > 0) {
        $params['ciclo'] = $cicloId;
    }
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($numeroPagina > 1) {
        $params['pagina'] = $numeroPagina;
    }

    return APP_URL . '/admin/jugadas/index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$destinoEditar = static function (int $jugadaId, int $cicloId, string $tipoJuego, string $q, int $numeroPagina): string {
    $params = [
        'id'       => $jugadaId,
        'volver_a' => $cicloId,
        'tipo'     => $tipoJuego,
    ];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($numeroPagina > 1) {
        $params['pagina'] = $numeroPagina;
    }

    return APP_URL . '/admin/jugadas/editar.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$db       = getPDO();
$jugadas  = JugadaService::crearDesde($db);
$antes    = $id > 0 ? $jugadas->buscarPorId($id) : null;

try {
    $resultado = $jugadas->actualizarNumeros($id, $numeros);
    flushOld();

    $tipoResultado  = $resultado['tipo_juego'];
    $cicloResultado = (int) $resultado['ciclo_id'];
    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::JUGADA_EDITADA,
        'jugadas',
        $id,
        'usuario',
        currentUserId(),
        [
            'tipo_juego'     => $tipoResultado,
            'ciclo_id'       => $cicloResultado,
            'numeros_antes'  => $antes['numeros'] ?? [],
            'numeros_despues' => $resultado['numeros'],
        ]
    );

    setFlash('success', 'Los números de la jugada fueron actualizados.');
    header('Location: ' . $destinoListado($cicloResultado, $tipoResultado, $busqueda, $pagina));
    exit;
} catch (ValidacionException $e) {
    setOld(['numeros' => $numeros]);
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $destinoEditar($id, $volverA, $tipo, $busqueda, $pagina));
    exit;
}
