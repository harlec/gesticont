-- ============================================================
-- GestiCont — Motor Contable, FASE 1 (núcleo)
-- Imputación manual de Compras/Ventas ya sincronizadas por SIRE,
-- y generación de asientos (Libro Diario).
--
-- Ejecutar DESPUÉS de fase0_plan_contable.sql (usa cuentas_contables,
-- periodos_contables, reglas_imputacion).
--
-- Decisión de diseño: la spec original proponía una tabla nueva
-- "documentos_sire" para compras/ventas. Ya existen y funcionan en
-- producción "registro_ventas" y "registro_compras" (sincronizadas
-- desde SIRE, con datos reales ya cargados) — se adaptan esas tablas
-- en vez de duplicar el mismo dato en una tabla paralela.
-- ============================================================

-- ============================================================
-- 1. Adaptar registro_ventas / registro_compras para imputación
-- ============================================================
ALTER TABLE registro_ventas
    ADD COLUMN estado_imputacion ENUM('pendiente','imputado','parcial')
        NOT NULL DEFAULT 'pendiente' AFTER estado_sunat;

ALTER TABLE registro_compras
    ADD COLUMN estado_imputacion ENUM('pendiente','imputado','parcial')
        NOT NULL DEFAULT 'pendiente' AFTER estado_sunat;

-- ============================================================
-- 2. IMPUTACIONES
-- Cada documento (venta o compra) puede dividirse entre varias cuentas
-- si aplica (no siempre es 1 a 1) — por eso es una tabla de líneas, no
-- una columna en registro_ventas/registro_compras.
-- ============================================================
CREATE TABLE imputaciones (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registro_venta_id  INT UNSIGNED DEFAULT NULL,
    registro_compra_id INT UNSIGNED DEFAULT NULL,
    cuenta_id          INT UNSIGNED NOT NULL,
    monto              DECIMAL(14,2) NOT NULL,
    regla_id           INT UNSIGNED DEFAULT NULL,  -- Fase 2, siempre NULL en Fase 1
    usuario_id         INT UNSIGNED NOT NULL,       -- quién clasificó, para trazabilidad
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registro_venta_id)  REFERENCES registro_ventas(id)  ON DELETE CASCADE,
    FOREIGN KEY (registro_compra_id) REFERENCES registro_compras(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id)          REFERENCES cuentas_contables(id),
    FOREIGN KEY (regla_id)           REFERENCES reglas_imputacion(id),
    FOREIGN KEY (usuario_id)         REFERENCES usuarios(id),
    CONSTRAINT chk_imputacion_un_solo_origen CHECK (
        (registro_venta_id IS NOT NULL AND registro_compra_id IS NULL)
        OR (registro_venta_id IS NULL AND registro_compra_id IS NOT NULL)
    )
) ENGINE=InnoDB;

-- ============================================================
-- 3. LIBRO DIARIO — fuente única de verdad
-- Cada compra/venta/planilla/caja imputada inserta sus líneas
-- directamente aquí. El Mayor y el Balance de Comprobación se calculan
-- SIEMPRE agregando estas dos tablas — nunca por un camino paralelo
-- (corrección explícita respecto al Excel original, sección "Correcciones
-- respecto al Excel original" de la spec).
-- ============================================================
CREATE TABLE asientos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id   INT UNSIGNED NOT NULL,
    periodo_id   INT UNSIGNED NOT NULL,
    correlativo  INT UNSIGNED NOT NULL,
    fecha        DATE NOT NULL,
    glosa        VARCHAR(300) NOT NULL,
    origen       ENUM('compra','venta','planilla','caja','manual','cierre') NOT NULL,
    documento_id INT UNSIGNED DEFAULT NULL,  -- referencia al registro origen; su tabla depende de "origen" (compra->registro_compras.id, venta->registro_ventas.id, etc.) — sin FK físico porque apunta a distintas tablas según el caso; se valida en la capa de servicio
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (periodo_id) REFERENCES periodos_contables(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empresa_correlativo (empresa_id, correlativo)
) ENGINE=InnoDB;

CREATE TABLE asientos_detalle (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asiento_id INT UNSIGNED NOT NULL,
    cuenta_id  INT UNSIGNED NOT NULL,
    debe       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    haber      DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (asiento_id) REFERENCES asientos(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id)  REFERENCES cuentas_contables(id)
) ENGINE=InnoDB;

-- NOTA: "no se persiste un asiento si Σdebe ≠ Σhaber" (regla transversal de
-- la spec, sección 2.7) se valida en la capa de servicio de PHP antes del
-- INSERT — no como constraint de base de datos. MySQL/MariaDB no tienen una
-- forma nativa confiable de sumar y comparar filas relacionadas dentro de un
-- CHECK constraint de una sola tabla.

-- ============================================================
-- 4. CIERRE DE PERÍODO
-- Al ejecutarse (lógica de aplicación, no aquí): calcula el resultado del
-- ejercicio, genera saldos_apertura del período siguiente a partir de los
-- saldos finales de las cuentas de balance (activo/pasivo/patrimonio, no
-- las de resultado 6/7/9 que se cierran a cero), y marca el período como
-- cerrado con sus asientos inmutables desde ese momento.
-- ============================================================
CREATE TABLE cierres_periodo (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id          INT UNSIGNED NOT NULL,
    periodo_id          INT UNSIGNED NOT NULL,
    fecha_cierre        DATE NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL,
    resultado_ejercicio DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (periodo_id) REFERENCES periodos_contables(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY uk_empresa_periodo (empresa_id, periodo_id)
) ENGINE=InnoDB;
