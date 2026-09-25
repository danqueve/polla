-- ============================================================
-- Feriados: dias sin sorteo por feriado provincial
-- ============================================================
-- Marca una fecha puntual como "no hay sorteo este dia". Sin esto, un
-- feriado provincial que cae un dia habil de la Nocturna (o un sabado
-- entero) deja el ciclo esperando para siempre un sorteo que nunca va
-- a llegar: CotejoService::secuenciaCompleta() exige un sorteo por
-- cada dia del rango del ciclo, y sin uno la semana (o el sabado)
-- nunca se declara completa ni cierra "sin ganador" -- queda abierta
-- y bloquea que la siguiente arranque.
--
-- Sin FK a `usuarios` a proposito, igual que auditoria: si se borra el
-- usuario que lo marco, el feriado sigue intacto.

CREATE TABLE IF NOT EXISTS `feriados` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `fecha`       DATE         NOT NULL,
    `motivo`      VARCHAR(160) NOT NULL COMMENT 'Ej: "Feriado provincial - Dia de la Tradicion"',
    `creado_por`  INT UNSIGNED DEFAULT NULL COMMENT 'usuarios.id de quien lo marco',
    `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_feriados_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
