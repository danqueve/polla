-- ============================================================
-- Decena de Oro - Esquema de base de datos
-- Motor: MySQL 8.x / MariaDB 11.x . InnoDB . utf8mb4
-- Instalador limpio con el acumulado de las fases 1 a 6
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
DROP TABLE IF EXISTS `promociones`;
DROP TABLE IF EXISTS `solicitudes`;
DROP TABLE IF EXISTS `pozo_ciclo`;
DROP TABLE IF EXISTS `ciclos`;
DROP TABLE IF EXISTS `clientes`;
DROP TABLE IF EXISTS `parametros`;
DROP TABLE IF EXISTS `usuarios`;


-- ------------------------------------------------------------
-- usuarios  (panel administrativo: admin | supervisor)
-- Va primero: parametros y clientes tienen FK hacia esta tabla.
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
-- parametros
-- Configuracion editable por el admin (importe, % de reparto...).
-- Se guarda como clave/valor de texto y cada Service castea.
-- ------------------------------------------------------------
CREATE TABLE `parametros` (
    `clave`           VARCHAR(50)  NOT NULL,
    `valor`           VARCHAR(255) NOT NULL,
    `descripcion`     VARCHAR(255)     NULL,
    `actualizado_por` INT UNSIGNED     NULL,
    `actualizado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`clave`),
    CONSTRAINT `fk_parametros_usuario`
        FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- clientes  (login propio al portal: usuario = DNI)
-- nro_cliente con formato AAAA-NNNNNN (ej. 2026-048372):
-- anio + 6 digitos al azar. La unicidad la garantiza el UNIQUE
-- de mas abajo (uk_clientes_nro); no hay correlativo que llevar.
--
-- password_hash es siempre el hash del DNI vigente: no hay clave
-- propia ni cambio de clave. ClienteService la regenera sola cada
-- vez que el DNI se edita, asi que las dos columnas nunca se
-- desincronizan.
-- ------------------------------------------------------------
CREATE TABLE `clientes` (
    `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nro_cliente`        VARCHAR(12)  NOT NULL,
    `dni`                VARCHAR(15)  NOT NULL,
    `nombre`             VARCHAR(120) NOT NULL,
    `telefono`           VARCHAR(30)      NULL,
    `password_hash`      VARCHAR(255) NOT NULL,
    `activo`             TINYINT(1)   NOT NULL DEFAULT 1,
    `estado`             ENUM('pendiente','aprobado','rechazado')
                                      NOT NULL DEFAULT 'aprobado',
    `origen_alta`        ENUM('manual','autorregistro')
                                      NOT NULL DEFAULT 'manual',
    `fecha_alta`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `alta_por`           INT UNSIGNED     NULL,
    `ultimo_acceso`      DATETIME         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clientes_nro`  (`nro_cliente`),
    UNIQUE KEY `uk_clientes_dni`  (`dni`),
    KEY `idx_clientes_nombre`     (`nombre`),
    KEY `idx_clientes_activo`     (`activo`, `nombre`),
    KEY `idx_clientes_estado`     (`estado`, `fecha_alta`),
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
--
-- monto_piso_aplicado [FASE 7]: snapshot del premio_base vigente en
-- el momento exacto de liquidar. Queda NULL mientras el ciclo sigue
-- abierto o se cierra sin ganador (no se pago nada, no hubo piso que
-- aplicar); se completa solo cuando hay un ganador, junto con
-- monto_pagado = MAX(monto_acumulado, premio_base). El pozo que se
-- MUESTRA en un ciclo abierto (portal, dashboard) tambien aplica ese
-- MAX, pero en caliente contra el parametro vigente: no se guarda
-- nada en la base hasta que efectivamente se liquida.
-- ------------------------------------------------------------
CREATE TABLE `pozo_ciclo` (
    `ciclo_id`             INT UNSIGNED  NOT NULL,
    `monto_arrastrado`     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_acumulado`      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `monto_piso_aplicado`  DECIMAL(12,2)     NULL,
    `monto_pagado`         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `fecha_liquidacion`    DATETIME          NULL,
    PRIMARY KEY (`ciclo_id`),
    CONSTRAINT `fk_pozo_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- solicitudes  [FASE 6]
-- Agrupa las jugadas que un cliente arma en una misma sesion del
-- portal (autoservicio), con un codigo corto (numero_registro) que
-- usa para identificarse al pagar. El staff la busca por ese codigo,
-- la confirma o la rechaza; ver jugadas.solicitud_id mas abajo.
-- ------------------------------------------------------------
CREATE TABLE `solicitudes` (
    `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cliente_id`        INT UNSIGNED  NOT NULL,
    `numero_registro`   VARCHAR(6)    NOT NULL,
    `cantidad_jugadas`  TINYINT UNSIGNED NOT NULL,
    `monto_total`       DECIMAL(12,2) NOT NULL,
    `estado`            ENUM('pendiente','confirmada','rechazada')
                                      NOT NULL DEFAULT 'pendiente',
    `fecha_creacion`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `fecha_resolucion`  DATETIME          NULL,
    `resuelto_por`      INT UNSIGNED      NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_solicitudes_numero` (`numero_registro`),
    KEY `idx_solicitudes_cliente` (`cliente_id`, `fecha_creacion`),
    KEY `idx_solicitudes_estado`  (`estado`, `fecha_creacion`),
    CONSTRAINT `fk_solicitudes_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_solicitudes_resuelto_por`
        FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- promociones  [FASE 7]
-- Paquetes de N jugadas por un precio total con descuento.
--
-- cantidad_si_activa es el mismo patron que ciclos.abierto_flag: una
-- columna generada que vale cantidad_jugadas solo si activa=1 y NULL
-- en cualquier otro caso. Como los NULL no colisionan en un indice
-- UNIQUE, el motor garantiza que nunca haya dos promociones ACTIVAS
-- con la misma cantidad a la vez; promociones viejas desactivadas
-- con esa misma cantidad no molestan.
-- ------------------------------------------------------------
CREATE TABLE `promociones` (
    `id`                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `cantidad_jugadas`   TINYINT UNSIGNED NOT NULL,
    `precio_total`       DECIMAL(12,2)    NOT NULL,
    `activa`             TINYINT(1)       NOT NULL DEFAULT 1,
    `fecha_creacion`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `actualizado_por`    INT UNSIGNED         NULL,
    `cantidad_si_activa` TINYINT UNSIGNED GENERATED ALWAYS AS
                         (CASE WHEN `activa` = 1 THEN `cantidad_jugadas` ELSE NULL END) STORED,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_promociones_cantidad_activa` (`cantidad_si_activa`),
    KEY `idx_promociones_activa` (`activa`, `cantidad_jugadas`),
    CONSTRAINT `fk_promociones_usuario`
        FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- jugadas
-- El reparto 60/40 se congela en la fila: si el admin cambia los
-- porcentajes mas adelante, las jugadas viejas conservan el suyo.
--
-- ciclo_id admite NULL [FASE 6]: una jugada armada por el cliente
-- (origen_carga='cliente') nace sin ciclo y con estado_pago =
-- 'pendiente_pago'; recien se le asigna el ciclo abierto en ESE
-- momento cuando el staff confirma el pago (SolicitudService), y
-- ahi pasa a sumar al pozo y a ser candidata al cotejo. Mientras
-- ciclo_id es NULL, el resto del sistema la ignora solo: el cotejo
-- y casi todas las consultas filtran por ciclo_id.
--
-- OJO: estado_pago (pendiente_pago | confirmada | rechazada) NO es
-- lo mismo que `estado` (activa | ganadora | perdedora | anulada):
-- este ultimo es el veredicto del cotejo de la Fase 2 y SorteoService
-- lo sigue leyendo tal cual. Son dos circuitos distintos a proposito.
-- ------------------------------------------------------------
CREATE TABLE `jugadas` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cliente_id`     INT UNSIGNED  NOT NULL,
    `ciclo_id`       INT UNSIGNED      NULL,
    `importe`        DECIMAL(10,2) NOT NULL,
    `aporte_pozo`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `aporte_gastos`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `pagada`         TINYINT(1)    NOT NULL DEFAULT 1,
    `estado_pago`    ENUM('pendiente_pago','confirmada','rechazada')
                                   NOT NULL DEFAULT 'confirmada',
    `origen_carga`   ENUM('staff','cliente')
                                   NOT NULL DEFAULT 'staff',
    `solicitud_id`   INT UNSIGNED      NULL,
    `grupo_compra`   CHAR(36)          NULL,
    `promocion_id`   INT UNSIGNED      NULL,
    `estado`         ENUM('activa','ganadora','perdedora','anulada')
                                   NOT NULL DEFAULT 'activa',
    `fecha_carga`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cargado_por`    INT UNSIGNED      NULL,
    PRIMARY KEY (`id`),
    KEY `idx_jugadas_ciclo_estado`   (`ciclo_id`, `estado`),
    KEY `idx_jugadas_cliente`        (`cliente_id`, `fecha_carga`),
    KEY `idx_jugadas_cargado_por`    (`cargado_por`, `fecha_carga`),
    KEY `idx_jugadas_fecha`          (`fecha_carga`),
    KEY `idx_jugadas_grupo_compra`   (`grupo_compra`),
    KEY `idx_jugadas_solicitud`      (`solicitud_id`),
    KEY `idx_jugadas_estado_pago`    (`estado_pago`, `fecha_carga`),
    KEY `idx_jugadas_promocion`      (`promocion_id`),
    CONSTRAINT `fk_jugadas_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_ciclo`
        FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_usuario`
        FOREIGN KEY (`cargado_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_solicitud`
        FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_jugadas_promocion`
        FOREIGN KEY (`promocion_id`) REFERENCES `promociones` (`id`)
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
