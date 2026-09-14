-- ============================================================
-- Nuevo corte de carga semanal: de gate diario (18:00, lunes a
-- viernes) a corte unico del lunes a las 22:00. Despues de esa hora
-- ya no se bloquea la carga -- lo que se cargue va automatico para
-- la semana que viene (mismo mecanismo de "ciclo programado" de la
-- Fase 12), avisando en pantalla. Aplica por igual a clientes y a
-- staff. Sabados no se toca: sigue cortando duro a las
-- horario_limite_sabado de siempre.
--
-- Solo cambia el VALOR del parametro; el codigo (HorarioCargaService,
-- CicloService::pasoCorteSemanal()) es el que reinterpreta que
-- significa ese horario para el semanal.
-- ============================================================

USE `polla_quevedo`;

UPDATE `parametros`
   SET `valor` = '22:00',
       `descripcion` = 'Hora limite (HH:MM, Argentina) del LUNES para que una jugada semanal cuente para esta semana. Despues de esa hora (martes a domingo), lo que se cargue va automatico para la semana que viene -- no bloquea la carga, solo cambia a que ciclo se anota.'
 WHERE `clave` = 'horario_limite_semanal';
