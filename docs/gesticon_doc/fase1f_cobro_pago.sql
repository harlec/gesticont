-- ============================================================
-- GestiCont — Motor Contable, FASE 1f
-- Cobro/pago declarado en el momento de clasificar (spec 2.5/2.7,
-- decisión de producto: no hay pantalla aparte de "cuentas por cobrar",
-- se declara junto a la cuenta contable al clasificar cada comprobante).
--
-- No se puede inferir de SIRE si un comprobante fue cobrado/pagado — el
-- RVIE/RCE solo reporta la emisión para efectos de IGV/Renta, nunca el
-- estado de cobranza (eso es un dato privado entre la empresa y su
-- cliente/proveedor, invisible para SUNAT). Por eso este campo siempre
-- lo declara la persona, nunca se sincroniza.
--
-- Ejecutar DESPUÉS de fase1e_caja_asientos.sql.
-- ============================================================

ALTER TABLE registro_ventas
    ADD COLUMN cobrado     TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_imputacion,
    ADD COLUMN fecha_cobro DATE       DEFAULT NULL       AFTER cobrado;

ALTER TABLE registro_compras
    ADD COLUMN pagado     TINYINT(1) NOT NULL DEFAULT 0 AFTER estado_imputacion,
    ADD COLUMN fecha_pago DATE       DEFAULT NULL       AFTER pagado;

-- Vínculo opcional del movimiento de caja generado automáticamente al
-- marcar "cobrada"/"pagada" — para poder rastrear cada saldo de Caja
-- hasta el comprobante que lo originó (igual criterio que imputaciones:
-- ambos nulos en un movimiento de caja manual normal, nunca los dos a
-- la vez en uno generado por clasificación).
ALTER TABLE caja_movimientos
    ADD COLUMN registro_venta_id  INT UNSIGNED DEFAULT NULL AFTER cuenta_id,
    ADD COLUMN registro_compra_id INT UNSIGNED DEFAULT NULL AFTER registro_venta_id,
    ADD CONSTRAINT fk_caja_reg_venta  FOREIGN KEY (registro_venta_id)  REFERENCES registro_ventas(id)  ON DELETE SET NULL,
    ADD CONSTRAINT fk_caja_reg_compra FOREIGN KEY (registro_compra_id) REFERENCES registro_compras(id) ON DELETE SET NULL;
