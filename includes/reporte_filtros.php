<?php
/**
 * Filtros comunes de las pantallas de reportes.
 *
 * Espera definidas: $filtro, $reporte, $accion (URL del form).
 * El desplegable de "cargado por" solo se dibuja para el admin, y el
 * de clientes ya viene acotado por el alcance desde el service.
 */
$_clientes = $reporte->clientesParaFiltro();
$_usuarios = $reporte->usuariosParaFiltro();
$_ciclos   = $reporte->ciclosParaFiltro();
?>
<form method="get" action="<?= e($accion) ?>" class="filtros mb-3">
    <div class="row g-2">

        <div class="col-6">
            <label class="form-label" for="desde">Desde</label>
            <input type="date" class="form-control" id="desde" name="desde"
                   value="<?= e($filtro->desde ?? '') ?>">
        </div>
        <div class="col-6">
            <label class="form-label" for="hasta">Hasta</label>
            <input type="date" class="form-control" id="hasta" name="hasta"
                   value="<?= e($filtro->hasta ?? '') ?>">
        </div>

        <div class="col-12">
            <label class="form-label" for="ciclo">Ciclo</label>
            <select class="form-select" id="ciclo" name="ciclo">
                <option value="">Todos los ciclos</option>
                <?php foreach ($_ciclos as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"
                            <?= $filtro->cicloId === (int) $c['id'] ? 'selected' : '' ?>>
                        Ciclo <?= (int) $c['numero'] ?> · <?= e(\Polla\Services\CicloService::rotulo($c)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label" for="cliente">Cliente</label>
            <select class="form-select" id="cliente" name="cliente">
                <option value="">Todos los clientes</option>
                <?php foreach ($_clientes as $c): ?>
                    <option value="<?= (int) $c['id'] ?>"
                            <?= $filtro->clienteId === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['nombre']) ?> — N° <?= e($c['nro_cliente']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($_usuarios): ?>
            <div class="col-12">
                <label class="form-label" for="usuario">Cargado por</label>
                <select class="form-select" id="usuario" name="usuario">
                    <option value="">Cualquiera</option>
                    <?php foreach ($_usuarios as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"
                                <?= $filtro->usuarioId === (int) $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['nombre']) ?>
                            (<?= e(\Polla\Services\UsuarioService::ROLES[$u['rol']] ?? $u['rol']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-12 d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-funnel"></i> Aplicar
            </button>
            <?php if ($filtro->hayAlguno()): ?>
                <a href="<?= e($accion) ?>" class="btn btn-outline-secondary">
                    Limpiar
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>
