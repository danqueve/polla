<?php
/** Jugadas del ciclo, con buscador por cliente. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\JugadaService;

requireLogin();

$db     = getPDO();
$ciclos = new CicloService($db);

// Sin ?ciclo se muestra el abierto; el selector permite mirar los cerrados.
// Resolver el ciclo va primero: en una base recien creada, obtenerCicloActivo()
// abre el ciclo 1, y recien despues tiene sentido armar la lista del selector.
$cicloId     = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
$ciclo       = $cicloId > 0 ? $ciclos->buscarPorId($cicloId) : $ciclos->obtenerCicloActivo();
$listaCiclos = $ciclos->listar(20);

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/jugadas/index.php');
    exit;
}

$busqueda = trim($_GET['q'] ?? '');
$jugadas  = JugadaService::crearDesde($db)->listarPorCiclo((int) $ciclo['id'], $busqueda);
$resumen  = $ciclos->resumen((int) $ciclo['id']);

$pageTitle  = 'Jugadas · ' . APP_NAME;
$navSeccion = 'jugadas';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Jugadas</h1>
    <p class="pantalla__bajada">
        <?= (int) $resumen['jugadas_total'] ?> cargadas ·
        <?= e(formatPesos($resumen['recaudado'])) ?> recaudados ·
        <?= e(formatPesos($resumen['al_pozo'])) ?> al pozo
    </p>

    <form method="get" class="mt-3">
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label" for="ciclo">Ciclo</label>
                <select class="form-select" id="ciclo" name="ciclo" onchange="this.form.submit()">
                    <?php foreach ($listaCiclos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"
                                <?= (int) $c['id'] === (int) $ciclo['id'] ? 'selected' : '' ?>>
                            Ciclo <?= (int) $c['numero'] ?> · <?= e(CicloService::rotulo($c)) ?>
                            <?= $c['estado'] === CicloService::ESTADO_ABIERTO ? '(abierto)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="q">Buscar cliente</label>
                <div class="position-relative">
                    <input type="search" class="form-control" id="q" name="q"
                           value="<?= e($busqueda) ?>"
                           placeholder="Nombre, DNI o N° de cliente"
                           style="padding-right:3rem">
                    <button type="submit" class="btn btn-link position-absolute end-0 top-0 h-100 px-3"
                            aria-label="Buscar">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <hr class="my-3">

    <?php if (!$jugadas): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
            <?= $busqueda !== ''
                ? 'No hay jugadas de ese cliente en este ciclo.'
                : 'Todavia no hay jugadas en este ciclo.' ?>
            <div class="mt-3">
                <a href="<?= APP_URL ?>/admin/jugadas/nueva.php" class="btn btn-sm btn-primary">
                    Cargar una jugada
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($jugadas as $jugada): ?>
            <article class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e($jugada['cliente_nombre']) ?></p>
                        <p class="fila__meta">
                            <span class="cifra">N° <?= e($jugada['nro_cliente']) ?></span>
                            · <?= e(formatFechaHora($jugada['fecha_carga'])) ?>
                            <?php if ($jugada['cargado_por_nombre']): ?>
                                · por <?= e($jugada['cargado_por_nombre']) ?>
                            <?php elseif ($jugada['origen_carga'] === 'cliente'): ?>
                                · <i class="bi bi-phone"></i> autoservicio
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="text-end text-nowrap">
                        <div class="cifra fw-bold"><?= e(formatPesos($jugada['importe'])) ?></div>
                        <?php if ($jugada['estado'] === 'ganadora'): ?>
                            <span class="etiqueta etiqueta--oro mt-1">Ganadora</span>
                        <?php elseif ((int) $jugada['pagada'] === 1): ?>
                            <span class="etiqueta etiqueta--verde mt-1">Pagada</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta--roja mt-1">Impaga</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bolillas mt-2">
                    <?php foreach ($jugada['numeros'] as $numero): ?>
                        <span class="bolilla"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if (isAdmin()): ?>
                    <form method="post" action="<?= APP_URL ?>/admin/jugadas/eliminar.php"
                          class="mt-2 text-end"
                          onsubmit="return confirm('Borrar la jugada de <?= e(addslashes($jugada['cliente_nombre'])) ?>? Se le descuenta el aporte al pozo.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="id" value="<?= (int) $jugada['id'] ?>">
                        <input type="hidden" name="volver_a" value="<?= (int) $ciclo['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash"></i> Borrar
                        </button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
