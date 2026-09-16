-- ============================================================
-- Decena de Oro - Datos iniciales de PRODUCCION
--
-- A diferencia de db/seed.sql (pensado para desarrollo/demo, con
-- el usuario admin/quevedo2026 de siempre), este script deja UN
-- SOLO usuario administrador, con una clave a elegir en el momento
-- (ver la nota junto al INSERT de `usuarios`, mas abajo).
--
-- Correr DESPUES de schema.sql, sobre la base de produccion (sin
-- USE: la elige quien ejecuta, ver la nota de db/schema.sql).
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Parametros del juego (editables desde el panel por el admin)
-- Incluye los del juego semanal y los del juego de sabados
-- (Fase 9/10), que ya vienen con este mismo INSERT porque el
-- ON DUPLICATE KEY UPDATE lo vuelve seguro de correr mas de una
-- vez sin duplicar filas.
-- ------------------------------------------------------------
INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('importe_jugada',        '2000',  'Costo de cada jugada semanal, en pesos'),
    ('porcentaje_pozo',       '60',    'Porcentaje de cada jugada pagada que va al pozo'),
    ('porcentaje_gastos',     '40',    'Porcentaje de cada jugada que va a gastos/ganancias'),
    ('numeros_por_jugada',    '10',    'Cantidad de numeros que elige el cliente'),
    ('numeros_por_sorteo',    '20',    'Cantidad de numeros del extracto de la Nocturna'),
    ('premio_base',           '25000', 'Piso garantizado del pozo semanal: si lo acumulado no llega a este monto, la diferencia la cubre la empresa'),
    ('importe_jugada_sabado', '2000',  'Monto por jugada del juego de sabados (caja separada del semanal)'),
    ('premio_base_sabado',    '25000', 'Piso garantizado del pozo de sabados'),
    ('horario_limite_semanal','18:00', 'Hora limite (HH:MM, Argentina) para cargar jugadas del juego semanal de lunes a viernes. Sabados y domingos no tienen limite.'),
    ('horario_limite_sabado', '11:00', 'Hora limite (HH:MM, Argentina) para cargar jugadas del juego de sabados, el mismo sabado.')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);


-- ------------------------------------------------------------
-- Usuario administrador (unico usuario de arranque)
--
-- El hash de aca abajo es un PLACEHOLDER, no sirve para loguearse.
-- Antes de correr este script, generá el hash real de tu clave:
--
--   php -r "echo password_hash('tu-clave-elegida', PASSWORD_DEFAULT), \"\n\";"
--
-- y pegalo en el INSERT en lugar del placeholder. No dejes la
-- clave en texto plano en este archivo ni en ningun comentario:
-- es un script versionado en git.
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`usuario`, `nombre`, `password_hash`, `rol`, `activo`) VALUES
    ('danqueve', 'Administrador', '$2y$10$REEMPLAZAR.CON.EL.HASH.GENERADO.ARRIBA', 'admin', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);


-- ------------------------------------------------------------
-- El primer ciclo NO se carga aca: CicloService::obtenerCicloActivo()
-- lo abre solo la primera vez que se entra al panel, con las fechas
-- del lunes a viernes de la semana en curso. Lo mismo el primer ciclo
-- de sabados, con obtenerCicloActivo('sabado') al entrar a admin/sabados/.
-- ------------------------------------------------------------
