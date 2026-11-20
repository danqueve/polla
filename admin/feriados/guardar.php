<?php
/**
 * Marca un feriado. Exclusivo del administrador.
 *
 * Un feriado manda sobre el extracto: si ese dia (incluso hoy) ya tenia
 * un sorteo cargado, se ANULA ese sorteo -- no hubo sorteo, asi que no
 * puede quedar un extracto ni lo que el cotejo hubiera sacado de el. Si
 * el sorteo estaba en un ciclo que ya cerro, se aplican las mismas
 * reglas que para corregirlo: solo se reabre si es el ultimo cerrado y
 * el siguiente no tiene sorteos propios (SorteoService::anularDeFecha()).
 * Si no se puede, no se cambia nada y el feriado no se marca.
 *
 * Marcar un feriado es en si un evento de cierre, igual que cargar un
 * sorteo: si con el la secuencia de un ciclo ya abierto (semanal o
 * sabado) queda completa, hay que recotejarlo ahora mismo -- no va a
 * existir ningun sorteo real que dispare ese cotejo despues, porque
 * justamente estamos diciendo que ese dia no hubo. Por eso, ademas de
 * guardar el feriado, este handler revisa los dos ciclos abiertos
 * (bloqueados con bloquearAbierto(), igual que SorteoService::registrar())
 * y corre CotejoService::recotejarCiclo() si la fecha marcada cae
 * dentro de alguno. Tambien cubre el ciclo que anular el sorteo acaba de
 * reabrir, que a partir de ahi es el abierto.
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\AuditoriaService;
use Polla\Services\CicloService;
use Polla\Services\CotejoService;
use Polla\Services\FeriadoService;
use Polla\Services\SorteoService;
use Polla\Support\ValidacionException;

requireAdmin();
requirePost();

$db = getPDO();

$fechaCruda = trim($_POST['fecha'] ?? '');
$motivo     = trim($_POST['motivo'] ?? '');

try {
    $usuarioId = currentUserId();
    $feriados  = FeriadoService::crearDesde($db);
    $auditoria = AuditoriaService::crearDesde($db);

    // 1) Todo lo que pueda fallar por datos mal escritos, antes de anular nada.
    $fechaValida = $feriados->validar($fechaCruda, $motivo);

    // 2) Anular el/los sorteo(s) que ese dia ya tuviera cargados. Si no se
    //    puede (ciclo cerrado que no se puede reabrir), lanza sin cambiar nada.
    $anulados = SorteoService::crearDesde($db)->anularDeFecha($fechaValida);
    foreach ($anulados as $anulado) {
        $auditoria->registrar(
            AuditoriaService::SORTEO_ANULADO_FERIADO, 'sorteos', $anulado['id'], 'usuario', $usuarioId,
            [
                'fecha'    => $fechaValida->format('Y-m-d'),
                'turno'    => $anulado['turno'],
                'ciclo_id' => $anulado['ciclo_id'],
                'ciclo'    => $anulado['ciclo'],
                'numeros'  => $anulado['numeros'],
                'reabrio'  => $anulado['reabrio'],
            ]
        );
    }

    // 3) Guardar el feriado.
    $fecha = $feriados->marcar($fechaCruda, $motivo, $usuarioId);

    $auditoria->registrar(
        AuditoriaService::FERIADO_CREADO, 'feriados', null, 'usuario', $usuarioId,
        ['fecha' => $fecha->format('Y-m-d'), 'motivo' => $motivo, 'sorteos_anulados' => count($anulados)]
    );

    // 4) Recotejar el ciclo abierto que contiene la fecha.
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
    if ($anulados) {
        $ciclosAnulados = array_values(array_unique(array_filter(array_column($anulados, 'ciclo'))));
        $mensaje .= count($anulados) === 1
            ? ' Se anuló el sorteo que ya estaba cargado ese día'
            : ' Se anularon los ' . count($anulados) . ' sorteos que ya estaban cargados ese día';
        $mensaje .= $ciclosAnulados ? ' (' . implode(', ', $ciclosAnulados) . ')' : '';
        $mensaje .= '.';
        if (in_array(true, array_column($anulados, 'reabrio'), true)) {
            $mensaje .= ' Ese ciclo ya había cerrado: se reabrió y se volvió a calcular.';
        }
    }
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
