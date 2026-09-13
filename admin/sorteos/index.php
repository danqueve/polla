<?php
/** Sorteos cargados, por ciclo. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\SorteoService;

requireLogin();

$db      = getPDO();
$ciclos  = new CicloService($db);
$sorteos = SorteoService::crearDesde($db);

// Resolver el ciclo va primero: en una base recien creada esto abre el
// ciclo 1, y recien despues tiene sentido armar la lista del selector.
$cicloId = isset($_GET['ciclo']) ? (int) $_GET['ciclo'] : 0;
$ciclo   = $cicloId > 0 ? $ciclos->buscarPorId($cicloId) : $ciclos->obtenerCicloActivo();

if (!$ciclo) {
    setFlash('danger', 'Ese ciclo no existe.');
    header('Location: ' . APP_URL . '/admin/sorteos/index.php');
    exit;
}

$listaCiclos = $ciclos->listar(20);
$lista       = $sorteos->listarPorCiclo((int) $ciclo['id']);
$abierto     = $ciclo['estado'] === CicloService::ESTADO_ABIERTO;

$pageTitle  = 'Sorteos · ' . APP_NAME;
$navSeccion = 'sorteos';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
        <div>
            <h1 class="pantalla__titulo">Sorteos</h1>
            <p class="pantalla__bajada">
                <?= count($lista) ?> de 5 cargados · semana del <?= e(CicloService::rotulo($ciclo)) ?>
            </p>
        </div>
        <?php if ($abierto): ?>
            <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="btn btn-primary btn-sm text-nowrap">
                <i class="bi bi-plus-lg"></i> Cargar
            </a>
        <?php endif; ?>
    </div>

    <form method="get" class="mb-3">
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
    </form>

    <?php if (!$lista): ?>
        <div class="vacio tarjeta">
            <i class="bi bi-dice-5" aria-hidden="true"></i>
            Todavia no hay sorteos cargados en este ciclo.
            <?php if ($abierto): ?>
                <div class="mt-3">
                    <a href="<?= APP_URL ?>/admin/sorteos/nuevo.php" class="btn btn-sm btn-primary">
                        Cargar el extracto
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($lista as $sorteo): ?>
            <article class="fila">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo"><?= e(formatFechaDia($sorteo['fecha'])) ?></p>
                        <p class="fila__meta">
                            Cargado <?= e(formatFechaHora($sorteo['creado_en'])) ?>
                            <?php if ($sorteo['cargado_por_nombre']): ?>
                                por <?= e($sorteo['cargado_por_nombre']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ((int) $sorteo['ganadores_total'] > 0): ?>
                        <span class="etiqueta etiqueta--oro text-nowrap">
                            <i class="bi bi-trophy-fill"></i>
                            <?= (int) $sorteo['ganadores_total'] ?>
                            <?= (int) $sorteo['ganadores_total'] === 1 ? 'ganador' : 'ganadores' ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="bolillas mt-2">
                    <?php foreach ($sorteo['numeros'] as $numero): ?>
                        <span class="bolilla"><?= e(num2($numero)) ?></span>
                    <?php endforeach; ?>
                </div>

                <?php if (isAdmin()): ?>
                    <div class="mt-2 text-end d-flex justify-content-end gap-2">
                        <a href="<?= APP_URL ?>/admin/sorteos/editar.php?id=<?= (int) $sorteo['id'] ?>"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil"></i> Editar
                        </a>
                        <?php if ($abierto): ?>
                            <form method="post" action="<?= APP_URL ?>/admin/sorteos/eliminar.php"
                                  onsubmit="return confirm('Borrar el sorteo del <?= e($sorteo['fecha']) ?>?')">
                                <?= csrfField() ?>
                                <input type="hidden" name="id" value="<?= (int) $sorteo['id'] ?>">
                                <input type="hidden" name="volver_a" value="<?= (int) $ciclo['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i> Borrar
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="<?= APP_URL ?>/admin/ciclos/ver.php?id=<?= (int) $ciclo['id'] ?>"
       class="btn btn-outline-secondary w-100 mt-3">
        <i class="bi bi-clipboard-data"></i> Ver el resumen del ciclo
    </a>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
