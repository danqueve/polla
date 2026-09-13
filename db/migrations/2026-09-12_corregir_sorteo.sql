-- ============================================================
-- Correccion de un sorteo ya cargado (numeros mal tipeados).
--
-- Rastro de auditoria: quien y cuando corrigio, aparte de
-- cargado_por/creado_en que quedan reflejando la carga original.
-- No hace falta nada mas en el esquema: SorteoService::corregir()
-- reutiliza sorteo_numeros, ciclos, pozo_ciclo, jugadas y ganadores
-- tal cual ya existen.
--
-- Correr una sola vez sobre una base que ya tenga aplicada
-- 2026-09-11_ciclo_programado.sql.
-- ============================================================

USE `iifatgdb_decena`;

ALTER TABLE `sorteos`
    ADD COLUMN `corregido_por` INT UNSIGNED NULL AFTER `cargado_por`,
    ADD COLUMN `corregido_en`  DATETIME     NULL AFTER `corregido_por`,
    ADD CONSTRAINT `fk_sorteos_corregido_por`
        FOREIGN KEY (`corregido_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
