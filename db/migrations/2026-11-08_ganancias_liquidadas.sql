-- ============================================================
-- Ganancias liquidadas: retiros del 40% de cada ciclo
-- ============================================================
-- De cada jugada, el porcentaje de gastos (hoy 40%) es lo que le queda a
-- la empresa; ya se guarda jugada por jugada en jugadas.aporte_gastos.
-- Esta tabla registra cuando ese dinero se RETIRA ("Liquidar" en
-- admin/ganancias/): una fila por retiro, con quien lo hizo, cuando, y
-- una nota opcional ("transferido a la cuenta X").
--
-- Funciona como libro mayor, igual que `liquidaciones` con las
-- comisiones: el pendiente de un ciclo es su 40% actual MENOS lo ya
-- liquidado (sin contar las anuladas). Si un ciclo cambia despues de
-- liquidado -- por ejemplo una correccion de sorteo que lo reabre --, la
-- diferencia aparece sola como pendiente, sin un numero viejo congelado.
--
-- Anular no borra la fila: se completa anulada_en/anulada_por, para que
-- quede el rastro de quien hizo que.
--
-- ENGINE=InnoDB explicito: GananciaService::liquidar() bloquea la fila del
-- ciclo (FOR UPDATE) para que dos liquidaciones simultaneas del mismo
-- ciclo no se dupliquen, y eso necesita transacciones de verdad.
--
-- Sin FK a ciclos/usuarios, igual que el resto del esquema: si se borra
-- un usuario, el registro de que liquido sigue intacto.

CREATE TABLE IF NOT EXISTS `ganancias_liquidadas` (
    `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `ciclo_id`       INT UNSIGNED  NOT NULL,
    `monto`          DECIMAL(12,2) NOT NULL COMMENT 'Lo retirado en esta liquidacion',
    `nota`           VARCHAR(200)  DEFAULT NULL COMMENT 'Opcional: "transferido a la cuenta X"',
    `liquidado_por`  INT UNSIGNED  DEFAULT NULL COMMENT 'usuarios.id',
    `fecha`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `anulada_en`     DATETIME      DEFAULT NULL COMMENT 'NULL = vigente',
    `anulada_por`    INT UNSIGNED  DEFAULT NULL COMMENT 'usuarios.id',
    PRIMARY KEY (`id`),
    KEY `idx_ganancias_ciclo` (`ciclo_id`, `anulada_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
