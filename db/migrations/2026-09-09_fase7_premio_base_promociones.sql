-- ============================================================
-- Fase 7 - Premio base garantizado y promociones de paquete.
--
-- Correr una sola vez sobre una base que ya tenga el schema previo.
-- En una base nueva no hace falta: schema.sql ya la trae incluida.
-- ============================================================

USE `polla_quevedo`;

-- 1) parametros: nada nuevo que crear, una fila mas. Mismo patron que
-- importe_jugada: se lee con ParametroService, se edita con
-- ConfiguracionService desde admin/configuracion/.
INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('premio_base', '25000',
     'Piso garantizado del pozo: si lo acumulado no llega a este monto, la diferencia la cubre la empresa')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);

-- 2) pozo_ciclo: snapshot del piso aplicado al momento de liquidar.
--
-- `monto_acumulado` NO se renombra: ya significa "lo realmente
-- acumulado" y nunca significo otra cosa, asi que no hace falta
-- tocarlo. `monto_piso_aplicado` queda NULL mientras el ciclo sigue
-- abierto o se cierra sin ganador (no se pago nada, no hubo piso que
-- aplicar); se completa recien cuando PozoService::liquidar() calcula
-- monto_pagado = MAX(monto_acumulado, premio_base vigente).
ALTER TABLE `pozo_ciclo`
    ADD COLUMN `monto_piso_aplicado` DECIMAL(12,2) NULL AFTER `monto_acumulado`;

-- 3) Tabla nueva: paquetes promocionales.
--
-- `cantidad_si_activa` es el mismo patron que `ciclos.abierto_flag`:
-- una columna generada que vale `cantidad_jugadas` solo si `activa=1`
-- y NULL en cualquier otro caso. Como los NULL no colisionan en un
-- indice UNIQUE, el motor garantiza solo que nunca pueda haber dos
-- promociones ACTIVAS con la misma cantidad de jugadas a la vez;
-- promociones viejas desactivadas con esa misma cantidad no molestan.
CREATE TABLE `promociones` (
    `id`                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `cantidad_jugadas`   TINYINT UNSIGNED NOT NULL,
    `precio_total`       DECIMAL(12,2)    NOT NULL,
    `activa`             TINYINT(1)       NOT NULL DEFAULT 1,
    `fecha_creacion`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_por`    INT UNSIGNED         NULL,
    `cantidad_si_activa` TINYINT UNSIGNED GENERATED ALWAYS AS
                         (CASE WHEN `activa` = 1 THEN `cantidad_jugadas` ELSE NULL END) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_promociones_cantidad_activa` (`cantidad_si_activa`),
    KEY `idx_promociones_activa` (`activa`, `cantidad_jugadas`),
    CONSTRAINT `fk_promociones_usuario`
        FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) jugadas: que promocion (si hubo) se le aplico a esta jugada.
ALTER TABLE `jugadas`
    ADD COLUMN `promocion_id` INT UNSIGNED NULL AFTER `grupo_compra`,
    ADD KEY `idx_jugadas_promocion` (`promocion_id`),
    ADD CONSTRAINT `fk_jugadas_promocion`
        FOREIGN KEY (`promocion_id`) REFERENCES `promociones` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
