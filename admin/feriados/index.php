<?php
/**
 * Feriados: dias marcados "sin sorteo" por feriado provincial.
 * Exclusivo del administrador -- marcar uno puede cerrar un ciclo
 * abierto de inmediato (ver guardar.php).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\CicloService;
use Polla\Services\FeriadoService;

requireAdmin();

$db       = getPDO();
$feriados = FeriadoService::crearDesde($db);
$lista    = $feriados->listar();

$ciclos        = new CicloService($db);
$cicloSemanal  = $ciclos->buscarAbierto(CicloService::TIPO_SEMANAL);
$cicloSabado   = $ciclos->buscarAbierto(CicloService::TIPO_SABADO);

$pageTitle        = 'Feriados · ' . APP_NAME;
$navSeccion       = 'feriados';
$pageSectionTitle = 'Feriados';
$bodyClass        = 'con-accion-fija';
$breadcrumb       = [
    ['label' => 'Configuración', 'url' => APP_URL . '/admin/configuracion/index.php'],
    ['label' => 'Feriados', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 g-animate">
        <div>
            <h1 class="g-page-title">Feriados</h1>
            <p class="g-page-subtitle">
                Días sin sorteo por feriado provincial. Marcá la fecha para que
                el sistema no espere un extracto que no va a llegar.
            </p>
        </div>
    </div>

    <div class="g-alert-banner mb-4 g-animate g-animate-delay-1">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            Un feriado que cae entre lunes y viernes salta ese día de la
            semana en curso (los demás sorteos se cargan igual). Si el
            feriado cae un sábado, salta esa edición completa. En los dos
            casos, si con eso ya queda todo lo demás cargado, el ciclo
            cierra "sin ganador" apenas guardás el feriado — no hace falta
            cargar nada más para que avance.
        </div>
    </div>

    <div class="row g-4">

        <div class="col-12 col-lg-5">
            <form method="post" id="form-feriado"
                  action="<?= APP_URL ?>/admin/feriados/guardar.php" novalidate>
                <?= csrfField() ?>
                <div class="g-card mb-4 g-animate g-animate-delay-1">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-calendar-x me-1"></i>
                            Marcar Feriado
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="fecha">Fecha</label>
                            <input type="date" class="form-control form-control-lg" id="fecha" name="fecha"
                                   value="<?= e(old('fecha')) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-muted" for="motivo">Motivo</label>
                            <input type="text" class="form-control form-control-lg" id="motivo" name="motivo"
                                   value="<?= e(old('motivo')) ?>"
                                   placeholder="Ej: Feriado provincial - Día de la Tradición"
                                   maxlength="160" required>
                        </div>
                        <button type="submit" class="g-btn g-btn--primary w-100">
                            <i class="bi bi-calendar-x"></i> Marcar Feriado
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($cicloSemanal || $cicloSabado): ?>
                <div class="g-card g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-calendar-week me-1"></i>
                            Ciclos Abiertos Ahora
                        </h3>
                    </div>
                    <div class="g-card__body small text-muted">
                        <?php if ($cicloSemanal): ?>
                            <p class="mb-2">
                                Semanal <?= (int) $cicloSemanal['numero'] ?>:
                                <?= e(formatFecha($cicloSemanal['fecha_inicio'])) ?> al
                                <?= e(formatFecha($cicloSemanal['fecha_fin'])) ?>.
                            </p>
                        <?php endif; ?>
                        <?php if ($cicloSabado): ?>
                            <p class="mb-0">
                                Sábado <?= (int) $cicloSabado['numero'] ?>:
                                <?= e(formatFecha($cicloSabado['fecha_inicio'])) ?>.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-12 col-lg-7">
            <div class="g-card g-list-card g-animate g-animate-delay-2">
                <div class="g-card__header">
                    <h2 class="g-card__title">
                        <i class="bi bi-calendar-x"></i>
                        Feriados Marcados (<?= count($lista) ?>)
                    </h2>
                </div>
                <div class="g-card__body">
                    <?php if (!$lista): ?>
                        <div class="p-5 text-center text-secondary">
                            <i class="bi bi-calendar-check fs-1 d-block mb-3 text-muted"></i>
                            Todavía no hay ningún feriado marcado.
                        </div>
                    <?php else: ?>
                        <?php foreach ($lista as $f): ?>
                            <?php $esPasado = $f['fecha'] < date('Y-m-d'); ?>
                            <div class="g-list-item">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div class="min-w-0">
                                        <div class="g-list-item__title">
                                            <?= e(formatFechaDia($f['fecha'])) ?>
                                            <?php if ($esPasado): ?>
                                                <span class="g-badge ms-1" style="background:#e5e7eb;color:#4b5563">Pasado</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="g-list-item__meta mt-1">
                                            <?= e($f['motivo']) ?>
                                            <?php if ($f['creado_por_nombre']): ?>
                                                · marcado por <?= e($f['creado_por_nombre']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <form method="post" action="<?= APP_URL ?>/admin/feriados/eliminar.php"
                                          class="d-inline"
                                          onsubmit="return confirm('¿Quitar el feriado del <?= e(formatFecha($f['fecha'])) ?>? Si algún ciclo ya cerró por esto, no se reabre solo.')">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                                        <button type="submit" class="g-btn g-btn--outline g-btn--sm text-danger border-0">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</main>

<?php
flushOld();
require __DIR__ . '/../../includes/admin_foot.php';
