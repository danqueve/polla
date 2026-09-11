-- ============================================================
-- Fase 11 - Vendedores y referidos
--
-- Correr una sola vez sobre una base que ya tenga el schema hasta
-- la Fase 9/10 (2026-09-10_horario_carga_y_juego_sabados.sql).
--
-- Los codigos de referido de los SUPERVISORES EXISTENTES no se
-- generan aca (ver db/migrations/2026-09-11_generar_codigos_supervisores.php,
-- que corre despues de esta migracion y reusa el mismo generador que
-- usa la app, con reintento ante colision).
-- ============================================================

USE `polla_quevedo`;

-- 1) vendedores (nueva). Password propia (no es el DNI como en
-- clientes): la carga el admin al crearlo, igual que usuarios.
CREATE TABLE `vendedores` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`          VARCHAR(120) NOT NULL,
    `dni`             VARCHAR(15)  NOT NULL,
    `telefono`        VARCHAR(30)      NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `codigo_referido` VARCHAR(6)   NOT NULL,
    `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
    `fecha_alta`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_vendedores_dni`    (`dni`),
    UNIQUE KEY `uk_vendedores_codigo` (`codigo_referido`),
    KEY `idx_vendedores_activo` (`activo`, `nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) usuarios: codigo_referido nullable -- solo los supervisores lo
-- usan (un admin nunca tiene). NULL no colisiona contra otro NULL en
-- un UNIQUE normal de MySQL, asi que las filas de admin conviven sin
-- problema.
ALTER TABLE `usuarios`
    ADD COLUMN `codigo_referido` VARCHAR(6) NULL AFTER `rol`,
    ADD UNIQUE KEY `uk_usuarios_codigo` (`codigo_referido`);

-- 3) clientes: quien lo refirio. Relacion POLIMORFICA (vendedor O
-- supervisor segun el tipo) -- sin FK, porque una sola columna no
-- puede apuntar a dos tablas distintas. La integridad la garantiza
-- la app (ComisionService::resolverCodigo() antes de guardar), no
-- la base.
ALTER TABLE `clientes`
    ADD COLUMN `referido_por_tipo` ENUM('vendedor','supervisor') NULL AFTER `origen_alta`,
    ADD COLUMN `referido_por_id`   INT UNSIGNED NULL AFTER `referido_por_tipo`,
    ADD KEY `idx_clientes_referido` (`referido_por_tipo`, `referido_por_id`);

-- 4) comisiones (nueva). UNIQUE(jugada_id): una jugada acredita
-- comision una sola vez -- mismo patron que ganadores.uk_ganadores_jugada
-- ("una jugada puede ganar una sola vez").
CREATE TABLE `comisiones` (
    `id`                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `referidor_tipo`      ENUM('vendedor','supervisor') NOT NULL,
    `referidor_id`        INT UNSIGNED  NOT NULL,
    `jugada_id`           INT UNSIGNED  NOT NULL,
    `monto`               DECIMAL(10,2) NOT NULL,
    `porcentaje_aplicado` DECIMAL(5,2)  NOT NULL,
    `fecha`               DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_comisiones_jugada` (`jugada_id`),
    KEY `idx_comisiones_referidor` (`referidor_tipo`, `referidor_id`, `fecha`),
    CONSTRAINT `fk_comisiones_jugada`
        FOREIGN KEY (`jugada_id`) REFERENCES `jugadas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) liquidaciones (nueva). Sin link a comisiones puntuales: el saldo
-- pendiente de un referidor se calcula como
-- SUM(comisiones.monto) - SUM(liquidaciones.monto), asi que liquidar
-- registra el saldo del momento y lo deja en $0 de ahi en mas.
CREATE TABLE `liquidaciones` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `referidor_tipo` ENUM('vendedor','supervisor') NOT NULL,
    `referidor_id`   INT UNSIGNED  NOT NULL,
    `monto`          DECIMAL(10,2) NOT NULL,
    `fecha`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `liquidado_por`  INT UNSIGNED      NULL,
    PRIMARY KEY (`id`),
    KEY `idx_liquidaciones_referidor` (`referidor_tipo`, `referidor_id`, `fecha`),
    CONSTRAINT `fk_liquidaciones_usuario`
        FOREIGN KEY (`liquidado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6) parametros: porcentaje global de comision, arranca en 0 (nadie
-- cobra nada hasta que el admin lo suba desde admin/configuracion/).
INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('comision_jugada_porcentaje', '0',
     'Porcentaje de comision para el vendedor/supervisor que refirio al cliente, sobre el importe de cada jugada confirmada')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);
