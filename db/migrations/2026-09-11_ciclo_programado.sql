-- ============================================================
-- Ciclo "programado": corte automatico de semana (o sabado) ya
-- iniciada.
--
-- Regla de negocio: en cuanto se carga el primer sorteo/turno de un
-- ciclo abierto (semanal o sabado), cualquier jugada nueva que se
-- cargue de ahi en adelante ya no se suma a ese ciclo -- pasa a un
-- ciclo "programado" de la semana (o sabado) siguiente, creado la
-- primera vez que hace falta. Cuando el ciclo abierto se cierra (con
-- ganador a mitad de secuencia, o sin ganador al final de la
-- secuencia), el programado pasa a abierto con todo lo que ya se le
-- cargo por anticipado, y se crea uno nuevo vacio para la semana/
-- sabado que sigue.
--
-- Mismo patron que uk_ciclo_tipo_abierto (Fase 10): una columna
-- generada que solo vale algo en el estado que nos interesa, con un
-- UNIQUE sobre (tipo, columna) para que el propio motor impida dos
-- programados del mismo tipo a la vez.
--
-- Correr una sola vez sobre una base que ya tenga aplicada
-- 2026-09-11_vendedor_cliente_link.sql.
-- ============================================================

USE `iifatgdb_decena`;

ALTER TABLE `ciclos`
    MODIFY COLUMN `estado` ENUM('programado','abierto','cerrado_con_ganador','cerrado_sin_ganador')
                            NOT NULL DEFAULT 'abierto',
    ADD COLUMN `programado_flag` TINYINT(1) GENERATED ALWAYS AS
                    (CASE WHEN `estado` = 'programado' THEN 1 ELSE NULL END) STORED AFTER `abierto_flag`,
    ADD UNIQUE KEY `uk_ciclo_tipo_programado` (`tipo`, `programado_flag`);
