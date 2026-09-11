-- ============================================================
-- Vinculo cliente <-> vendedor: para que una persona que ya es
-- clienta pueda pasar a ser vendedora reusando sus datos (sin
-- duplicar el alta), y para que TODO vendedor pueda tambien jugar
-- (VendedorService::crear() le crea un cliente si todavia no era
-- uno). El vinculo se resuelve solo, por DNI, al dar de alta el
-- vendedor -- no hace falta ninguna pantalla nueva de "convertir".
--
-- NULL no colisiona contra otro NULL en un UNIQUE de MySQL (mismo
-- criterio ya usado en usuarios.codigo_referido), asi que los
-- vendedores sin cliente vinculado conviven sin problema.
-- ============================================================

USE `polla_quevedo`;

ALTER TABLE `vendedores`
    ADD COLUMN `cliente_id` INT UNSIGNED NULL AFTER `dni`,
    ADD UNIQUE KEY `uk_vendedores_cliente_id` (`cliente_id`),
    ADD CONSTRAINT `fk_vendedores_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE;
