-- ============================================================
-- GestiCont — Motor Contable, FASE 1e
-- Caja y Bancos (spec 2.5) — adapta la tabla caja_movimientos ya
-- existente para poder generar el asiento de Caja (2.7 "Por Caja").
--
-- Ejecutar DESPUÉS de fase0_plan_contable.sql y fase1_imputacion_diario.sql.
-- ============================================================

ALTER TABLE caja_movimientos
    ADD COLUMN cuenta_id INT UNSIGNED DEFAULT NULL AFTER categoria,
    ADD CONSTRAINT fk_caja_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id);

-- ============================================================
-- NOTA sobre el asiento de Caja (spec 2.7):
--
--   INGRESOS: DEBE 101/104 Caja y Bancos = total ingresos
--             HABER cuenta_id de cada movimiento (121 Clientes por lo
--             cobrado, 451 Obligaciones Financieras por lo prestado, etc.)
--
--   EGRESOS:  DEBE cuenta_id de cada movimiento (421 Proveedores, 4011
--             IGV, 4017 Renta, 4031 ESSALUD, 4032 ONP, 417 AFP, 411
--             Remuneraciones, 632 Honorarios pagados directo, etc.)
--             HABER 101/104 Caja y Bancos = total egresos
--
-- El contador elige la cuenta contable directamente al registrar cada
-- movimiento (no hay una capa de "nombre visible" como en tipos_gasto,
-- porque en la práctica real —visto en el Libro Caja de Valencia— cada
-- columna de caja ya es un código PCGE reconocible: "632 ASES Y CONSULT",
-- "421 PROVEEDORES", etc.).
-- ============================================================
