-- ============================================================
-- Fase 9/10 - Horario limite de carga + juego de sabados (5 turnos)
--
-- Correr una sola vez sobre una base que ya tenga el schema hasta
-- la Fase 7 (2026-09-09_fase7_premio_base_promociones.sql).
-- ============================================================

USE `polla_quevedo`;

-- 1) ciclos: distingo la "caja" semanal de la de sabados.
--
-- El UNIQUE sobre abierto_flag pasa a ser compuesto con `tipo`: sigue
-- garantizando que nunca haya DOS ciclos abiertos del MISMO tipo a la
-- vez, pero ahora si permite que haya un semanal Y un sabado abiertos
-- en simultaneo (('semanal',1) y ('sabado',1) son tuplas distintas).
-- Lo mismo con la numeracion: cada tipo lleva su propia secuencia de
-- `numero` (Ciclo semanal #12 y Ciclo sabado #12 pueden coexistir).
ALTER TABLE `ciclos`
    ADD COLUMN `tipo` ENUM('semanal','sabado') NOT NULL DEFAULT 'semanal' AFTER `id`,
    DROP INDEX `uk_ciclos_numero`,
    DROP INDEX `uk_ciclo_abierto`,
    ADD UNIQUE KEY `uk_ciclos_tipo_numero`  (`tipo`, `numero`),
    ADD UNIQUE KEY `uk_ciclo_tipo_abierto`  (`tipo`, `abierto_flag`),
    ADD KEY `idx_ciclos_tipo_estado`        (`tipo`, `estado`);

-- 2) sorteos: un sabado tiene 5 filas con la MISMA fecha (una por
-- turno), a diferencia del semanal que sigue teniendo una fila por
-- fecha. `turno` reemplaza a `fecha` como parte de la unicidad;
-- para el juego semanal turno vale siempre 1, asi que su
-- comportamiento actual (un sorteo por fecha) no cambia en los
-- hechos.
ALTER TABLE `sorteos`
    ADD COLUMN `turno` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `fecha`,
    DROP INDEX `uk_sorteos_fecha`,
    ADD UNIQUE KEY `uk_sorteos_fecha_turno` (`fecha`, `turno`),
    ADD KEY `idx_sorteos_ciclo_turno`       (`ciclo_id`, `turno`),
    ADD CONSTRAINT `chk_sorteos_turno_rango` CHECK (`turno` BETWEEN 1 AND 5);

-- 3) jugadas: mientras una jugada esta pendiente de pago (ciclo_id
-- NULL, Fase 6), esta es la UNICA forma de saber a que juego
-- pertenece y que importe/parametros aplicarle al confirmarla.
ALTER TABLE `jugadas`
    ADD COLUMN `tipo_juego` ENUM('semanal','sabado') NOT NULL DEFAULT 'semanal' AFTER `cliente_id`,
    ADD KEY `idx_jugadas_tipo_juego` (`tipo_juego`, `ciclo_id`);

-- 4) solicitudes: una solicitud agrupa jugadas homogeneas de un
-- mismo cliente y un mismo armado (Fase 6); necesita saber su tipo
-- para que SolicitudService::confirmar() bloquee el ciclo ABIERTO
-- DEL TIPO CORRECTO, no "el" abierto a secas.
ALTER TABLE `solicitudes`
    ADD COLUMN `tipo_juego` ENUM('semanal','sabado') NOT NULL DEFAULT 'semanal' AFTER `cliente_id`,
    ADD KEY `idx_solicitudes_tipo` (`tipo_juego`, `estado`);

-- 5) parametros: monto de jugada y premio base propios del sabado
-- (caja separada del semanal), y los dos horarios limite
-- configurables. Formato de hora: 'HH:MM' de 24hs, hora de Argentina
-- (ya fijada globalmente en config/bootstrap.php). Mismo patron que
-- toda la tabla: se lee con ParametroService, se edita desde
-- admin/configuracion/.
INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('importe_jugada_sabado', '2000',
     'Monto por jugada del juego de sabados (caja separada del semanal)'),
    ('premio_base_sabado', '25000',
     'Piso garantizado del pozo de sabados'),
    ('horario_limite_semanal', '18:00',
     'Hora limite (HH:MM, Argentina) para cargar jugadas del juego semanal de lunes a viernes. Sabados y domingos no tienen limite.'),
    ('horario_limite_sabado', '11:00',
     'Hora limite (HH:MM, Argentina) para cargar jugadas del juego de sabados, el mismo sabado.')
ON DUPLICATE KEY UPDATE `descripcion` = VALUES(`descripcion`);
