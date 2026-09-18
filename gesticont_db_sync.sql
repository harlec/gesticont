-- ============================================================
-- GestiCont — Tablas adicionales para sincronización SUNAT
-- Ejecutar después de gesticont_db.sql
-- ============================================================

USE gesticont;

-- ============================================================
-- REGISTRO DE VENTAS (jalado de SUNAT/SIRE o emitido en sistema)
-- ============================================================
CREATE TABLE registro_ventas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    -- Identificación del comprobante
    tipo_comp       VARCHAR(2)    NOT NULL,  -- 01=Factura, 03=Boleta, 07=NC, 08=ND
    serie           VARCHAR(4)    NOT NULL,
    correlativo     INT UNSIGNED  NOT NULL,
    -- Fechas
    fecha_emision   DATE          NOT NULL,
    -- Cliente
    cliente_tipo_doc VARCHAR(10),
    cliente_num_doc  VARCHAR(20),
    cliente_nombre   VARCHAR(200),
    -- Importes
    moneda          VARCHAR(3)    NOT NULL DEFAULT 'PEN',
    tipo_cambio     DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    base_imponible  DECIMAL(14,2) NOT NULL DEFAULT 0,
    igv             DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_inafecta   DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_exonerada  DECIMAL(14,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Estado
    estado_sunat    VARCHAR(20)   NOT NULL DEFAULT 'aceptado',
    hash_cpe        VARCHAR(500),
    -- Control
    fuente          ENUM('gesticont','api_sunat','sire_api','importacion_excel') NOT NULL DEFAULT 'api_sunat',
    sync_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- FK
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_comp_venta (empresa_id, tipo_comp, serie, correlativo),
    INDEX idx_empresa_fecha (empresa_id, fecha_emision),
    INDEX idx_periodo (empresa_id, fecha_emision),
    INDEX idx_estado (estado_sunat)
) ENGINE=InnoDB;

-- ============================================================
-- REGISTRO DE COMPRAS (jalado del SIRE de SUNAT)
-- ============================================================
CREATE TABLE registro_compras (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    -- Proveedor
    proveedor_ruc   VARCHAR(11)   NOT NULL,
    proveedor_nombre VARCHAR(200),
    -- Comprobante del proveedor
    tipo_comp       VARCHAR(2)    NOT NULL DEFAULT '01',
    serie           VARCHAR(4)    NOT NULL,
    correlativo     VARCHAR(20)   NOT NULL,  -- varchar porque pueden ser alfanuméricos
    fecha_emision   DATE          NOT NULL,
    -- Importes
    base_imponible  DECIMAL(14,2) NOT NULL DEFAULT 0,
    igv             DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_inafecta   DECIMAL(14,2) NOT NULL DEFAULT 0,
    base_no_gravada DECIMAL(14,2) NOT NULL DEFAULT 0,
    total           DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Clasificación
    tipo_operacion  VARCHAR(4)    NOT NULL DEFAULT '01',  -- 01=gravada, 02=no gravada
    -- Estado
    estado_sunat    VARCHAR(20)   NOT NULL DEFAULT 'aceptado',
    -- Control
    fuente          ENUM('sire_api','importacion_excel','manual') NOT NULL DEFAULT 'sire_api',
    sync_at         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- FK
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_comp_compra (empresa_id, proveedor_ruc, tipo_comp, serie, correlativo),
    INDEX idx_empresa_fecha (empresa_id, fecha_emision),
    INDEX idx_proveedor (empresa_id, proveedor_ruc)
) ENGINE=InnoDB;

-- ============================================================
-- RESUMEN MENSUAL (generado por el cron de cierre)
-- Es la base para la hoja de trabajo y el PDT
-- ============================================================
CREATE TABLE resumen_mensual (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    periodo         VARCHAR(6)   NOT NULL,  -- YYYYMM
    -- Ventas
    base_ventas     DECIMAL(14,2) NOT NULL DEFAULT 0,
    igv_ventas      DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_ventas    DECIMAL(14,2) NOT NULL DEFAULT 0,
    cant_facturas   INT UNSIGNED  NOT NULL DEFAULT 0,
    cant_boletas    INT UNSIGNED  NOT NULL DEFAULT 0,
    -- Compras
    base_compras    DECIMAL(14,2) NOT NULL DEFAULT 0,
    igv_compras     DECIMAL(14,2) NOT NULL DEFAULT 0,
    total_compras   DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Determinación IGV
    igv_resultante        DECIMAL(14,2) NOT NULL DEFAULT 0,
    saldo_favor_anterior  DECIMAL(14,2) NOT NULL DEFAULT 0,
    igv_a_pagar           DECIMAL(14,2) NOT NULL DEFAULT 0,
    saldo_igv_favor       DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Renta
    base_renta      DECIMAL(14,2) NOT NULL DEFAULT 0,
    porcentaje_renta DECIMAL(5,2) NOT NULL DEFAULT 1.50,
    renta_a_pagar   DECIMAL(14,2) NOT NULL DEFAULT 0,
    -- Estado del resumen
    estado          ENUM('generado','pendiente_revision','revisado','declarado') NOT NULL DEFAULT 'generado',
    revisado_por    INT UNSIGNED,
    revisado_en     DATETIME,
    -- Declaración
    fecha_declaracion DATE,
    num_orden_sunat VARCHAR(20),
    -- Metadata
    generado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    -- FK
    FOREIGN KEY (empresa_id)  REFERENCES empresas(id)  ON DELETE CASCADE,
    FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_resumen (empresa_id, periodo),
    INDEX idx_periodo (periodo),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- ============================================================
-- VISTA: resumen del dashboard por empresa
-- ============================================================
CREATE OR REPLACE VIEW v_dashboard_empresa AS
SELECT
    e.id                                          AS empresa_id,
    e.ruc,
    e.razon_social,
    e.regimen,
    -- Último mes con datos
    rm.periodo                                    AS ultimo_periodo,
    rm.base_ventas,
    rm.igv_ventas,
    rm.base_compras,
    rm.igv_compras,
    rm.igv_a_pagar,
    rm.saldo_igv_favor,
    rm.renta_a_pagar,
    rm.estado                                     AS estado_resumen,
    -- Certificado
    ec.cert_hasta,
    DATEDIFF(ec.cert_hasta, CURDATE())            AS dias_cert,
    CASE
        WHEN ec.cert_hasta < CURDATE()                        THEN 'vencido'
        WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7          THEN 'critico'
        WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 30         THEN 'por_vencer'
        ELSE                                                       'vigente'
    END                                           AS estado_cert,
    -- Última sincronización
    (SELECT MAX(sync_at) FROM registro_ventas rv
     WHERE rv.empresa_id = e.id)                 AS ultima_sync_ventas,
    (SELECT MAX(sync_at) FROM registro_compras rc
     WHERE rc.empresa_id = e.id)                 AS ultima_sync_compras
FROM empresas e
LEFT JOIN resumen_mensual rm ON rm.empresa_id = e.id
    AND rm.periodo = (
        SELECT MAX(periodo) FROM resumen_mensual
        WHERE empresa_id = e.id
    )
LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
WHERE e.activo = 1;

-- ============================================================
-- CRONTAB de referencia (agregar con: crontab -e)
-- ============================================================
-- # GestiCont — Sincronización automática
-- # Sync ventas diario 6:00am
-- 0 6 * * * php /var/www/gesticont/cron/sync_ventas.php >> /var/www/gesticont/storage/logs/sync_ventas.log 2>&1
--
-- # Sync compras diario 6:30am
-- 30 6 * * * php /var/www/gesticont/cron/sync_compras.php >> /var/www/gesticont/storage/logs/sync_compras.log 2>&1
--
-- # Alertas certificados diario 7:00am
-- 0 7 * * * php /var/www/gesticont/cron/alertas_certificados.php >> /var/www/gesticont/storage/logs/alertas.log 2>&1
--
-- # Cierre mensual día 2 de cada mes 8:00am
-- 0 8 2 * * php /var/www/gesticont/cron/cierre_mensual.php >> /var/www/gesticont/storage/logs/cierre_mensual.log 2>&1
--
-- # Backup BD semanal domingos 2:00am
-- 0 2 * * 0 php /var/www/gesticont/cron/backup_db.php >> /var/www/gesticont/storage/logs/backup.log 2>&1
