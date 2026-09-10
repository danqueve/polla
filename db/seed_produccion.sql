-- ============================================================
-- Decena de Oro - Datos iniciales de PRODUCCION
--
-- A diferencia de db/seed.sql (pensado para desarrollo/demo, con
-- el usuario admin/quevedo2026 de siempre), este script deja UN
-- SOLO usuario administrador, con las credenciales reales.
--
-- Correr DESPUES de schema.sql, sobre la base de produccion.
--
--   usuario: danqueve
--   clave:   Vera0803
-- >>> CAMBIAR LA CLAVE APENAS ENTRES POR PRIMERA VEZ <<<
-- (admin/usuarios/ -> tu usuario -> cambiar clave)
-- ============================================================

USE `iifatgdb_decena`;

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
--   usuario: danqueve
--   clave:   Vera0803
-- >>> CAMBIAR LA CLAVE APENAS ENTRES POR PRIMERA VEZ <<<
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`usuario`, `nombre`, `password_hash`, `rol`, `activo`) VALUES
    ('danqueve', 'Administrador', '$2y$10$QbqROt5VQ26FIDqXo8pih.F5bYfONOETnX03hY9QUXJBEBp.hCz.W', 'admin', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);


-- ------------------------------------------------------------
-- El primer ciclo NO se carga aca: CicloService::obtenerCicloActivo()
-- lo abre solo la primera vez que se entra al panel, con las fechas
-- del lunes a viernes de la semana en curso. Lo mismo el primer ciclo
-- de sabados, con obtenerCicloActivo('sabado') al entrar a admin/sabados/.
-- ------------------------------------------------------------
