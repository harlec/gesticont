-- ============================================================
-- GestiCont — Motor Contable, FASE 1i
-- Honorarios por recibo (4ta categoría) y préstamos bancarios, como en
-- las hojas HONORARIO y BANCO del Excel de referencia (Valencia).
--
-- HONORARIOS: cada recibo se provisiona Debe 632 / Haber 424 (neto por
-- pagar) + Haber 4017 (retención de renta de 4ta categoría), y luego el
-- gasto pasa a 94/95 por destino igual que cualquier otro gasto. Se asume
-- pagado por defecto (movimiento de Caja Debe 424 / Haber 101), criterio
-- del contador; se puede pasar a pendiente.
--
-- PRÉSTAMOS: la fuente de verdad son los movimientos de Caja ligados al
-- préstamo (desembolso = ingreso contra 451, amortización = egreso contra
-- 451, interés = egreso contra 673) — igual que cobros y pagos de
-- comprobantes. El saldo pendiente es lo recibido menos lo amortizado.
--
-- Ejecutar DESPUÉS de fase1h_reglas_origen.sql.
-- ============================================================

CREATE TABLE honorarios (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id        INT UNSIGNED NOT NULL,
    periodo           CHAR(6) NOT NULL COMMENT 'YYYYMM',
    fecha             DATE NOT NULL,
    prestador_nombre  VARCHAR(200) NOT NULL,
    prestador_doc     VARCHAR(15)  DEFAULT NULL COMMENT 'RUC o DNI',
    comprobante       VARCHAR(30)  DEFAULT NULL COMMENT 'Serie-número del recibo por honorarios',
    descripcion       VARCHAR(200) DEFAULT NULL,
    monto             DECIMAL(12,2) NOT NULL COMMENT 'importe bruto del recibo',
    retencion         DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'renta de 4ta categoría retenida (8 %)',
    pagado            TINYINT(1) NOT NULL DEFAULT 0,
    fecha_pago        DATE DEFAULT NULL,
    usuario_id        INT UNSIGNED DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_empresa_periodo (empresa_id, periodo)
) ENGINE=InnoDB;

CREATE TABLE prestamos (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id        INT UNSIGNED NOT NULL,
    entidad           VARCHAR(150) NOT NULL COMMENT 'banco o entidad financiera',
    referencia        VARCHAR(100) DEFAULT NULL COMMENT 'número de contrato / cronograma',
    fecha_desembolso  DATE NOT NULL,
    usuario_id        INT UNSIGNED DEFAULT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB;

-- Vínculo del movimiento de Caja con el honorario o préstamo que lo originó
-- (a lo sumo uno de los dos, junto a registro_venta_id / registro_compra_id).
ALTER TABLE caja_movimientos
    ADD COLUMN honorario_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN prestamo_id  INT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT fk_caja_honorario FOREIGN KEY (honorario_id) REFERENCES honorarios(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_caja_prestamo  FOREIGN KEY (prestamo_id)  REFERENCES prestamos(id)  ON DELETE SET NULL;
