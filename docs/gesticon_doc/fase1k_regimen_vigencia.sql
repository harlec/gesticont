-- ============================================================
-- GestiCont — FASE 1k
-- Historial del régimen tributario de cada empresa, con vigencia.
-- Antes solo existía empresas.regimen (un dato suelto que se pisaba al
-- editar y no decía desde cuándo regía). Ahora cada cambio es una fila:
-- el régimen de un período es el que estaba vigente en esa fecha, y
-- empresas.regimen queda como "el régimen actual" para los listados.
--
-- Códigos (los de empresas.regimen): rus = Nuevo RUS, especial = RER,
-- mype = MYPE Tributario, general = Régimen General.
-- tramo = ingresos brutos anuales en UIT; solo aplica a MYPE y General,
-- porque de él depende qué libros son obligatorios (RS 234-2006/SUNAT).
-- Es informativo: el sistema no bloquea pantallas según el régimen.
-- ============================================================

CREATE TABLE IF NOT EXISTS empresa_regimen (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id     INT UNSIGNED NOT NULL,
    regimen        ENUM('rus','especial','mype','general') NOT NULL,
    tramo          ENUM('hasta_300','300_500','500_1700','mas_1700') DEFAULT NULL,
    vigente_desde  DATE NOT NULL,
    vigente_hasta  DATE DEFAULT NULL COMMENT 'NULL = vigente actualmente',
    nota           VARCHAR(300) DEFAULT NULL,
    usuario_id     INT UNSIGNED DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)  ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)  ON DELETE SET NULL,
    INDEX idx_empresa_vigencia (empresa_id, vigente_desde)
) ENGINE=InnoDB;

-- Cada empresa existente arranca con su régimen actual, vigente desde el primer
-- período con el que trabaja el sistema (enero de 2026).
INSERT INTO empresa_regimen (empresa_id, regimen, vigente_desde)
SELECT e.id, e.regimen, '2026-01-01'
FROM empresas e
WHERE NOT EXISTS (SELECT 1 FROM empresa_regimen r WHERE r.empresa_id = e.id);
