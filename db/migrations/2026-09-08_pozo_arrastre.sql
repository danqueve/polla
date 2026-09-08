-- ============================================================
-- Fase 2 - Arrastre del pozo entre ciclos
--
-- Cuando una semana cierra sin ganador, el pozo acumulado pasa
-- entero al ciclo siguiente. Esta columna guarda con cuanto
-- arranco cada ciclo, para poder reconstruir de donde salio la
-- plata sin tener que deducirlo restando jugadas.
--
-- Correr una sola vez sobre una base que ya tenga el schema de
-- la fase 1. En una base nueva no hace falta: schema.sql ya la
-- trae incluida.
-- ============================================================

USE `polla_quevedo`;

ALTER TABLE `pozo_ciclo`
    ADD COLUMN `monto_arrastrado` DECIMAL(12,2) NOT NULL DEFAULT 0.00
    AFTER `ciclo_id`;
