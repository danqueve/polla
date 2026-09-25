-- ============================================================
-- Preflight de integridad: jugada_numeros -> jugadas
--
-- Esta migración es deliberadamente SOLO de lectura. La instalación
-- histórica puede tener jugada_numeros en MyISAM y, si alguna eliminación
-- vieja dejó huérfanos, convertirla a InnoDB y agregar una FK fallaría o
-- tentaría a limpiar registros sin revisar. El código de negocio elimina
-- explícitamente los hijos dentro de su transacción, por lo que no depende
-- de esta conversión para operar de forma segura.
--
-- Ejecutar con la base destino ya seleccionada y revisar los resultados antes
-- de aplicar el endurecimiento opcional documentado al final. No usar
-- FOREIGN_KEY_CHECKS=0 ni borrar huérfanos automáticamente.
-- ============================================================

-- Debe existir exactamente una fila y ENGINE debe ser InnoDB para que una
-- FK pueda proteger los números de cada jugada.
SELECT TABLE_NAME, ENGINE
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'jugada_numeros';

-- Debe devolver 0. Si devuelve otro valor, detenerse: esos registros deben
-- investigarse y resolverse con trazabilidad antes de crear la FK.
SELECT COUNT(*) AS jugada_numeros_huerfanos
  FROM jugada_numeros n
  LEFT JOIN jugadas j ON j.id = n.jugada_id
 WHERE j.id IS NULL;

-- Informa si la FK ya está instalada, para no intentar crearla dos veces.
SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
  FROM information_schema.KEY_COLUMN_USAGE
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME = 'jugada_numeros'
   AND COLUMN_NAME = 'jugada_id'
   AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Endurecimiento opcional, SOLO después de que el chequeo de huérfanos sea
-- 0 y durante una ventana de mantenimiento (ALTER TABLE puede reconstruir y
-- bloquear la tabla). Se deja comentado para que esta migración no modifique
-- datos ni esquema sin esa verificación explícita. Hasta convertirla a
-- InnoDB, las ediciones de números no comparten el rollback de las tablas
-- transaccionales, aunque el código siga reemplazando las filas explícitamente.
--
-- ALTER TABLE jugada_numeros ENGINE=InnoDB;
-- ALTER TABLE jugada_numeros
--     ADD CONSTRAINT fk_jugada_numeros_jugada
--     FOREIGN KEY (jugada_id) REFERENCES jugadas (id)
--     ON DELETE CASCADE ON UPDATE CASCADE;
