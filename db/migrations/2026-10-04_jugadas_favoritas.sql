-- ============================================================
-- Jugadas favoritas: combinaciones que el cliente guarda para
-- volver a jugarlas sin tipear numero por numero
-- ============================================================
-- Los numeros van en UNA sola columna con forma canonica (enteros
-- ascendentes separados por coma, ej. "5,12,18,..."), no en una tabla
-- hija como jugada_numeros: nunca se cotejan numero por numero, y esa
-- forma es identica a la que devuelve
--   GROUP_CONCAT(n.numero ORDER BY n.numero)
-- para una jugada real. Con eso uk_favorita evita duplicados y saber si
-- una jugada pasada "ya esta en favoritas" es comparar dos strings.
--
-- ENGINE=InnoDB explicito: la base ya tiene tres tablas en MyISAM
-- (jugada_numeros, sorteos, sorteo_numeros) y no hay que sumar otra.
--
-- Sin FK a clientes, igual que el resto del esquema:
-- ClienteService::eliminar() borra las favoritas a mano.

CREATE TABLE IF NOT EXISTS `jugadas_favoritas` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cliente_id`  INT UNSIGNED NOT NULL,
    `tipo_juego`  ENUM('semanal','sabado') NOT NULL,
    `nombre`      VARCHAR(40)  DEFAULT NULL COMMENT 'Opcional: "Mis numeros de siempre"',
    `numeros`     VARCHAR(60)  NOT NULL COMMENT 'Canonica: enteros ascendentes separados por coma',
    `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_favorita` (`cliente_id`, `tipo_juego`, `numeros`),
    KEY `idx_favoritas_cliente` (`cliente_id`, `tipo_juego`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
