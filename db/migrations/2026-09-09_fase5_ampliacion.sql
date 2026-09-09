-- ============================================================
-- Fase 5 - Ampliacion: autorregistro, monto configurable, carga
-- multiple de jugadas.
--
-- Correr una sola vez sobre una base que ya tenga el schema previo.
-- En una base nueva no hace falta: schema.sql ya la trae incluida.
-- ============================================================

USE `polla_quevedo`;

-- 1) clientes: estado del autorregistro y origen del alta.
-- DEFAULT 'aprobado' / 'manual' para que los clientes existentes (todos
-- dados de alta manualmente hasta ahora) sigan operando sin friccion.
ALTER TABLE `clientes`
    ADD COLUMN `estado` ENUM('pendiente','aprobado','rechazado')
        NOT NULL DEFAULT 'aprobado' AFTER `activo`,
    ADD COLUMN `origen_alta` ENUM('manual','autorregistro')
        NOT NULL DEFAULT 'manual' AFTER `estado`,
    ADD KEY `idx_clientes_estado` (`estado`, `fecha_alta`);

-- 2) jugadas: a que carga conjunta pertenece cada jugada.
-- Nullable: las jugadas ya cargadas no tienen grupo.
ALTER TABLE `jugadas`
    ADD COLUMN `grupo_compra` CHAR(36) NULL AFTER `pagada`,
    ADD KEY `idx_jugadas_grupo_compra` (`grupo_compra`);

-- 3) parametros: quien actualizo el valor por ultima vez.
ALTER TABLE `parametros`
    ADD COLUMN `actualizado_por` INT UNSIGNED NULL AFTER `descripcion`,
    ADD CONSTRAINT `fk_parametros_usuario`
        FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
