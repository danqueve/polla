-- ============================================================
-- Sesion persistente ("recordarme") para el portal del cliente,
-- para que la PWA abra ya logueada en vez de pedir DNI cada vez.
--
-- Exclusivo de clientes: NO aplica a usuarios (admin/supervisor) ni a
-- vendedores, que siguen logueandose siempre a mano.
-- ============================================================

USE `polla_quevedo`;

CREATE TABLE `remember_tokens` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cliente_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64)     NOT NULL COMMENT 'sha256 del token; el token plano solo vive en la cookie, nunca en la base',
    `expira_en`  DATETIME     NOT NULL,
    `creado_en`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_remember_tokens_hash` (`token_hash`),
    KEY `idx_remember_tokens_cliente` (`cliente_id`, `expira_en`),
    CONSTRAINT `fk_remember_tokens_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
