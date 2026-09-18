-- ============================================================
-- GestiCont — Sistema Contable Perú
-- Base de datos completa v1.0
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4
-- ============================================================

CREATE DATABASE IF NOT EXISTS gesticont
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gesticont;

-- ============================================================
-- 1. USUARIOS DEL SISTEMA
-- (El superadmin eres tú, los contadores son tus clientes,
--  los operadores son el equipo de cada contador)
-- ============================================================
CREATE TABLE usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100)  NOT NULL,
    email         VARCHAR(150)  NOT NULL UNIQUE,
    password      VARCHAR(255)  NOT NULL,
    rol           ENUM('superadmin','contador','operador','cliente') NOT NULL DEFAULT 'operador',
    telefono      VARCHAR(20),
    avatar        VARCHAR(300),
    activo        TINYINT(1)    NOT NULL DEFAULT 1,
    ultimo_acceso DATETIME,
    token_reset   VARCHAR(100),
    token_expira  DATETIME,
    created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_rol (rol)
) ENGINE=InnoDB;

-- ============================================================
-- 2. EMPRESAS (cada cliente del contador)
-- ============================================================
CREATE TABLE empresas (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Datos principales
    ruc               VARCHAR(11)   NOT NULL UNIQUE,
    razon_social      VARCHAR(200)  NOT NULL,
    nombre_comercial  VARCHAR(200),
    -- Dirección
    direccion         VARCHAR(300),
    ubigeo            VARCHAR(6),
    distrito          VARCHAR(100),
    provincia         VARCHAR(100),
    departamento      VARCHAR(100),
    -- Contacto
    telefono          VARCHAR(20),
    email             VARCHAR(150),
    web               VARCHAR(200),
    logo_path         VARCHAR(300),
    -- Tributario
    regimen           ENUM('general','mype','especial','rus') NOT NULL DEFAULT 'mype',
    tipo_contribuyente ENUM('natural','juridica') NOT NULL DEFAULT 'juridica',
    -- Series de comprobantes
    serie_factura     VARCHAR(4)    NOT NULL DEFAULT 'F001',
    serie_boleta      VARCHAR(4)    NOT NULL DEFAULT 'B001',
    serie_nota_cred   VARCHAR(4)    NOT NULL DEFAULT 'FC01',
    serie_nota_deb    VARCHAR(4)    NOT NULL DEFAULT 'FD01',
    serie_guia        VARCHAR(4)    NOT NULL DEFAULT 'T001',
    -- Plan y facturación
    plan              ENUM('basico','profesional','ilimitado') NOT NULL DEFAULT 'basico',
    fecha_alta        DATE,
    fecha_vence       DATE,
    activo            TINYINT(1)    NOT NULL DEFAULT 1,
    -- Metadata
    created_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ruc (ruc),
    INDEX idx_activo (activo)
) ENGINE=InnoDB;

-- ============================================================
-- 3. CERTIFICADOS DIGITALES POR EMPRESA
-- (Tabla separada para mayor seguridad y control)
-- ============================================================
CREATE TABLE empresa_certificados (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    -- Archivo del certificado
    cert_path       VARCHAR(500) NOT NULL,    -- ruta al .pfx en servidor
    cert_password   TEXT         NOT NULL,    -- contraseña encriptada con AES
    cert_nombre     VARCHAR(200),             -- nombre del archivo original
    -- Información del certificado
    cert_cn         VARCHAR(200),             -- Common Name del certificado
    cert_desde      DATE,                     -- válido desde
    cert_hasta      DATE,                     -- válido hasta
    cert_emisor     VARCHAR(200),             -- entidad emisora (eCert, FNMT, etc.)
    cert_tipo       ENUM('pfx','p12','fnmt') NOT NULL DEFAULT 'pfx',
    -- Estado
    estado          ENUM('activo','vencido','por_vencer','revocado') NOT NULL DEFAULT 'activo',
    alerta_enviada  TINYINT(1)  NOT NULL DEFAULT 0,  -- si ya se mandó alerta de vencimiento
    -- Credenciales SOL SUNAT
    sol_usuario     VARCHAR(20),              -- RUC + usuario SOL (ej: 20601234567HUGO)
    sol_clave       TEXT,                     -- clave SOL encriptada con AES
    -- Ambiente
    ambiente        ENUM('beta','produccion') NOT NULL DEFAULT 'produccion',
    -- Quien configuró
    configurado_por INT UNSIGNED,
    -- Metadata
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id)      REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (configurado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_empresa (empresa_id),
    INDEX idx_estado (estado),
    INDEX idx_vencimiento (cert_hasta)
) ENGINE=InnoDB;

-- ============================================================
-- 4. RELACIÓN CONTADOR → EMPRESAS
-- (Un contador puede manejar muchas empresas,
--  una empresa puede tener varios contadores/operadores)
-- ============================================================
CREATE TABLE empresa_usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NOT NULL,
    rol         ENUM('admin','operador','solo_lectura') NOT NULL DEFAULT 'operador',
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_usuario (empresa_id, usuario_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)  ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 5. CLIENTES DE CADA EMPRESA
-- (Los clientes a quienes les emiten comprobantes)
-- ============================================================
CREATE TABLE clientes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo_doc        ENUM('RUC','DNI','CE','PASAPORTE','otros') NOT NULL DEFAULT 'RUC',
    numero_doc      VARCHAR(20)  NOT NULL,
    razon_social    VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(200),
    direccion       VARCHAR(300),
    ubigeo          VARCHAR(6),
    distrito        VARCHAR(100),
    provincia       VARCHAR(100),
    departamento    VARCHAR(100),
    email           VARCHAR(150),
    telefono        VARCHAR(20),
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_doc (tipo_doc, numero_doc)
) ENGINE=InnoDB;

-- ============================================================
-- 6. PRODUCTOS / SERVICIOS POR EMPRESA
-- ============================================================
CREATE TABLE productos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    codigo          VARCHAR(50),
    descripcion     VARCHAR(500) NOT NULL,
    unidad          VARCHAR(10)  NOT NULL DEFAULT 'NIU',  -- catálogo SUNAT
    precio_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    tipo_afectacion VARCHAR(4)   NOT NULL DEFAULT '10',  -- IGV gravado=10
    incluye_igv     TINYINT(1)   NOT NULL DEFAULT 1,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMPROBANTES (facturas, boletas, notas)
-- ============================================================
CREATE TABLE comprobantes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    cliente_id      INT UNSIGNED,
    -- Identificación SUNAT
    tipo_comp       VARCHAR(2)   NOT NULL,   -- 01=Factura, 03=Boleta, 07=N.Cred, 08=N.Deb
    serie           VARCHAR(4)   NOT NULL,
    correlativo     INT UNSIGNED NOT NULL,
    -- Para notas: referencia al comprobante original
    comp_ref_id     INT UNSIGNED,
    tipo_nota       VARCHAR(2),              -- catálogo 09 SUNAT (motivo nota crédito/débito)
    -- Fechas
    fecha_emision   DATE         NOT NULL,
    fecha_vencimiento DATE,
    -- Cliente en el momento de emisión (snapshot)
    cliente_tipo_doc VARCHAR(10),
    cliente_num_doc VARCHAR(20),
    cliente_nombre  VARCHAR(200),
    cliente_direccion VARCHAR(300),
    -- Moneda
    moneda          VARCHAR(3)   NOT NULL DEFAULT 'PEN',
    tipo_cambio     DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
    -- Importes
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0,
    descuento_global DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_igv        DECIMAL(12,2) NOT NULL DEFAULT 0,
    igv             DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_inafecta   DECIMAL(12,2) NOT NULL DEFAULT 0,
    base_exonerada  DECIMAL(12,2) NOT NULL DEFAULT 0,
    otros_cargos    DECIMAL(12,2) NOT NULL DEFAULT 0,
    total           DECIMAL(12,2) NOT NULL DEFAULT 0,
    -- Estado SUNAT
    estado          ENUM('borrador','enviado','aceptado','rechazado','anulado','por_anular') NOT NULL DEFAULT 'borrador',
    hash_cpe        VARCHAR(500),            -- hash del XML firmado
    xml_path        VARCHAR(500),            -- ruta al XML firmado
    cdr_path        VARCHAR(500),            -- ruta al CDR de SUNAT
    pdf_path        VARCHAR(500),            -- ruta al PDF generado
    sunat_descripcion TEXT,                  -- respuesta de SUNAT
    sunat_codigo    VARCHAR(10),             -- código de respuesta SUNAT
    -- Forma de pago
    forma_pago      ENUM('contado','credito') NOT NULL DEFAULT 'contado',
    -- Observaciones
    observaciones   TEXT,
    -- Quien emitió
    emitido_por     INT UNSIGNED,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id)  REFERENCES empresas(id)     ON DELETE RESTRICT,
    FOREIGN KEY (cliente_id)  REFERENCES clientes(id)     ON DELETE SET NULL,
    FOREIGN KEY (comp_ref_id) REFERENCES comprobantes(id) ON DELETE SET NULL,
    FOREIGN KEY (emitido_por) REFERENCES usuarios(id)     ON DELETE SET NULL,
    UNIQUE KEY uk_serie_corr (empresa_id, tipo_comp, serie, correlativo),
    INDEX idx_empresa_fecha (empresa_id, fecha_emision),
    INDEX idx_estado (estado),
    INDEX idx_tipo (tipo_comp)
) ENGINE=InnoDB;

-- ============================================================
-- 8. DETALLE DE COMPROBANTES
-- ============================================================
CREATE TABLE comprobante_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comprobante_id  INT UNSIGNED NOT NULL,
    producto_id     INT UNSIGNED,
    -- Datos del item en el momento de emisión
    descripcion     VARCHAR(500) NOT NULL,
    unidad          VARCHAR(10)  NOT NULL DEFAULT 'NIU',
    cantidad        DECIMAL(12,4) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(12,4) NOT NULL,
    descuento       DECIMAL(12,4) NOT NULL DEFAULT 0,
    tipo_afectacion VARCHAR(4)   NOT NULL DEFAULT '10',
    base_imponible  DECIMAL(12,4) NOT NULL DEFAULT 0,
    igv             DECIMAL(12,4) NOT NULL DEFAULT 0,
    total           DECIMAL(12,4) NOT NULL DEFAULT 0,
    orden           TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id)    REFERENCES productos(id)    ON DELETE SET NULL,
    INDEX idx_comprobante (comprobante_id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. CORRELATIVO DE SERIES
-- (Controla el siguiente número a usar por empresa y tipo)
-- ============================================================
CREATE TABLE series_correlativo (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo_comp       VARCHAR(2)   NOT NULL,
    serie           VARCHAR(4)   NOT NULL,
    ultimo_correlativo INT UNSIGNED NOT NULL DEFAULT 0,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_serie (empresa_id, tipo_comp, serie),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 10. GUÍAS DE REMISIÓN
-- ============================================================
CREATE TABLE guias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    -- Numeración
    serie           VARCHAR(4)   NOT NULL,
    correlativo     INT UNSIGNED NOT NULL,
    -- Fechas
    fecha_emision   DATE         NOT NULL,
    fecha_traslado  DATE         NOT NULL,
    -- Motivo y modalidad (catálogos SUNAT)
    motivo_traslado VARCHAR(2)   NOT NULL DEFAULT '01',  -- 01=Venta
    modalidad       VARCHAR(2)   NOT NULL DEFAULT '01',  -- 01=Público, 02=Privado
    -- Peso
    peso_bruto      DECIMAL(10,3) NOT NULL DEFAULT 0,
    unidad_peso     VARCHAR(10)  NOT NULL DEFAULT 'KGM',
    num_bultos      INT UNSIGNED,
    -- Destinatario
    dest_tipo_doc   VARCHAR(10),
    dest_num_doc    VARCHAR(20),
    dest_nombre     VARCHAR(200),
    -- Partida
    partida_dir     VARCHAR(300) NOT NULL,
    partida_ubigeo  VARCHAR(6)   NOT NULL,
    -- Llegada
    llegada_dir     VARCHAR(300) NOT NULL,
    llegada_ubigeo  VARCHAR(6)   NOT NULL,
    -- Transportista (modalidad pública)
    transp_ruc      VARCHAR(11),
    transp_nombre   VARCHAR(200),
    transp_placa    VARCHAR(10),
    transp_licencia VARCHAR(20),
    -- Estado SUNAT
    estado          ENUM('borrador','enviado','aceptado','rechazado','anulado') NOT NULL DEFAULT 'borrador',
    xml_path        VARCHAR(500),
    cdr_path        VARCHAR(500),
    pdf_path        VARCHAR(500),
    sunat_codigo    VARCHAR(10),
    sunat_descripcion TEXT,
    hash_cpe        VARCHAR(500),
    -- Referencia a comprobante (opcional)
    comprobante_id  INT UNSIGNED,
    -- Quien emitió
    emitido_por     INT UNSIGNED,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id)     REFERENCES empresas(id)     ON DELETE RESTRICT,
    FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE SET NULL,
    FOREIGN KEY (emitido_por)    REFERENCES usuarios(id)     ON DELETE SET NULL,
    UNIQUE KEY uk_guia (empresa_id, serie, correlativo),
    INDEX idx_empresa_fecha (empresa_id, fecha_traslado),
    INDEX idx_estado (estado)
) ENGINE=InnoDB;

-- ============================================================
-- 11. BIENES EN GUÍA
-- ============================================================
CREATE TABLE guia_bienes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guia_id     INT UNSIGNED NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    cantidad    DECIMAL(12,4) NOT NULL DEFAULT 1,
    unidad      VARCHAR(10)  NOT NULL DEFAULT 'NIU',
    codigo      VARCHAR(50),
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (guia_id) REFERENCES guias(id) ON DELETE CASCADE,
    INDEX idx_guia (guia_id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. CAJA POR EMPRESA
-- (Registro simple de ingresos y egresos)
-- ============================================================
CREATE TABLE caja_movimientos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo            ENUM('ingreso','egreso') NOT NULL,
    categoria       VARCHAR(100),            -- ventas, compras, gastos, etc.
    descripcion     VARCHAR(300) NOT NULL,
    monto           DECIMAL(12,2) NOT NULL,
    fecha           DATE         NOT NULL,
    -- Referencia opcional a comprobante
    comprobante_id  INT UNSIGNED,
    -- Adjunto (voucher, recibo, etc.)
    adjunto_path    VARCHAR(500),
    registrado_por  INT UNSIGNED,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id)    REFERENCES empresas(id)     ON DELETE CASCADE,
    FOREIGN KEY (comprobante_id) REFERENCES comprobantes(id) ON DELETE SET NULL,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id)    ON DELETE SET NULL,
    INDEX idx_empresa_fecha (empresa_id, fecha)
) ENGINE=InnoDB;

-- ============================================================
-- 13. DECLARACIONES (PDT / SIRE)
-- (Historial de declaraciones cargadas como respaldo)
-- ============================================================
CREATE TABLE declaraciones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo            ENUM('pdt621','pdt617','pdt601','sire_ventas','sire_compras','renta_anual','otros') NOT NULL,
    periodo         VARCHAR(7)   NOT NULL,   -- YYYY-MM (ej: 2025-03)
    fecha_declaracion DATE,
    fecha_vencimiento DATE,
    estado          ENUM('pendiente','declarado','rectificado') NOT NULL DEFAULT 'pendiente',
    -- Importes principales
    base_ventas     DECIMAL(14,2),
    igv_ventas      DECIMAL(14,2),
    base_compras    DECIMAL(14,2),
    igv_compras     DECIMAL(14,2),
    impuesto_pagar  DECIMAL(14,2),
    -- Archivos adjuntos
    pdf_path        VARCHAR(500),            -- PDF de la declaración
    excel_path      VARCHAR(500),            -- Excel exportado del SIRE
    txt_path        VARCHAR(500),            -- TXT del PLE
    -- Notas
    observaciones   TEXT,
    registrado_por  INT UNSIGNED,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id)    REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uk_declaracion (empresa_id, tipo, periodo),
    INDEX idx_empresa_periodo (empresa_id, periodo),
    INDEX idx_vencimiento (fecha_vencimiento)
) ENGINE=InnoDB;

-- ============================================================
-- 14. ALERTAS Y VENCIMIENTOS
-- (Sistema de notificaciones automáticas)
-- ============================================================
CREATE TABLE alertas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id      INT UNSIGNED NOT NULL,
    tipo            ENUM('certificado','declaracion','renta','planilla','otros') NOT NULL,
    titulo          VARCHAR(200) NOT NULL,
    descripcion     TEXT,
    fecha_vence     DATE         NOT NULL,
    dias_anticipacion TINYINT UNSIGNED NOT NULL DEFAULT 7,
    nivel           ENUM('info','warning','danger') NOT NULL DEFAULT 'warning',
    vista           TINYINT(1)   NOT NULL DEFAULT 0,
    resuelta        TINYINT(1)   NOT NULL DEFAULT 0,
    -- Referencia opcional
    ref_tabla       VARCHAR(50),
    ref_id          INT UNSIGNED,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_fecha (fecha_vence),
    INDEX idx_resuelta (resuelta)
) ENGINE=InnoDB;

-- ============================================================
-- 15. LOG DE ACTIVIDAD
-- (Auditoría de todas las acciones importantes)
-- ============================================================
CREATE TABLE actividad_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED,
    empresa_id  INT UNSIGNED,
    accion      VARCHAR(100) NOT NULL,   -- 'emitir_factura', 'anular_guia', etc.
    descripcion TEXT,
    ip          VARCHAR(45),
    user_agent  VARCHAR(300),
    ref_tabla   VARCHAR(50),
    ref_id      INT UNSIGNED,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE SET NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)  ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_fecha (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 16. CONFIGURACIÓN GENERAL DEL SISTEMA
-- ============================================================
CREATE TABLE configuracion (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave   VARCHAR(100) NOT NULL UNIQUE,
    valor   TEXT,
    tipo    ENUM('texto','numero','booleano','json') NOT NULL DEFAULT 'texto',
    descripcion VARCHAR(300),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Configuración base del sistema
INSERT INTO configuracion (clave, valor, tipo, descripcion) VALUES
('sistema_nombre',        'GestiCont',                    'texto',   'Nombre del sistema'),
('sistema_version',       '1.0.0',                        'texto',   'Versión actual'),
('sistema_moneda',        'PEN',                          'texto',   'Moneda por defecto'),
('igv_porcentaje',        '18',                           'numero',  'Porcentaje IGV vigente'),
('cert_alerta_dias',      '30',                           'numero',  'Días de anticipación alerta certificado'),
('sunat_endpoint_beta',   'https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService', 'texto', 'Endpoint SUNAT beta'),
('sunat_endpoint_prod',   'https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService',   'texto', 'Endpoint SUNAT producción'),
('sunat_endpoint_guia',   'https://e-guiaremision.sunat.gob.pe/ol-ti-itemision-guia-pide-gem/billService', 'texto', 'Endpoint guías SUNAT'),
('cert_encryption_key',   '',                             'texto',   'Clave AES para encriptar certificados — CAMBIAR EN PRODUCCIÓN'),
('max_empresas_basico',   '5',                            'numero',  'Máximo empresas plan básico'),
('max_empresas_pro',      '20',                           'numero',  'Máximo empresas plan profesional'),
('backups_ruta',          '/backups/gesticont/',           'texto',   'Ruta de backups del sistema'),
('email_alertas',         '',                             'texto',   'Email para alertas del sistema');

-- Usuario superadmin inicial (password: GestiCont2025! — CAMBIAR AL INSTALAR)
INSERT INTO usuarios (nombre, email, password, rol) VALUES
('Hugo Admin', 'admin@gesticont.pe',
 '$2y$12$placeholder_cambia_esto_al_instalar_el_sistema_en_vps',
 'superadmin');

-- ============================================================
-- VISTAS ÚTILES
-- ============================================================

-- Vista: estado de certificados por empresa
CREATE OR REPLACE VIEW v_certificados_estado AS
SELECT
    e.id                AS empresa_id,
    e.ruc,
    e.razon_social,
    ec.id               AS cert_id,
    ec.cert_hasta,
    ec.cert_emisor,
    ec.estado           AS cert_estado,
    ec.ambiente,
    DATEDIFF(ec.cert_hasta, CURDATE()) AS dias_para_vencer,
    CASE
        WHEN ec.cert_hasta < CURDATE()                        THEN 'VENCIDO'
        WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7          THEN 'CRITICO'
        WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 30         THEN 'POR_VENCER'
        ELSE                                                       'VIGENTE'
    END AS alerta_nivel
FROM empresas e
LEFT JOIN empresa_certificados ec ON e.id = ec.empresa_id
WHERE e.activo = 1;

-- Vista: resumen de comprobantes por empresa y mes
CREATE OR REPLACE VIEW v_comprobantes_resumen AS
SELECT
    c.empresa_id,
    e.razon_social,
    DATE_FORMAT(c.fecha_emision, '%Y-%m')   AS periodo,
    c.tipo_comp,
    COUNT(*)                                AS cantidad,
    SUM(c.total)                            AS total_monto,
    SUM(CASE WHEN c.estado = 'aceptado' THEN 1 ELSE 0 END) AS aceptados,
    SUM(CASE WHEN c.estado = 'rechazado' THEN 1 ELSE 0 END) AS rechazados
FROM comprobantes c
JOIN empresas e ON e.id = c.empresa_id
WHERE c.estado != 'borrador'
GROUP BY c.empresa_id, e.razon_social, periodo, c.tipo_comp;

-- Vista: alertas activas con info de empresa
CREATE OR REPLACE VIEW v_alertas_activas AS
SELECT
    a.*,
    e.ruc,
    e.razon_social
FROM alertas a
JOIN empresas e ON e.id = a.empresa_id
WHERE a.resuelta = 0
  AND a.fecha_vence >= CURDATE()
ORDER BY a.fecha_vence ASC;

-- ============================================================
-- PROCEDIMIENTOS ALMACENADOS
-- ============================================================

DELIMITER $$

-- Obtener siguiente correlativo (con bloqueo para concurrencia)
CREATE PROCEDURE sp_siguiente_correlativo(
    IN  p_empresa_id  INT UNSIGNED,
    IN  p_tipo_comp   VARCHAR(2),
    IN  p_serie       VARCHAR(4),
    OUT p_correlativo INT UNSIGNED
)
BEGIN
    DECLARE v_ultimo INT UNSIGNED DEFAULT 0;

    START TRANSACTION;

    SELECT ultimo_correlativo INTO v_ultimo
    FROM series_correlativo
    WHERE empresa_id = p_empresa_id
      AND tipo_comp  = p_tipo_comp
      AND serie      = p_serie
    FOR UPDATE;

    IF v_ultimo IS NULL THEN
        INSERT INTO series_correlativo
            (empresa_id, tipo_comp, serie, ultimo_correlativo)
        VALUES
            (p_empresa_id, p_tipo_comp, p_serie, 1);
        SET p_correlativo = 1;
    ELSE
        SET v_ultimo = v_ultimo + 1;
        UPDATE series_correlativo
        SET ultimo_correlativo = v_ultimo
        WHERE empresa_id = p_empresa_id
          AND tipo_comp  = p_tipo_comp
          AND serie      = p_serie;
        SET p_correlativo = v_ultimo;
    END IF;

    COMMIT;
END$$

-- Generar alertas de certificados próximos a vencer
CREATE PROCEDURE sp_generar_alertas_certificados()
BEGIN
    INSERT IGNORE INTO alertas
        (empresa_id, tipo, titulo, descripcion, fecha_vence, nivel)
    SELECT
        ec.empresa_id,
        'certificado',
        CONCAT('Certificado digital vence en ',
               DATEDIFF(ec.cert_hasta, CURDATE()), ' días'),
        CONCAT('El certificado emitido por ', IFNULL(ec.cert_emisor,''),
               ' vence el ', DATE_FORMAT(ec.cert_hasta,'%d/%m/%Y'),
               '. Renuévalo para seguir emitiendo comprobantes.'),
        ec.cert_hasta,
        CASE
            WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7  THEN 'danger'
            WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 15 THEN 'warning'
            ELSE 'info'
        END
    FROM empresa_certificados ec
    WHERE ec.estado   = 'activo'
      AND ec.cert_hasta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY);
END$$

DELIMITER ;

-- ============================================================
-- FIN DEL SCRIPT
-- Próximo paso: estructura de carpetas PHP + config.php
-- ============================================================
