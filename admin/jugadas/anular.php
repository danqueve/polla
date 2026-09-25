<?php
/** Anula una jugada sin borrar su trazabilidad. Exclusivo del administrador. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$id        = (int) ($_POST['id'] ?? 0);
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

$db      = getPDO();
$jugadas = JugadaService::crearDesde($db);

try {
    $resultado = $jugadas->anular($id);

    $tipoResultado  = $resultado['tipo_juego'];
    $cicloResultado = (int) $resultado['ciclo_id'];
    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::JUGADA_ANULADA,
        'jugadas',
        $id,
        'usuario',
        currentUserId(),
        [
            'tipo_juego'            => $tipoResultado,
            'ciclo_id'              => $cicloResultado,
            'numeros'               => $resultado['numeros'],
            'aporte_pozo_revertido' => (float) $resultado['aporte_pozo_revertido'],
            'comision_revertida'    => (float) $resultado['comision_revertida'],
        ]
    );

    $mensaje = 'Jugada anulada.';
    if ((float) $resultado['aporte_pozo_revertido'] > 0) {
        $mensaje .= ' Se revirtió ' . formatPesos((float) $resultado['aporte_pozo_revertido']) . ' del pozo.';
    }
    setFlash('success', $mensaje);
    header('Location: ' . $destinoListado($cicloResultado, $tipoResultado, $busqueda, $pagina));
    exit;
} catch (ValidacionException $e) {
    setFlash('danger', implode("\n", $e->errores()));
    header('Location: ' . $destinoListado($volverA, $tipo, $busqueda, $pagina));
    exit;
}
