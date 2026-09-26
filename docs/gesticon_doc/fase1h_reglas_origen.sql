-- ============================================================
-- GestiCont — Motor Contable, FASE 1h
-- Las reglas de clasificación por RUC ahora distinguen si aplican a
-- COMPRAS (el RUC es un proveedor) o a VENTAS (el RUC es un cliente).
-- Antes una regla se disparaba por RUC sin importar la dirección, lo
-- que no tiene sentido contable: la cuenta destino de una compra
-- (gasto/costo, 60-65) nunca es la de una venta (ingreso, 70).
--
-- Las reglas ya existentes se reparten según dónde aparece su RUC:
-- solo en ventas -> 'venta'; en cualquier otro caso -> 'compra'
-- (el uso más común, y el que tenía sentido en la primera versión).
--
-- Ejecutar DESPUÉS de fase1g_perfil_comercial.sql.
-- ============================================================

ALTER TABLE reglas_imputacion
    ADD COLUMN aplica_a ENUM('compra','venta') NOT NULL DEFAULT 'compra' AFTER empresa_id;

UPDATE reglas_imputacion r
SET r.aplica_a = 'venta'
WHERE r.tipo_criterio = 'ruc_contraparte'
  AND EXISTS (SELECT 1 FROM registro_ventas v  WHERE v.empresa_id = r.empresa_id AND v.cliente_num_doc = r.valor_criterio)
  AND NOT EXISTS (SELECT 1 FROM registro_compras c WHERE c.empresa_id = r.empresa_id AND c.proveedor_ruc = r.valor_criterio);
