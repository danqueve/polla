<?php
/** Historial de ciclos de sábado con diseño Gentelella. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\PozoService;

requireLogin();

$db     = getPDO();
$ciclos = new CicloService($db);

$ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);
$lista = $ciclos->listar(52, CicloService::TIPO_SABADO);

$premioBase = (new ParametroService($db))->premioBaseSabado();

$pageTitle        = 'Historial de Sábados · ' . APP_NAME;
$navSeccion       = 'sabados';
$pageSectionTitle = 'Historial de Sábados';
$breadcrumb       = [
    ['label' => 'Sábados', 'url' => APP_URL . '/admin/sabados/index.php'],
    ['label' => 'Historial', 'url' => '']
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
            <h1 class="g-page-title">Historial de Sábados</h1>
            <p class="g-page-subtitle">Registro cronológico de las ediciones especiales de los sábados</p>
        </div>

        <div>
            <a href="<?= APP_URL ?>/admin/sabados/index.php" class="g-btn g-btn--outline">
                <i class="bi bi-arrow-left"></i> Volver a Tablero Sábados
            </a>
        </div>
    </div>

    <!-- Lista de Sábados -->
    <div class="g-card g-list-card g-animate g-animate-delay-1">
        <div class="g-card__header">
            <h2 class="g-card__title">
                <i class="bi bi-star me-1"></i>
                Ediciones de Sábado (<?= count($lista) ?>)
            </h2>
        </div>

        <div class="g-card__body">
            <?php foreach ($lista as $ciclo): ?>
                <a class="g-list-item" href="<?= APP_URL ?>/admin/sabados/ver.php?id=<?= (int) $ciclo['id'] ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3 min-w-0">
                            <div class="g-stat__icon g-stat__icon--orange mb-0 flex-shrink-0" style="width:44px;height:44px">
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div class="min-w-0">
                                <h3 class="g-list-item__title">
                                    Sábado <?= (int) $ciclo['numero'] ?>
                                    <span class="text-muted fw-normal fs-7 ms-1">· <?= e(CicloService::rotulo($ciclo)) ?></span>
                                </h3>
                                <div class="g-list-item__meta mt-1">
                                    <span class="badge bg-light text-dark border me-1"><?= (int) $ciclo['jugadas_total'] ?> jugadas</span>
                                    · <span class="badge bg-light text-dark border me-1"><?= (int) $ciclo['sorteos_total'] ?>/5 turnos</span>
                                    <?php if ((float) $ciclo['monto_arrastrado'] > 0): ?>
                                        · <span class="text-primary"><i class="bi bi-arrow-return-right me-1"></i>Arrastró <?= e(formatPesos($ciclo['monto_arrastrado'])) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <div class="mb-1">
                                <?php if ($ciclo['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
                                    <span class="g-badge g-badge--info">
                                        <i class="bi bi-clock me-1"></i> Próximo Sábado
                                    </span>
                                <?php elseif ($ciclo['estado'] === CicloService::ESTADO_ABIERTO): ?>
                                    <span class="g-badge g-badge--success">Abierto</span>
                                <?php elseif ($ciclo['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                                    <span class="g-badge g-badge--warning">
                                        <i class="bi bi-trophy-fill me-1"></i> <?= (int) $ciclo['ganadores_total'] ?> <?= (int) $ciclo['ganadores_total'] === 1 ? 'Ganador' : 'Ganadores' ?>
                                    </span>
                                <?php else: ?>
                                    <span class="g-badge" style="background:#e5e7eb;color:#4b5563">Sin Ganador</span>
                                <?php endif; ?>
                            </div>

                            <?php
                            $pagado = (float) $ciclo['monto_pagado'];
                            $real   = (float) $ciclo['monto_acumulado'];
                            $mostrado = $pagado > 0
                                ? $pagado
                                : ($ciclo['estado'] === CicloService::ESTADO_ABIERTO
                                    ? PozoService::montoAMostrar($real, $premioBase)
                                    : $real);
                            ?>
                            <div class="fw-bold fs-6 text-dark"><?= e(formatPesos($mostrado)) ?></div>
                            <div class="small text-muted"><?= $pagado > 0 ? 'repartido' : 'en pozo' ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
