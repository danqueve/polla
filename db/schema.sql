-- ============================================================
-- Decena de Oro - Esquema de base de datos
-- Motor: MySQL 8.x / MariaDB 11.x . InnoDB . utf8mb4
-- Fase 1 (nucleo) + tablas de fases 2 y 3 ya previstas
-- ============================================================

CREATE DATABASE IF NOT EXISTS `polla_quevedo`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `polla_quevedo`;

SET NAMES utf8mb4;

-- Orden de borrado inverso al de creacion (respeta las FK)
DROP TABLE IF EXISTS `ganadores`;
DROP TABLE IF EXISTS `sorteo_numeros`;
DROP TABLE IF EXISTS `sorteos`;
DROP TABLE IF EXISTS `jugada_numeros`;
DROP TABLE IF EXISTS `jugadas`;
DROP TABLE IF EXISTS `pozo_ciclo`;
DROP TABLE IF EXISTS `ciclos`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `parametros`;


-- ------------------------------------------------------------
-- parametros
-- Configuracion editable por el admin (importe, % de reparto...).
-- Se guarda como clave/valor de texto y cada Service castea.
-- ------------------------------------------------------------
CREATE TABLE `parametros` (
    `clave`          VARCHAR(50)  NOT NULL,
    `valor`          VARCHAR(255) NOT NULL,
    `descripcion`    VARCHAR(255)     NULL,
    `actualizado_en` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                  ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- usuarios  (panel administrativo: admin | supervisor)
-- ------------------------------------------------------------
CREATE TABLE `usuarios` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario`       VARCHAR(50)  NOT NULL,
    `nombre`        VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol`           ENUM('admin','supervisor') NOT NULL DEFAULT 'supervisor',
    `activo`        TINYINT(1)   NOT NULL DEFAULT 1,
    `ultimo_acceso` DATETIME         NULL,
    `creado_en`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_usuario` (`usuario`),
    KEY `idx_usuarios_rol_activo` (`rol`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- clientes  (login propio al portal: usuario = DNI)
-- nro_cliente con formato AAAA-NNNNNN (ej. 2026-048372):
-- anio + 6 digitos al azar. La unicidad la garantiza el UNIQUE
-- de mas abajo (uk_clientes_nro); no hay correlativo que llevar.
-- ------------------------------------------------------------
CREATE TABLE `clientes` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nro_cliente`        VARCHAR(12)  NOT NULL,
    `dni`                VARCHAR(15)  NOT NULL,
    `nombre`             VARCHAR(120) NOT NULL,
    `telefono`           VARCHAR(30)      NULL,
    `password_hash`      VARCHAR(255) NOT NULL,
    `debe_cambiar_clave` TINYINT(1)   NOT NULL DEFAULT 1,
    `activo`             TINYINT(1)   NOT NULL DEFAULT 1,
    `fecha_alta`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `alta_por`           INT UNSIGNED     NULL,
    `ultimo_acceso`      DATETIME         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clientes_nro`  (`nro_cliente`),
    UNIQUE KEY `uk_clientes_dni`  (`dni`),
    KEY `idx_clientes_nombre`     (`nombre`),
    KEY `idx_clientes_activo`     (`activo`, `nombre`),
    KEY `idx_clientes_alta_por`   (`alta_por`),
    CONSTRAINT `fk_clientes_alta_por`
        FOREIGN KEY (`alta_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ciclos  (una semana de juego, lunes a viernes)
--
-- `abierto_flag` es una columna generada que vale 1 solo cuando
-- el ciclo esta abierto y NULL en cualquier otro estado. Como los
-- NULL no colisionan en un indice UNIQUE, el motor garantiza que
-- nunca pueda haber dos ciclos abiertos a la vez.
-- ------------------------------------------------------------
CREATE TABLE `ciclos` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `numero`        INT UNSIGNED NOT NULL,
    `fecha_inicio`  DATE         NOT NULL,
    `fecha_fin`     DATE         NOT NULL,
    `estado`        ENUM('abierto','cerrado_con_ganador','cerrado_sin_ganador')
                                 NOT NULL DEFAULT 'abierto',
    `fecha_cierre`  DATETIME         NULL,
    `creado_en`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `abierto_flag`  TINYINT(1) GENERATED ALWAYS AS
                    (CASE WHEN `estado` = 'abierto' THEN 1 ELSE NULL END) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ciclos_numero`  (`numero`),
    UNIQUE KEY `uk_ciclo_abierto`  (`abierto_flag`),
    KEY `idx_ciclos_estado`        (`estado`),
    KEY `idx_ciclos_fechas`        (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- pozo_ciclo  (1 a 1 con ciclos)
--
-- monto_acumulado crece con el 60% de cada jugada pagada, y
-- arranca en monto_arrastrado: cero si el ciclo anterior se
-- cerro con ganador (el pozo se repartio entero), o el sobrante
-- del ciclo anterior si esa semana no gano nadie.
-- ------------------------------------------------------------
CREATE TABLE `pozo_ciclo` (
    `ciclo_id`           INT UNSIGNED  NOT NULL,
    `monto_arrastrado`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_acumulado`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_pagado`       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `fecha_liquidacion`  DATETIME          NULL,
    PRIMARY KEY (`ciclo_id`),
    CONSTRAINT `fk_pozo_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- jugadas
-- El reparto 60/40 se congela en la fila: si el admin cambia los
-- porcentajes mas adelante, las jugadas viejas conservan el suyo.
-- ------------------------------------------------------------
CREATE TABLE `jugadas` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cliente_id`     INT UNSIGNED  NOT NULL,
    `ciclo_id`       INT UNSIGNED  NOT NULL,
    `importe`        DECIMAL(10,2) NOT NULL,
    `aporte_pozo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `aporte_gastos`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `pagada`         TINYINT(1)    NOT NULL DEFAULT 1,
    `estado`         ENUM('activa','ganadora','perdedora','anulada')
                                   NOT NULL DEFAULT 'activa',
    `fecha_carga`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cargado_por`    INT UNSIGNED      NULL,
    PRIMARY KEY (`id`),
    KEY `idx_jugadas_ciclo_estado` (`ciclo_id`, `estado`),
    KEY `idx_jugadas_cliente`      (`cliente_id`, `fecha_carga`),
    KEY `idx_jugadas_cargado_por`  (`cargado_por`, `fecha_carga`),
    KEY `idx_jugadas_fecha`        (`fecha_carga`),
    CONSTRAINT `fk_jugadas_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_usuario`
        FOREIGN KEY (`cargado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- jugada_numeros  (10 filas por jugada)
-- El numero se guarda como entero 0..99 para poder cotejarlo con
-- el extracto en SQL; el cero a la izquierda es cosa de la vista.
-- El UNIQUE (jugada_id, numero) impide numeros repetidos.
-- ------------------------------------------------------------
CREATE TABLE `jugada_numeros` (
    `id`        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `jugada_id` INT UNSIGNED     NOT NULL,
    `numero`    TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_jugada_numero`      (`jugada_id`, `numero`),
    KEY `idx_jugada_numeros_numero`    (`numero`),
    CONSTRAINT `chk_jugada_numero_rango` CHECK (`numero` BETWEEN 0 AND 99),
    CONSTRAINT `fk_jugada_numeros_jugada`
        FOREIGN KEY (`jugada_id`) REFERENCES `jugadas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- sorteos  (extracto de la Nocturna de Tucuman)  [FASE 2]
-- Un solo sorteo por fecha.
-- ------------------------------------------------------------
CREATE TABLE `sorteos` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ciclo_id`    INT UNSIGNED NOT NULL,
    `fecha`       DATE         NOT NULL,
    `cargado_por` INT UNSIGNED     NULL,
    `creado_en`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sorteos_fecha`  (`fecha`),
    KEY `idx_sorteos_ciclo`        (`ciclo_id`, `fecha`),
    KEY `idx_sorteos_cargado_por`  (`cargado_por`),
    CONSTRAINT `fk_sorteos_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sorteos_usuario`
        FOREIGN KEY (`cargado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- sorteo_numeros  (20 filas por sorteo)  [FASE 2]
-- OJO: el extracto SI puede repetir un numero en dos posiciones,
-- asi que el UNIQUE va por (sorteo_id, posicion), no por numero.
-- ------------------------------------------------------------
CREATE TABLE `sorteo_numeros` (
    `id`        INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `sorteo_id` INT UNSIGNED     NOT NULL,
    `posicion`  TINYINT UNSIGNED NOT NULL,
    `numero`    TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_sorteo_posicion`   (`sorteo_id`, `posicion`),
    KEY `idx_sorteo_numeros_lookup`   (`sorteo_id`, `numero`),
    KEY `idx_sorteo_numeros_numero`   (`numero`),
    CONSTRAINT `chk_sorteo_numero_rango`   CHECK (`numero`   BETWEEN 0 AND 99),
    CONSTRAINT `chk_sorteo_posicion_rango` CHECK (`posicion` BETWEEN 1 AND 20),
    CONSTRAINT `fk_sorteo_numeros_sorteo`
        FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- ganadores  [FASE 2]
-- Una jugada puede ganar una sola vez: UNIQUE por jugada_id.
-- ------------------------------------------------------------
CREATE TABLE `ganadores` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `jugada_id`    INT UNSIGNED  NOT NULL,
    `sorteo_id`    INT UNSIGNED  NOT NULL,
    `ciclo_id`     INT UNSIGNED  NOT NULL,
    `monto_premio` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `creado_en`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ganadores_jugada` (`jugada_id`),
    KEY `idx_ganadores_ciclo`        (`ciclo_id`),
    KEY `idx_ganadores_sorteo`       (`sorteo_id`),
    CONSTRAINT `fk_ganadores_jugada`
        FOREIGN KEY (`jugada_id`) REFERENCES `jugadas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ganadores_sorteo`
        FOREIGN KEY (`sorteo_id`) REFERENCES `sorteos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ganadores_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
