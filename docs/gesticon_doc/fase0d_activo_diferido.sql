-- ============================================================
-- GestiCont — Motor Contable, FASE 0d
-- Agrega la cuenta 37 "Activo Diferido" / 371 "Activos Diferidos" al
-- catálogo PCGE — faltaba por completo (el catálogo original saltaba de
-- 33 a 38). Señalado por el contador al revisar la pantalla de Apertura.
--
-- Ejecutar en cualquier momento después de fase0_plan_contable.sql —
-- solo agrega, no modifica ni reordena nada existente.
-- ============================================================

INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('37',  'Activo Diferido',    2, NULL, 'deudora', 'activo'),
('371', 'Activos Diferidos',  3, '37', 'deudora', 'activo');
