-- ============================================================
-- GestiCont — FASE 1m
-- Medio de pago de cada cobro/pago registrado en Caja (Efectivo, Transferencia,
-- Yape / Plin, Tarjeta). Lo pide el rediseño de "Cobros y pagos". Es opcional:
-- los movimientos existentes quedan sin medio y el sistema funciona igual si
-- esta migración aún no se aplicó.
-- ============================================================
ALTER TABLE caja_movimientos
    ADD COLUMN medio_pago ENUM('efectivo','transferencia','yape_plin','tarjeta') DEFAULT NULL AFTER descripcion;
