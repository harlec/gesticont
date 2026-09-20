-- ============================================================
-- GestiCont — Motor Contable, FASE 1b
-- Complemento a fase1_imputacion_diario.sql: marca qué cuentas de
-- Compras son "inventariables" (mercadería/materia prima/suministros
-- que se compran para quedar en stock), a diferencia de un gasto puro
-- (transporte, honorarios, alquileres, etc.).
--
-- Por qué hace falta: la spec pide reclasificar los gastos de Compras
-- hacia Gastos de Administración/Venta (94/95) según % configurado. Pero
-- eso solo es correcto para gastos puros — la mercadería/materia prima
-- comprada NO es un gasto del período hasta que se vende o se consume
-- (spec 2.6: Costo de Ventas = Inventario Inicial + Compras − Inventario
-- Final). Sin un módulo de Kardex/inventario final (fuera de alcance de
-- Fase 1, ya documentado así en el propio plan), esas cuentas deben
-- quedarse pendientes en la cuenta 60 en vez de reclasificarse — hacerlo
-- de todas formas daría un Costo de Ventas y una Utilidad incorrectos.
-- ============================================================

ALTER TABLE cuentas_contables
    ADD COLUMN es_inventariable TINYINT(1) NOT NULL DEFAULT 0
        AFTER tipo;

UPDATE cuentas_contables
   SET es_inventariable = 1
 WHERE codigo IN ('601','602','603','604')
   AND empresa_id IS NULL;
