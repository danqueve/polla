-- ============================================================
-- Clave de cliente fija = DNI, sin cambio de clave posible.
--
-- Elimina el flag que exigia cambiar la clave en el primer ingreso.
-- Ya no hace falta: la clave del portal es siempre el DNI vigente,
-- sin excepciones ni pantalla de cambio.
--
-- Correr una sola vez sobre una base que ya tenga el schema previo.
-- En una base nueva no hace falta: schema.sql ya la trae sin esta
-- columna.
--
-- Sin indices ni FK sobre esta columna: DROP directo, sin riesgo de
-- romper una constraint.
-- ============================================================

USE `polla_quevedo`;

ALTER TABLE `clientes`
    DROP COLUMN `debe_cambiar_clave`;
