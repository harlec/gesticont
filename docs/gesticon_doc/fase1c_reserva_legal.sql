-- ============================================================
-- GestiCont — Motor Contable, complemento a Fase 0
-- Agrega el % de Reserva Legal a parametros_contables — la spec (2.12)
-- pide que sea configurable ("10% o el % legal vigente"), no un valor
-- fijo en el código, igual que ya se hizo con tasa_ir y los % de
-- distribución de gastos admin/ventas.
--
-- Archivo incremental — usar INSERT/ALTER en vez de reescribir
-- fase0_plan_contable.sql, por si ya se importó en Plesk.
-- ============================================================

ALTER TABLE parametros_contables
    ADD COLUMN pct_reserva_legal DECIMAL(5,2) NOT NULL DEFAULT 10.00
        AFTER pct_gastos_ventas;
