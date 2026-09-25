<?php
/** Jugadas del ciclo, con buscador por cliente y filtros estilo Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;
use Polla\Services\SorteoService;

requireLogin();

$db     = getPDO();
$ciclos = new CicloService($db);

$tipoSolicitado = isset($_GET['tipo']) && is_string($_GET['tipo']) && array_key_exists($_GET['tipo'], CicloService::TIPOS)
    ? $_GET['tipo']
    : CicloService::TIPO_SEMANAL;
$cicloId = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;

// Si llega un ciclo concreto, ese ciclo manda la modalidad: los IDs son
// unicos entre semana y sabado. Asi siguen funcionando los enlaces antiguos
// que solo enviaban ?ciclo= y nunca se mezcla un selector con ciclos ajenos.
if ($cicloId > 0) {
    $ciclo = $ciclos->buscarPorId($cicloId);
    $tipo  = $ciclo['tipo'] ?? $tipoSolicitado;
} else {
    $tipo  = $tipoSolicitado;
    $ciclo = $ciclos->obtenerCicloActivo($tipo);
}

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/jugadas/index.php?tipo=' . rawurlencode($tipoSolicitado));
    exit;
}

$cicloId     = (int) $ciclo['id'];
$esSabado    = $tipo === CicloService::TIPO_SABADO;
$busqueda    = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$pagina      = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina   = 25;
$jugadasSvc  = JugadaService::crearDesde($db);
$jugadasTotal = $jugadasSvc->contarPorCiclo($cicloId, $busqueda);
$totalPaginas = max(1, (int) ceil($jugadasTotal / $porPagina));
$pagina       = min($pagina, $totalPaginas);
$offset       = ($pagina - 1) * $porPagina;
$jugadas      = $jugadasSvc->listarPorCiclo($cicloId, $busqueda, $porPagina, $offset);
$resumen      = $ciclos->resumen($cicloId);
// Un sábado por semana: un año completo evita que el selector oculte
// rápidamente ciclos recientes. El historial mantiene acceso al resto.
$listaCiclos  = $ciclos->listar($esSabado ? 52 : 20, $tipo);

// La regla se vuelve a validar en los handlers y en el servicio. La vista
// solo evita ofrecer acciones que ya no son posibles despues del primer
// sorteo/turno, o sobre un ciclo terminado.
$tieneSorteos = (bool) SorteoService::crearDesde($db)->listarPorCiclo($cicloId);
$cicloEditable = in_array($ciclo['estado'], [
    CicloService::ESTADO_ABIERTO,
    CicloService::ESTADO_PROGRAMADO,
], true) && !$tieneSorteos;
$puedeGestionar = isAdmin() && $cicloEditable;

$desde = $jugadasTotal === 0 ? 0 : $offset + 1;
$hasta = min($offset + count($jugadas), $jugadasTotal);

/** Conserva el contexto del listado en enlaces internos y retornos de acciones. */
$urlListado = static function (array $cambios = []) use ($tipo, $cicloId, $busqueda, $pagina): string {
    $parametros = array_replace([
        'tipo'   => $tipo,
        'ciclo'  => $cicloId,
        'q'      => $busqueda,
        'pagina' => $pagina,
    ], $cambios);

    foreach ($parametros as $clave => $valor) {
        if ($valor === null) {
            unset($parametros[$clave]);
        }
    }

    return APP_URL . '/admin/jugadas/index.php?' . http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
};

$estadoCicloClase = match ($ciclo['estado']) {
    CicloService::ESTADO_ABIERTO => 'g-badge--success',
    CicloService::ESTADO_PROGRAMADO => 'g-badge--info',
    CicloService::ESTADO_CON_GANADOR => 'g-badge--warning',
    default => '',
};
$estadoCicloRotulo = $esSabado && $ciclo['estado'] === CicloService::ESTADO_PROGRAMADO
    ? 'Próximo sábado (en formación)'
    : (CicloService::ESTADOS[$ciclo['estado']] ?? $ciclo['estado']);

$pageTitle        = 'Jugadas · ' . APP_NAME;
$navSeccion       = 'jugadas';
$pageSectionTitle = 'Listado de Jugadas';
$breadcrumb       = [
    ['label' => 'Jugadas', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <!-- Encabezado de Página -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <div class="d-flex align-items-center gap-2">
                <span class="g-badge g-badge--blue"><?= $esSabado ? 'Sábado' : 'Ciclo' ?> <?= (int) $ciclo['numero'] ?></span>
                <span class="g-badge <?= $estadoCicloClase ?>">
                    <?= e($estadoCicloRotulo) ?>
                </span>
            </div>
            <h1 class="g-page-title mt-2"><?= $esSabado ? 'Jugadas de Sábado' : 'Jugadas Semanales' ?></h1>
            <p class="g-page-subtitle">
                <?= (int) $resumen['jugadas_total'] ?> activas ·
                <?= e(formatPesos($resumen['recaudado'])) ?> recaudados ·
                <?= e(formatPesos($resumen['al_pozo'])) ?> al pozo
            </p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= e($tipo) ?>" class="g-btn g-btn--primary">
                <i class="bi bi-plus-lg"></i> Cargar Nueva Jugada
            </a>
        </div>
    </div>

    <!-- Pestañas Semana / Sábado -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-2">
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= !$esSabado ? 'active' : '' ?>"
                       href="<?= e($urlListado([
                           'tipo' => CicloService::TIPO_SEMANAL,
                           'ciclo' => null,
                           'pagina' => 1,
                       ])) ?>">
                        <i class="bi bi-calendar-week me-1"></i> Semana
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold <?= $esSabado ? 'active' : '' ?>"
                       href="<?= e($urlListado([
                           'tipo' => CicloService::TIPO_SABADO,
                           'ciclo' => null,
                           'pagina' => 1,
                       ])) ?>">
                        <i class="bi bi-star me-1"></i> Sábado
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Filtros y Buscador -->
    <div class="g-card mb-4 g-animate g-animate-delay-1">
        <div class="g-card__body p-3">
            <form method="get">
                <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                <input type="hidden" name="pagina" value="1">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label fw-semibold small text-muted" for="ciclo">Seleccionar <?= $esSabado ? 'Sábado' : 'Ciclo' ?></label>
                        <select class="form-select" id="ciclo" name="ciclo" onchange="this.form.submit()">
                            <?php foreach ($listaCiclos as $c): ?>
                                <option value="<?= (int) $c['id'] ?>"
                                        <?= (int) $c['id'] === (int) $ciclo['id'] ? 'selected' : '' ?>>
                                    <?= $esSabado ? 'Sábado' : 'Ciclo' ?> <?= (int) $c['numero'] ?> · <?= e(CicloService::rotulo($c)) ?>
                                    <?= $c['estado'] === CicloService::ESTADO_ABIERTO ? '(abierto)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-7">
                        <label class="form-label fw-semibold small text-muted" for="q">Buscar por Cliente</label>
                        <div class="input-group">
                            <input type="search" class="form-control" id="q" name="q"
                                   value="<?= e($busqueda) ?>"
                                   placeholder="Nombre, DNI o N° de cliente">
                            <button type="submit" class="g-btn g-btn--primary">
                                <i class="bi bi-search"></i>
                                <span class="d-none d-sm-inline">Buscar</span>
                            </button>
                            <?php if ($busqueda !== ''): ?>
                                <a href="<?= e($urlListado(['q' => '', 'pagina' => 1])) ?>"
                                   class="g-btn g-btn--outline" title="Limpiar búsqueda">
                                    <i class="bi bi-x-lg"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </form>
            <div class="mt-3 pt-3 border-top d-flex justify-content-end">
                <a href="<?= APP_URL ?>/admin/solicitudes/index.php" class="g-btn g-btn--outline g-btn--sm">
                    <i class="bi bi-clock-history"></i> Gestionar solicitudes pendientes
                </a>
            </div>
        </div>
    </div>

    <?php if (isAdmin() && !$cicloEditable): ?>
        <div class="g-alert-banner g-alert-banner--info mb-4 small">
            <i class="bi bi-lock-fill"></i>
            <div>
                <?= $tieneSorteos
                    ? 'Las jugadas ya no se pueden editar ni anular porque este ciclo ya tiene un sorteo cargado.'
                    : 'Las jugadas solo se pueden editar o anular mientras el ciclo esté abierto o en formación.' ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Lista de Jugadas -->
    <div class="g-card g-list-card g-animate g-animate-delay-2">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-ticket-perforated"></i>
                <?= $esSabado ? 'Jugadas de Sábado' : 'Listado de Jugadas' ?> (<?= $jugadasTotal ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php if ($jugadasTotal > 0): ?>
                <div class="px-3 pt-3 small text-muted">
                    Mostrando <?= $desde ?>–<?= $hasta ?> de <?= $jugadasTotal ?> jugadas<?= $busqueda !== '' ? ' para la búsqueda actual' : '' ?>.
                </div>
            <?php endif; ?>
            <?php if (!$jugadas): ?>
                <div class="p-5 text-center text-secondary">
                    <i class="bi bi-ticket-perforated fs-1 d-block mb-3 text-muted"></i>
                    <p class="mb-3">
                        <?= $busqueda !== ''
                            ? 'No hay jugadas que coincidan con la búsqueda en este ciclo.'
                            : 'Todavía no hay jugadas registradas en este ciclo.' ?>
                    </p>
                    <a href="<?= APP_URL ?>/admin/jugadas/nueva.php?tipo=<?= e($tipo) ?>" class="g-btn g-btn--primary g-btn--sm">
                        <i class="bi bi-plus-lg"></i> Cargar una jugada ahora
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($jugadas as $jugada): ?>
                    <?php
                    $estadoJugada = $jugada['estado'] ?? '';
                    $puedeGestionarJugada = $puedeGestionar
                        && $estadoJugada === 'activa'
                        && (bool) ($jugada['puede_editar'] ?? false);
                    $urlEditar = APP_URL . '/admin/jugadas/editar.php?' . http_build_query([
                        'id'       => (int) $jugada['id'],
                        'volver_a' => $cicloId,
                        'tipo'     => $tipo,
                        'ciclo'    => $cicloId,
                        'q'        => $busqueda,
                        'pagina'   => $pagina,
                    ], '', '&', PHP_QUERY_RFC3986);
                    ?>
                    <div class="g-list-item">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div class="min-w-0">
                                <h3 class="g-list-item__title">
                                    <?= e($jugada['cliente_nombre']) ?>
                                    <span class="text-muted fw-normal fs-7 ms-1">(N° <?= e($jugada['nro_cliente']) ?>)</span>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <i class="bi bi-clock me-1"></i><?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                                    <?php if ($jugada['cargado_por_nombre']): ?>
                                        · <i class="bi bi-person me-1"></i>Cargado por <?= e($jugada['cargado_por_nombre']) ?>
                                    <?php elseif ($jugada['origen_carga'] === 'cliente'): ?>
                                        · <i class="bi bi-phone me-1"></i>Autoservicio portal
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="text-end text-nowrap d-flex flex-column align-items-end gap-1">
                                <div class="fw-bold fs-6 text-dark"><?= e(formatPesos($jugada['importe'])) ?></div>
                                <?php if ($estadoJugada === 'anulada'): ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Anulada</span>
                                <?php elseif ((int) $jugada['pagada'] !== 1): ?>
                                    <span class="g-badge g-badge--danger">Impaga</span>
                                <?php elseif ($estadoJugada === 'ganadora'): ?>
                                    <span class="g-badge g-badge--warning">Ganadora</span>
                                <?php elseif ($estadoJugada === 'perdedora'): ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Perdió</span>
                                <?php else: ?>
                                    <span class="g-badge g-badge--success">Activa</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Bolillas -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                            <div class="bolillas">
                                <?php foreach ($jugada['numeros'] as $numero): ?>
                                    <span class="bolilla"><?= e(num2($numero)) ?></span>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($puedeGestionarJugada): ?>
                                <div class="d-flex align-items-center gap-1">
                                    <a href="<?= e($urlEditar) ?>" class="g-btn g-btn--outline g-btn--sm"
                                       title="Editar los números de esta jugada">
                                        <i class="bi bi-pencil"></i>
                                        <span class="d-none d-sm-inline">Editar</span>
                                    </a>
                                    <form method="post" action="<?= APP_URL ?>/admin/jugadas/anular.php" class="d-inline"
                                          onsubmit="return confirm('¿Anular esta jugada? Sus números quedarán registrados para auditoría y se descontará su aporte al pozo.')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $jugada['id'] ?>">
                                        <input type="hidden" name="volver_a" value="<?= $cicloId ?>">
                                        <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
                                        <input type="hidden" name="ciclo" value="<?= $cicloId ?>">
                                        <input type="hidden" name="q" value="<?= e($busqueda) ?>">
                                        <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                        <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0"
                                                title="Anular jugada y descontar pozo">
                                            <i class="bi bi-slash-circle"></i>
                                            <span class="d-none d-sm-inline">Anular</span>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if ($totalPaginas > 1): ?>
                    <?php
                    $primeraPaginaVisible = max(1, $pagina - 2);
                    $ultimaPaginaVisible  = min($totalPaginas, $pagina + 2);
                    ?>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 border-top">
                        <span class="small text-muted">Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                        <nav aria-label="Páginas de jugadas">
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?= $pagina === 1 ? 'disabled' : '' ?>">
                                    <?php if ($pagina === 1): ?>
                                        <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?= e($urlListado(['pagina' => $pagina - 1])) ?>" aria-label="Página anterior">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    <?php endif; ?>
                                </li>
                                <?php if ($primeraPaginaVisible > 1): ?>
                                    <li class="page-item"><a class="page-link" href="<?= e($urlListado(['pagina' => 1])) ?>">1</a></li>
                                    <?php if ($primeraPaginaVisible > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php for ($numeroPagina = $primeraPaginaVisible; $numeroPagina <= $ultimaPaginaVisible; $numeroPagina++): ?>
                                    <li class="page-item <?= $numeroPagina === $pagina ? 'active' : '' ?>">
                                        <?php if ($numeroPagina === $pagina): ?>
                                            <span class="page-link" aria-current="page"><?= $numeroPagina ?></span>
                                        <?php else: ?>
                                            <a class="page-link" href="<?= e($urlListado(['pagina' => $numeroPagina])) ?>"><?= $numeroPagina ?></a>
                                        <?php endif; ?>
                                    </li>
                                <?php endfor; ?>
                                <?php if ($ultimaPaginaVisible < $totalPaginas): ?>
                                    <?php if ($ultimaPaginaVisible < $totalPaginas - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">…</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link" href="<?= e($urlListado(['pagina' => $totalPaginas])) ?>"><?= $totalPaginas ?></a></li>
                                <?php endif; ?>
                                <li class="page-item <?= $pagina === $totalPaginas ? 'disabled' : '' ?>">
                                    <?php if ($pagina === $totalPaginas): ?>
                                        <span class="page-link" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?= e($urlListado(['pagina' => $pagina + 1])) ?>" aria-label="Página siguiente">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
