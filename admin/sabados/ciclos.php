<?php
/** Historial de ciclos de sabado: estado, pozo y ganadores de cada sabado. */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\ParametroService;
use Polla\Services\PozoService;

requireLogin();

$db     = getPDO();
$ciclos = new CicloService($db);

// Abre el del sabado si todavia no existe, para que la lista nunca
// aparezca vacia en una instalacion nueva.
$ciclos->obtenerCicloActivo(CicloService::TIPO_SABADO);
$lista = $ciclos->listar(52, CicloService::TIPO_SABADO);

$premioBase = (new ParametroService($db))->premioBaseSabado();

$pageTitle  = 'Sábados · ' . APP_NAME;
$navSeccion = 'sabados';
require __DIR__ . '/../../includes/head.php';
require __DIR__ . '/../../includes/topbar.php';
?>

<main class="pantalla">

    <a href="<?= APP_URL ?>/admin/sabados/index.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left"></i> Tablero de sábados
    </a>

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <h1 class="pantalla__titulo">Sábados</h1>
    <p class="pantalla__bajada">Un sábado de juego por fila, del más nuevo al más viejo</p>

    <div class="mt-3">
        <?php foreach ($lista as $ciclo): ?>
            <a class="fila" href="<?= APP_URL ?>/admin/sabados/ver.php?id=<?= (int) $ciclo['id'] ?>">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <p class="fila__titulo">
                            Sábado <?= (int) $ciclo['numero'] ?>
                            <span class="fw-normal text-secondary">
                                · <?= e(CicloService::rotulo($ciclo)) ?>
                            </span>
                        </p>
                        <p class="fila__meta">
                            <?= (int) $ciclo['jugadas_total'] ?> jugadas
                            · <?= (int) $ciclo['sorteos_total'] ?>/5 turnos
                        </p>
                        <?php if ((float) $ciclo['monto_arrastrado'] > 0): ?>
                            <p class="fila__meta">
                                <i class="bi bi-arrow-return-right"></i>
                                Arrastró <?= e(formatPesos($ciclo['monto_arrastrado'])) ?>
                                del sábado anterior
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="text-end text-nowrap">
                        <?php if ($ciclo['estado'] === CicloService::ESTADO_PROGRAMADO): ?>
                            <span class="etiqueta etiqueta--gris">
                                <i class="bi bi-clock-history"></i> Próximo sábado
                            </span>
                        <?php elseif ($ciclo['estado'] === CicloService::ESTADO_ABIERTO): ?>
                            <span class="etiqueta etiqueta--verde">Abierto</span>
                        <?php elseif ($ciclo['estado'] === CicloService::ESTADO_CON_GANADOR): ?>
                            <span class="etiqueta etiqueta--oro">
                                <i class="bi bi-trophy-fill"></i>
                                <?= (int) $ciclo['ganadores_total'] ?>
                            </span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta--gris">Sin ganador</span>
                        <?php endif; ?>

                        <?php
                        $pagado = (float) $ciclo['monto_pagado'];
                        $real   = (float) $ciclo['monto_acumulado'];
                        $mostrado = $pagado > 0
                            ? $pagado
                            : ($ciclo['estado'] === CicloService::ESTADO_ABIERTO
                                ? PozoService::montoAMostrar($real, $premioBase)
                                : $real);
                        ?>
                        <div class="cifra fw-bold mt-1">
                            <?= e(formatPesos($mostrado)) ?>
                        </div>
                        <div class="fila__meta">
                            <?= $pagado > 0 ? 'repartido' : 'en el pozo' ?>
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
