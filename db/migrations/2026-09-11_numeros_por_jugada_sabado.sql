-- ============================================================
-- Cantidad de numeros por jugada del juego de sabados: 5 en vez de
-- los 10 del semanal (caja separada, definida desde la Fase 10).
--
-- Correr una sola vez sobre una base que ya tenga el schema hasta
-- la Fase 11 (2026-09-11_fase11_vendedores_referidos.sql).
--
-- No hace falta ALTER TABLE: la cantidad de numeros por jugada nunca
-- estuvo restringida a nivel de esquema (jugada_numeros no tiene un
-- CHECK de cantidad de filas), solo se validaba en PHP
-- (JugadaService::validarNumeros()) contra este mismo parametro.
-- ============================================================

USE `polla_quevedo`;

INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('numeros_por_jugada_sabado', '5',
     'Cantidad de numeros que elige el cliente en una jugada del juego de sabados (el semanal sigue en 10, parametro numeros_por_jugada)')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);
