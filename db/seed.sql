-- ============================================================
-- Polla Semanal Los Quevedo - Datos iniciales
-- Correr DESPUES de schema.sql
-- ============================================================

USE `polla_quevedo`;

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Parametros del juego (editables desde el panel por el admin)
-- ------------------------------------------------------------
INSERT INTO `parametros` (`clave`, `valor`, `descripcion`) VALUES
    ('importe_jugada',    '2000', 'Costo de cada jugada, en pesos'),
    ('porcentaje_pozo',   '60',   'Porcentaje de cada jugada pagada que va al pozo'),
    ('porcentaje_gastos', '40',   'Porcentaje de cada jugada que va a gastos/ganancias'),
    ('numeros_por_jugada','10',   'Cantidad de numeros que elige el cliente'),
    ('numeros_por_sorteo','20',   'Cantidad de numeros del extracto de la Nocturna')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);


-- ------------------------------------------------------------
-- Usuario administrador inicial
--   usuario: admin
--   clave:   quevedo2026
-- >>> CAMBIAR LA CLAVE APENAS ENTRES POR PRIMERA VEZ <<<
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`usuario`, `nombre`, `password_hash`, `rol`, `activo`) VALUES
    ('admin', 'Administrador', '$2y$10$0Gbqlql7P51yQqh8.1B9ueaPHZ0ZYa6wH1H1qi56UgfYqymMBBfK2', 'admin', 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);


-- ------------------------------------------------------------
-- El primer ciclo NO se carga aca: CicloService::obtenerCicloActivo()
-- lo abre solo la primera vez que se entra al panel, con las fechas
-- del lunes a viernes de la semana en curso.
-- ------------------------------------------------------------
