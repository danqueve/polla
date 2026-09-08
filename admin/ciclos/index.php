<?php
/** Historial de ciclos: estado, pozo y ganadores de cada semana. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;

requireLogin();

$ciclos = new CicloService(getPDO());

// Abre el de la semana si todavia no existe, para que la lista nunca
// aparezca vacia en una instalacion nueva.
$ciclos->obtenerCicloActivo();
$lista = $ciclos->listar(52);

$pageTitle  = 'Ciclos · ' . APP_NAME;
$navSeccion = 'ciclos';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Ciclos</h1>
    <p class="pantalla__bajada">Una semana de juego por fila, de la mas nueva a la mas vieja</p>

    <div class="mt-3">
        <?php foreach ($lista as $ciclo): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/ciclos/ver.php?id=<?= (int) $ciclo['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo">
                            Ciclo <?= (int) $ciclo['numero'] ?>
                            <span class="fw-normal text-secondary">
                                · <?= e(CicloService::rotulo($ciclo)) ?>
                            </span>
                        </p>
                        <p class="fila__meta">
                            <?= (int) $ciclo['jugadas_total'] ?> jugadas
                            · <?= (int) $ciclo['sorteos_total'] ?>/5 sorteos
                        </p>
                        <?php if ((float) $ciclo['monto_arrastrado'] > 0): ?>
                            <p class="fila__meta">
                                <i class="bi bi-arrow-return-right"></i>
                                Arrastro <?= e(formatPesos($ciclo['monto_arrastrado'])) ?>
                                de la semana anterior
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="text-end text-nowrap">
                        <?php if ($ciclo['estado'] === CicloService::ESTADO_ABIERTO): ?>
                            <span class="etiqueta etiqueta--verde">Abierto</span>
                        <?php elseif ($ciclo['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                            <span class="etiqueta etiqueta--oro">
                                <i class="bi bi-trophy-fill"></i>
                                <?= (int) $ciclo['ganadores_total'] ?>
                            </span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta--gris">Sin ganador</span>
                        <?php endif; ?>

                        <div class="cifra fw-bold mt-1">
                            <?= e(formatPesos(
                                (float) $ciclo['monto_pagado'] > 0
                                    ? $ciclo['monto_pagado']
                                    : $ciclo['monto_acumulado']
                            )) ?>
                        </div>
                        <div class="fila__meta">
                            <?= (float) $ciclo['monto_pagado'] > 0 ? 'repartido' : 'en el pozo' ?>
                        </div>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</main>

<?php
require __DIR__ . '/../../includes/bottom_nav.php';
require __DIR__ . '/../../includes/foot.php';
