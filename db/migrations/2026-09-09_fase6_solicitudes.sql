-- ============================================================
-- Fase 6 - Seleccion propia de jugadas por el cliente, con
-- autorizacion de pago por staff.
--
-- Requiere tener aplicada antes 2026-09-09_fase5_ampliacion.sql
-- (agrega clientes.estado, jugadas.grupo_compra, etc.) - sin esas
-- columnas esta migracion no tiene sentido.
--
-- Correr una sola vez sobre una base que ya tenga el schema previo.
-- En una base nueva no hace falta: schema.sql ya la trae incluida.
-- ============================================================

USE `polla_quevedo`;

-- 1) Tabla nueva: agrupa las jugadas que un cliente arma en una misma
-- sesion del portal, con un codigo corto para identificarse al pagar.
CREATE TABLE `solicitudes` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cliente_id`        INT UNSIGNED  NOT NULL,
    `numero_registro`   VARCHAR(6)    NOT NULL,
    `cantidad_jugadas`  TINYINT UNSIGNED NOT NULL,
    `monto_total`       DECIMAL(12,2) NOT NULL,
    `estado`            ENUM('pendiente','confirmada','rechazada')
                                      NOT NULL DEFAULT 'pendiente',
    `fecha_creacion`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_resolucion`  DATETIME          NULL,
    `resuelto_por`      INT UNSIGNED      NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_solicitudes_numero` (`numero_registro`),
    KEY `idx_solicitudes_cliente` (`cliente_id`, `fecha_creacion`),
    KEY `idx_solicitudes_estado`  (`estado`, `fecha_creacion`),
    CONSTRAINT `fk_solicitudes_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_solicitudes_resuelto_por`
        FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) jugadas: ciclo_id pasa a admitir NULL (una jugada armada por el
-- cliente no pertenece a ningun ciclo hasta que se confirma el pago),
-- mas las columnas del circuito de autoservicio.
--
-- OJO con el nombre: la tabla ya tenia una columna `estado` desde la
-- Fase 2 (activa | ganadora | perdedora | anulada), el VEREDICTO del
-- cotejo. Lo que agrega esta fase (pendiente_pago | confirmada |
-- rechazada) es el circuito de PAGO, un concepto distinto, asi que
-- va como `estado_pago` para no pisar la columna existente ni el
-- motor de SorteoService, que sigue leyendo `estado` tal cual.
ALTER TABLE `jugadas`
    MODIFY COLUMN `ciclo_id` INT UNSIGNED NULL,
    ADD COLUMN `estado_pago` ENUM('pendiente_pago','confirmada','rechazada')
        NOT NULL DEFAULT 'confirmada' AFTER `pagada`,
    ADD COLUMN `origen_carga` ENUM('staff','cliente')
        NOT NULL DEFAULT 'staff' AFTER `estado_pago`,
    ADD COLUMN `solicitud_id` INT UNSIGNED NULL AFTER `origen_carga`,
    ADD KEY `idx_jugadas_solicitud`    (`solicitud_id`),
    ADD KEY `idx_jugadas_estado_pago` (`estado_pago`, `fecha_carga`),
    ADD CONSTRAINT `fk_jugadas_solicitud`
        FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
