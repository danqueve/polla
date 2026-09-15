<?php
/**
 * "Mis referidos" [Fase 11]: la vista del supervisor sobre sus propios
 * referidos, jugadas y saldo -- misma informacion que ve un vendedor en
 * su panel, pero dentro del panel admin porque el supervisor ya vive
 * ahi para todo lo demas. No puede liquidar (eso es exclusivo del
 * administrador, en admin/liquidaciones/).
 */
require_once __DIR__ . '/../../config/app.php';

use Polla\Services\ComisionService;

requireLogin();

$usuario    = currentUser();
$comisiones = new ComisionService(getPDO());

// Un admin no tiene codigo de referido (Fase 11 solo se lo da a
// supervisores). No se bloquea la pantalla -- simplemente no hay nada
// que mostrar.
$stmt = getPDO()->prepare('SELECT codigo_referido FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $usuario['id']]);
$codigo = $stmt->fetchColumn();

$referidos = $codigo ? $comisiones->listarReferidos('supervisor', (int) $usuario['id']) : [];
$saldo     = $codigo ? $comisiones->saldoPendiente('supervisor', (int) $usuario['id']) : 0.0;
$link      = $codigo ? APP_URL . '/registro.php?ref=' . $codigo : '';

$pageTitle        = 'Mis referidos · ' . APP_NAME;
$navSeccion       = 'referidos';
$pageSectionTitle = 'Mis Referidos';
$pageScripts      = ['copiar.js'];
$breadcrumb       = [
    ['label' => 'Mis Referidos', 'url' => '']
];

require __DIR__ . '/../../includes/admin_head.php';
require __DIR__ . '/../../includes/admin_sidebar.php';
require __DIR__ . '/../../includes/admin_topbar.php';
?>

<main class="g-content">

    <?php require __DIR__ . '/../../includes/flash.php'; ?>

    <div class="mb-4 g-animate">
        <h1 class="g-page-title">Mis Referidos</h1>
        <p class="g-page-subtitle">Tu link personal, tus referidos y tu saldo acumulado</p>
    </div>

    <?php if (!$codigo): ?>
        <div class="g-card g-animate g-animate-delay-1">
            <div class="g-card__body p-5 text-center text-secondary">
                <i class="bi bi-diagram-3 fs-1 d-block mb-3 text-muted"></i>
                Tu usuario todavía no tiene código de referido. Hablá con el administrador.
            </div>
        </div>
    <?php else: ?>

        <div class="row g-4">
            <div class="col-12 col-lg-8">

                <!-- Hero Card Saldo -->
                <div class="g-hero g-animate g-animate-delay-1">
                    <div class="g-hero__label">
                        <i class="bi bi-wallet2 me-1"></i>
                        Tu Saldo Acumulado
                    </div>
                    <div class="g-hero__amount">
                        <?= e(formatPesos($saldo)) ?>
                    </div>
                </div>

                <!-- Métricas Stats -->
                <div class="g-stat-grid g-animate g-animate-delay-2">
                    <div class="g-stat">
                        <div class="g-stat__icon g-stat__icon--blue">
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="g-stat__value"><?= count($referidos) ?></div>
                        <div class="g-stat__label">Referidos</div>
                    </div>

                    <div class="g-stat">
                        <div class="g-stat__icon g-stat__icon--purple">
                            <i class="bi bi-ticket-perforated"></i>
                        </div>
                        <div class="g-stat__value">
                            <?= array_sum(array_column($referidos, 'jugadas_total')) ?>
                        </div>
                        <div class="g-stat__label">Jugadas Generadas</div>
                    </div>
                </div>

                <!-- Lista de Referidos -->
                <div class="g-card g-list-card g-animate g-animate-delay-3">
                    <div class="g-card__header">
                        <h2 class="g-card__title">
                            <i class="bi bi-people"></i>
                            Tus Referidos
                        </h2>
                    </div>
                    <div class="g-card__body">
                        <?php if (!$referidos): ?>
                            <div class="p-5 text-center text-secondary">
                                <i class="bi bi-people fs-1 d-block mb-3 text-muted"></i>
                                Todavía no tenés referidos.
                            </div>
                        <?php else: ?>
                            <?php foreach ($referidos as $r): ?>
                                <div class="g-list-item">
                                    <div class="d-flex justify-content-between align-items-start gap-2">
                                        <div class="min-w-0">
                                            <h3 class="g-list-item__title"><?= e($r['nombre']) ?></h3>
                                            <div class="g-list-item__meta mt-1">
                                                N° <?= e($r['nro_cliente']) ?> · desde <?= e(formatFecha($r['fecha_alta'])) ?>
                                            </div>
                                        </div>
                                        <span class="g-badge <?= (int) $r['jugadas_total'] > 0 ? 'g-badge--success' : '' ?> text-nowrap" <?= (int) $r['jugadas_total'] > 0 ? '' : 'style="background:#e5e7eb;color:#4b5563"' ?>>
                                            <?= (int) $r['jugadas_total'] ?> <?= (int) $r['jugadas_total'] === 1 ? 'jugada' : 'jugadas' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="g-card g-animate g-animate-delay-2">
                    <div class="g-card__header">
                        <h3 class="g-card__title">
                            <i class="bi bi-link-45deg me-1"></i>
                            Tu Código de Referido
                        </h3>
                    </div>
                    <div class="g-card__body">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                            <span class="fs-4 fw-bold"><?= e($codigo) ?></span>
                            <button type="button" class="g-btn g-btn--outline g-btn--sm" data-copiar="<?= e($codigo) ?>">
                                <i class="bi bi-clipboard"></i> Copiar código
                            </button>
                        </div>
                        <div class="text-break small text-muted mb-3"><?= e($link) ?></div>
                        <button type="button" class="g-btn g-btn--primary w-100" data-copiar="<?= e($link) ?>">
                            <i class="bi bi-clipboard"></i> Copiar Link
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php
require __DIR__ . '/../../includes/admin_foot.php';
