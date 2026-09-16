-- ============================================================
-- P1: Tabla de rate limiting para login
-- P8: Tabla de log de auditoria
-- ============================================================

-- ── Rate limiting ───────────────────────────────────────────
-- Guarda intentos fallidos por identificador (DNI o usuario) e IP.
-- Se limpia automaticamente: cada vez que LoginThrottle chequea,
-- borra filas viejas de paso.

CREATE TABLE IF NOT EXISTS `login_intentos` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `identificador`   VARCHAR(100)  NOT NULL COMMENT 'DNI o usuario que intento loguearse',
    `ip`              VARCHAR(45)   NOT NULL COMMENT 'IPv4 o IPv6 del visitante',
    `intentos`        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `ultimo_intento`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_login_ident_ip` (`identificador`, `ip`),
    KEY `idx_login_ultimo` (`ultimo_intento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── Log de auditoria ────────────────────────────────────────
-- Registro inmutable de acciones sensibles. No tiene FK a proposito:
-- si se borra un usuario o un cliente, el log sigue intacto.

CREATE TABLE IF NOT EXISTS `auditoria` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `accion`      VARCHAR(80)     NOT NULL COMMENT 'Clave corta: login_ok, login_fallido, sorteo_cargado, cliente_aprobado, etc.',
    `entidad`     VARCHAR(40)     DEFAULT NULL COMMENT 'Tabla afectada: clientes, sorteos, jugadas, etc.',
    `entidad_id`  INT UNSIGNED    DEFAULT NULL COMMENT 'ID del registro afectado',
    `actor_tipo`  VARCHAR(20)     DEFAULT NULL COMMENT 'usuario, cliente, vendedor, sistema',
    `actor_id`    INT UNSIGNED    DEFAULT NULL COMMENT 'ID del actor (puede ser NULL para acciones anonimas como login fallido)',
    `ip`          VARCHAR(45)     DEFAULT NULL,
    `detalle`     TEXT            DEFAULT NULL COMMENT 'JSON libre con contexto adicional',
    `creado_en`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_accion`  (`accion`),
    KEY `idx_audit_entidad` (`entidad`, `entidad_id`),
    KEY `idx_audit_actor`   (`actor_tipo`, `actor_id`),
    KEY `idx_audit_fecha`   (`creado_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
