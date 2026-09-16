-- ============================================================
-- Permite que un sorteo quede sin ciclo (ciclo_id NULL).
--
-- SorteoService::recotejarCiclo() puede cerrar una semana/sabado
-- mas temprano de lo que ya habia cerrado antes (por ejemplo, al
-- corregir un sorteo anterior y que el resultado nuevo complete un
-- ganador mas rapido). Los sorteos que quedan cronologicamente
-- despues del que cerro siguen existiendo, pero ya no pertenecen a
-- ningun ciclo activo: se reasignan al ciclo siguiente solo si su
-- fecha entra en su rango (el caso normal es que no entre, son
-- fechas de la semana/sabado que ya termino), y si no, quedan sin
-- ciclo -- mejor eso que colgados de uno ya cerrado y liquidado,
-- donde seguirian sumando en sorteosDelCiclo()/evaluar() y
-- contradiciendo el resultado ya pagado.
--
-- Mismo criterio que jugadas.ciclo_id, que ya es NULL-able desde
-- que existe la carga por el portal (una jugada pendiente_pago no
-- tiene ciclo todavia).
--
-- Sin USE a proposito: la base la elige quien ejecuta.
-- ============================================================

ALTER TABLE `sorteos`
    MODIFY COLUMN `ciclo_id` INT UNSIGNED NULL;
