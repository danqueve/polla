<?php
/**
 * Marca un feriado. Exclusivo del administrador.
 *
 * Marcar un feriado es en si un evento de cierre, igual que cargar un
 * sorteo: si con el la secuencia de un ciclo ya abierto (semanal o
 * sabado) queda completa, hay que recotejarlo ahora mismo -- no va a
 * existir ningun sorteo real que dispare ese cotejo despues, porque
 * justamente estamos diciendo que ese dia no hubo. Por eso, ademas de
 * guardar el feriado, este handler revisa los dos ciclos abiertos
 * (bloqueados con bloquearAbierto(), igual que SorteoService::registrar())
 * y corre CotejoService::recotejarCiclo() si la fecha marcada cae
 * dentro de alguno.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\CotejoService;
use Polla\Services\FeriadoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$db = getPDO();

$fechaCruda = trim($_POST['fecha'] ?? '');
$motivo     = trim($_POST['motivo'] ?? '');

try {
    $usuarioId = currentUserId();
    $fecha     = FeriadoService::crearDesde($db)->marcar($fechaCruda, $motivo, $usuarioId);

    AuditoriaService::crearDesde($db)->registrar(
        AuditoriaService::FERIADO_CREADO, 'feriados', null, 'usuario', $usuarioId,
        ['fecha' => $fecha->format('Y-m-d'), 'motivo' => $motivo]
    );

    $cerrados = [];
    $ciclos   = new CicloService($db);
    $cotejo   = CotejoService::crearDesde($db);

    foreach ([CicloService::TIPO_SEMANAL, CicloService::TIPO_SABADO] as $tipo) {
        $db->beginTransaction();
        try {
            $ciclo = $ciclos->bloquearAbierto($tipo);
            $dentroDelRango = $ciclo
                && $fecha >= new DateTimeImmutable($ciclo['fecha_inicio'])
                && $fecha <= new DateTimeImmutable($ciclo['fecha_fin']);

            if ($dentroDelRango) {
                $resultado = $cotejo->recotejarCiclo($ciclo, $tipo);
                if ($resultado['cerro_ciclo']) {
                    $cerrados[] = ($tipo === CicloService::TIPO_SABADO ? 'Sábado' : 'Semanal')
                        . ' ' . (int) $ciclo['numero'];
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    $mensaje = 'Feriado marcado: ' . $fecha->format('d/m/Y') . ' (' . $motivo . ').';
    if ($cerrados) {
        $mensaje .= ' Con esto quedó completo y cerró "sin ganador": ' . implode(', ', $cerrados) . '.';
    }
    setFlash('success', $mensaje);
} catch (ValidacionException $e) {
    setOld(['fecha' => $fechaCruda, 'motivo' => $motivo]);
    setFlash('danger', implode("\n", $e->errores()));
}

header('Location: ' . APP_URL . '/admin/feriados/index.php');
exit;
