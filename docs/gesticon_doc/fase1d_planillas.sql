-- ============================================================
-- GestiCont — Motor Contable, FASE 1d
-- Planillas (spec 2.4) — captura manual de remuneraciones por
-- trabajador y período, para generar el asiento de planillas (2.7).
--
-- Ejecutar DESPUÉS de fase0_plan_contable.sql y fase1_imputacion_diario.sql.
-- ============================================================

CREATE TABLE planillas (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id          INT UNSIGNED NOT NULL,
    periodo             CHAR(6) NOT NULL COMMENT 'YYYYMM',
    trabajador          VARCHAR(200) NOT NULL,
    sueldo              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gratificacion       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    asignacion_familiar DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    essalud             DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- aporte del EMPLEADOR (9%), es gasto, no descuento al trabajador
    regimen_pension     ENUM('onp','afp','ninguno') NOT NULL DEFAULT 'onp',
    retencion_pension   DECIMAL(12,2) NOT NULL DEFAULT 0.00,  -- descuento al TRABAJADOR (ONP 13% o aporte+comisión AFP)
    origen              ENUM('import_plame','manual') NOT NULL DEFAULT 'manual',
    usuario_id          INT UNSIGNED DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_empresa_periodo (empresa_id, periodo)
) ENGINE=InnoDB;

-- ============================================================
-- NOTA sobre las cuentas del asiento de planillas (ver spec 2.7 "Por
-- Planillas"): la spec menciona "625 Gratificaciones" y divisionarias
-- "6251 Asignación familiar" / "6252 ESSALUD" — NINGUNA de esas existe en
-- el PCGE 2019 (625 es oficialmente "Atención al Personal", sin relación
-- con planillas; 6251/6252 no existen). Se corrige así, con cuentas
-- oficiales verificadas:
--
--   DEBE 621 Remuneraciones      = sueldo + gratificación + asignación familiar
--   DEBE 627 Seguridad, Previsión Social y Otras Contribuciones = essalud (aporte empleador)
--   HABER 411 Remuneraciones por Pagar = neto a pagar (bruto − retención pensión)
--   HABER 4031 ESSALUD           = essalud (aporte empleador, por pagar)
--   HABER 4032 ONP  (si régimen = onp)  = retención del trabajador
--   HABER 417  AFP  (si régimen = afp)  = retención del trabajador
--
-- El gasto de personal (621+627) entra a la misma reclasificación por
-- destino (94/95) que ya existe para compras — un trabajador también
-- puede ser 100% administrativo, 100% de ventas, o repartido igual que
-- cualquier otro gasto operativo.
-- ============================================================
