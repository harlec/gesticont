-- ============================================================
-- GestiCont — Motor Contable, complemento a Fase 0
-- Agrega las cuentas de Ventas que faltaban para cubrir empresas de
-- manufactura, no solo comercio (701 Mercadería) y servicios (703).
--
-- Archivo incremental — usar INSERT en vez de reescribir
-- fase0_plan_contable.sql, por si ya se importó en Plesk.
-- Fuente: PCGE Modificado 2019, mismo criterio de fase0_plan_contable.sql.
-- ============================================================

INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('702', 'Productos Terminados',                    3, '70', 'acreedora', 'ingreso'),  -- venta de bienes fabricados por la propia empresa (manufactura), no comprados para reventa
('704', 'Subproductos, Desechos y Desperdicios',   3, '70', 'acreedora', 'ingreso');  -- venta de remanentes/sobrantes del proceso productivo — código oficial vigente (antes de la modificación 2019 este código significaba "Prestación de servicios")

INSERT INTO tipos_gasto (nombre_visible, cuenta_id, aplica_a, orden)
SELECT v.nombre_visible, c.id, 'venta', v.orden FROM (
    SELECT 'Productos Terminados (manufactura)' AS nombre_visible, '702' AS codigo, 15 AS orden
    UNION ALL SELECT 'Subproductos y Desechos', '704', 25
) AS v
JOIN cuentas_contables c ON c.codigo = v.codigo AND c.empresa_id IS NULL;
