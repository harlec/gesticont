-- ============================================================
-- GestiCont — Motor Contable, FASE 0 (Fundación)
-- Tablas: cuentas_contables, tipos_gasto, periodos_contables,
--         parametros_contables, reglas_imputacion (estructura, Fase 2)
--
-- Este archivo NO se ha ejecutado contra ninguna base de datos.
-- Revisar con el contador antes de correrlo en Plesk, especialmente
-- las cuentas marcadas "-- CONFIRMAR": el nombre/código exacto viene
-- de dos fuentes reales (spec-motor-contable-gesticont.md y el
-- Libro Diario real de AVIMAS JM E.I.R.L.), pero donde ambas fuentes
-- no coincidían o el dato no estaba completo, se dejó la mejor
-- aproximación en vez de inventar un nombre PCGE que podría ser
-- incorrecto para una declaración real ante SUNAT.
-- ============================================================

-- ============================================================
-- 1. CATÁLOGO DE CUENTAS PCGE
-- ============================================================
CREATE TABLE cuentas_contables (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo       VARCHAR(10)  NOT NULL,        -- '10','101','401.1', etc.
    nombre       VARCHAR(200) NOT NULL,
    nivel        TINYINT UNSIGNED NOT NULL,    -- 2=cuenta, 3=subcuenta, 4=divisionaria
    padre_codigo VARCHAR(10)  DEFAULT NULL,
    naturaleza   ENUM('deudora','acreedora') NOT NULL,
    tipo         ENUM('activo','pasivo','patrimonio','ingreso','gasto','destino') NOT NULL,
    empresa_id   INT UNSIGNED DEFAULT NULL,    -- NULL = catálogo global compartido entre tenants
    activa       TINYINT(1) NOT NULL DEFAULT 1,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_codigo_empresa (codigo, empresa_id)
) ENGINE=InnoDB;

-- ============================================================
-- 2. TIPOS DE GASTO (clasificación en lenguaje llano para la UI)
-- ============================================================
CREATE TABLE tipos_gasto (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_visible VARCHAR(100) NOT NULL,      -- "Mercadería", "Honorarios", etc.
    cuenta_id      INT UNSIGNED NOT NULL,
    aplica_a       ENUM('compra','venta','ambos') NOT NULL DEFAULT 'compra',
    orden          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo         TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (cuenta_id) REFERENCES cuentas_contables(id)
) ENGINE=InnoDB;

-- ============================================================
-- 3. PERÍODOS CONTABLES
-- ============================================================
CREATE TABLE periodos_contables (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id     INT UNSIGNED NOT NULL,
    anio           SMALLINT UNSIGNED NOT NULL,
    estado         ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
    fecha_apertura DATE DEFAULT NULL,
    fecha_cierre   DATE DEFAULT NULL,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empresa_anio (empresa_id, anio)
) ENGINE=InnoDB;

-- ============================================================
-- 4. PARÁMETROS CONTABLES (por empresa y período — nada queda fijo en código)
-- ============================================================
CREATE TABLE parametros_contables (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id        INT UNSIGNED NOT NULL,
    periodo_id        INT UNSIGNED NOT NULL,
    tasa_ir           DECIMAL(5,2) NOT NULL DEFAULT 29.50,  -- % Impuesto a la Renta anual
    pct_gastos_admin  DECIMAL(5,2) NOT NULL DEFAULT 30.00,
    pct_gastos_ventas DECIMAL(5,2) NOT NULL DEFAULT 70.00,
    pct_reserva_legal DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    moneda            CHAR(3) NOT NULL DEFAULT 'PEN',
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (periodo_id) REFERENCES periodos_contables(id) ON DELETE CASCADE,
    UNIQUE KEY uk_empresa_periodo (empresa_id, periodo_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. REGLAS DE IMPUTACIÓN — estructura lista para Fase 2,
--    sin lógica de negocio ni datos en Fase 1 (queda vacía / inactiva)
-- ============================================================
CREATE TABLE reglas_imputacion (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id        INT UNSIGNED NOT NULL,
    tipo_criterio     ENUM('ruc_contraparte','palabra_clave','tipo_comprobante','ruc_y_palabra') NOT NULL,
    valor_criterio    VARCHAR(200) NOT NULL,
    cuenta_destino_id INT UNSIGNED NOT NULL,
    prioridad         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activa            TINYINT(1) NOT NULL DEFAULT 0,  -- siempre 0 en Fase 1
    veces_aplicada    INT UNSIGNED NOT NULL DEFAULT 0,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_destino_id) REFERENCES cuentas_contables(id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. PLAN DE CUENTAS (seed) — catálogo global (empresa_id = NULL)
--
-- Fuente: PCGE Modificado 2019 (Resolución N.º 002-2019-EF/30, Consejo
-- Normativo de Contabilidad, vigente desde 01.01.2020), texto oficial:
-- https://www.mef.gob.pe/contenidos/conta_publ/documentac/PCGE_2019.pdf
-- Verificado cuenta por cuenta contra ese PDF el 2026-09-19.
--
-- Alcance: todas las cuentas usadas en las hojas operativas del Libro
-- Diario/Mayor/Balance real de un contador en ejercicio (BALANCE
-- VALENCIA-2025.xlsx: Inventario Inicial, Compras, Ventas, Planillas,
-- Honorarios, Banco, Caja), más lo que la spec del motor contable
-- menciona explícitamente. Se dejaron fuera, a propósito, las cuentas
-- analíticas avanzadas del Elemento 8 (Márgenes y Resultados del
-- Ejercicio) y las divisionarias extendidas del Elemento 9 (80x-96x)
-- que aparecen en el Balance de 8 columnas de esa plantilla — son
-- contabilidad analítica de explotación opcional que ni la spec ni el
-- flujo real de una EIRL pequeña necesitan para el cumplimiento mensual
-- de IGV/Renta; si hace falta más adelante, se agregan bajo demanda.
--
-- Ese mismo contador le confirmó al usuario que su plantilla tiene
-- inconsistencias — varias de sus cuentas usan numeración de un plan
-- contable anterior a la modificación 2019, o códigos que directamente
-- no existen. El criterio del proyecto es corregir hacia el código
-- oficial vigente, nunca replicar el error (ver nota de cada cuenta
-- afectada abajo).
-- ============================================================

-- Elemento 1 — Activo disponible y exigible
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('10',  'Efectivo y equivalentes de efectivo',              2, NULL, 'deudora', 'activo'),
('101', 'Caja',                                              3, '10', 'deudora', 'activo'),
('104', 'Cuentas corrientes en instituciones financieras',   3, '10', 'deudora', 'activo'),
('12',  'Cuentas por cobrar comerciales - Terceros',         2, NULL, 'deudora', 'activo'),
('121', 'Facturas, boletas y otros comprobantes por cobrar', 3, '12', 'deudora', 'activo'),
('122', 'Anticipos de Clientes',                             3, '12', 'deudora', 'activo'),
('123', 'Letras por Cobrar',                                 3, '12', 'deudora', 'activo'),
('14',  'Cuentas por Cobrar al Personal, a los Accionistas (Socios), Directores y Gerentes', 2, NULL, 'deudora', 'activo'),
('141', 'Personal',                                          3, '14', 'deudora', 'activo'),
('19',  'Estimación de Cuentas de Cobranza Dudosa',          2, NULL, 'acreedora', 'activo'),  -- contra-activo: reduce el saldo de las cuentas por cobrar
('191', 'Cuentas por Cobrar Comerciales - Terceros',         3, '19', 'acreedora', 'activo');

-- Elemento 2 — Existencias
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('20',  'Mercaderías',                                     2, NULL, 'deudora', 'activo'),
('201', 'Mercaderías Manufacturadas',                      3, '20', 'deudora', 'activo'),
('21',  'Productos Terminados',                            2, NULL, 'deudora', 'activo'),
('211', 'Productos Terminados',                            3, '21', 'deudora', 'activo'),
('23',  'Productos en Proceso',                            2, NULL, 'deudora', 'activo'),
('231', 'Productos en Proceso',                             3, '23', 'deudora', 'activo'),
('24',  'Materias Primas',                                 2, NULL, 'deudora', 'activo'),
('241', 'Materias Primas',                                  3, '24', 'deudora', 'activo'),
('25',  'Materiales Auxiliares, Suministros y Repuestos',  2, NULL, 'deudora', 'activo'),
('251', 'Materiales Auxiliares',                            3, '25', 'deudora', 'activo'),
('26',  'Envases y Embalajes',                             2, NULL, 'deudora', 'activo'),  -- el nombre oficial de "26" es Envases y Embalajes, no "existencias/suministros" genérico; si el giro real de una empresa es otro, su inventario debería registrarse en 20/24/25 según corresponda, no forzarlo aquí
('261', 'Envases',                                         3, '26', 'deudora', 'activo');

-- Elemento 3 — Activo inmovilizado
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('33',  'Propiedades, Planta y Equipo',                    2, NULL, 'deudora', 'activo'),  -- el nombre del elemento cambió en el PCGE Modificado 2019 (antes "Inmuebles, Maquinaria y Equipo")
('331', 'Terrenos',                                        3, '33', 'deudora', 'activo'),
('333', 'Maquinaria y Equipo de Explotación',              3, '33', 'deudora', 'activo'),
('334', 'Unidades de Transporte',                          3, '33', 'deudora', 'activo'),
('335', 'Muebles y Enseres',                               3, '33', 'deudora', 'activo'),
('336', 'Equipos Diversos',                                3, '33', 'deudora', 'activo'),
('38',  'Otros Activos',                                   2, NULL, 'deudora', 'activo'),
('381', 'Bienes de Arte y Cultura',                        3, '38', 'deudora', 'activo'),  -- el Diario de AVIMAS ("383", que no existe) y el Balance de Valencia (que reusa "381" como "Otros Activos" genérico) NO corresponden al significado oficial de esta subcuenta (bienes de arte/cultura específicamente) — confirmar con cada contador a qué activo real corresponde ese monto antes de asentarlo aquí
('39',  'Depreciación, Amortización y Agotamiento Acumulados', 2, NULL, 'acreedora', 'activo'),
('395', 'Depreciación Acumulada Propiedad, Planta y Equipo', 3, '39', 'acreedora', 'activo');  -- ambos Excel usan "391" o "393" para esto; el código vigente de depreciación acumulada de la cuenta 33 es 395

-- Elemento 4 — Pasivo por pagar
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('40',   'Tributos, Contraprestaciones y Aportes al Sistema Público de Pensiones y de Salud por Pagar', 2, NULL, 'acreedora', 'pasivo'),
('401',  'Gobierno Nacional',                               3, '40', 'acreedora', 'pasivo'),
('4011', 'Impuesto General a las Ventas (IGV)',             4, '401','acreedora', 'pasivo'),  -- los Excel lo anotan como "401.1"; el código oficial sin punto es 4011
('4017', 'Impuesto a la Renta',                             4, '401','acreedora', 'pasivo'),
('4018', 'Otros Impuestos y Contraprestaciones',            4, '401','acreedora', 'pasivo'),  -- el Excel de Valencia usa "401.9"/"4019", que no existe; el catch-all oficial de este nivel es 4018
('403',  'Instituciones Públicas',                          3, '40', 'acreedora', 'pasivo'),
('4031', 'ESSALUD',                                         4, '403','acreedora', 'pasivo'),  -- AVIMAS lo tenía como "4030" bajo 401; el código oficial es 4031 bajo 403
('4032', 'ONP',                                             4, '403','acreedora', 'pasivo'),  -- AVIMAS lo tenía como "4031" bajo 401; el código oficial es 4032 bajo 403
('409',  'Otros Costos Administrativos e Intereses',        4, '401','acreedora', 'pasivo'),
-- NOTA: "4082" (Diario de AVIMAS) no existe en el PCGE 2019 — era la divisionaria
-- "Entidades prestadoras de servicios de salud - cuenta de terceros" del elemento
-- 408, ELIMINADO en la modificación 2019. Requiere que el contador aclare a qué
-- obligación real correspondía ese monto históricamente.
('41',   'Remuneraciones y Participaciones por Pagar',      2, NULL, 'acreedora', 'pasivo'),
('411',  'Remuneraciones por Pagar',                        3, '41', 'acreedora', 'pasivo'),
('415',  'Beneficios Sociales de los Trabajadores por Pagar', 3, '41', 'acreedora', 'pasivo'),  -- el Excel de Valencia lo llama solo "CTS"; 415 es el nombre completo, CTS es su divisionaria 4151
('417',  'Administradoras de Fondos de Pensiones',          3, '41', 'acreedora', 'pasivo'),  -- ambos Excel lo tienen como "407" bajo el elemento 40; en el PCGE 2019 se movió al elemento 41
('42',   'Cuentas por Pagar Comerciales - Terceros',        2, NULL, 'acreedora', 'pasivo'),
('421',  'Facturas, Boletas y otros Comprobantes por Pagar', 3, '42', 'acreedora', 'pasivo'),
('423',  'Letras por Pagar',                                3, '42', 'acreedora', 'pasivo'),
('424',  'Honorarios por Pagar',                            3, '42', 'acreedora', 'pasivo'),
('45',   'Obligaciones Financieras',                        2, NULL, 'acreedora', 'pasivo'),
('451',  'Préstamos de Instituciones Financieras y Otras Entidades', 3, '45', 'acreedora', 'pasivo'),
('46',   'Cuentas por Pagar Diversas - Terceros',           2, NULL, 'acreedora', 'pasivo'),
('461',  'Reclamaciones de Terceros',                       3, '46', 'acreedora', 'pasivo'),
('469',  'Otras Cuentas por Pagar Diversas',                3, '46', 'acreedora', 'pasivo'),
('47',   'Cuentas por Pagar Diversas - Relacionadas',       2, NULL, 'acreedora', 'pasivo'),
('471',  'Préstamos',                                       3, '47', 'acreedora', 'pasivo'),  -- el nombre de la subcuenta 471 es "Préstamos", no una repetición del nombre del elemento 47
('49',   'Pasivo Diferido',                                 2, NULL, 'acreedora', 'pasivo'),
('491',  'Impuesto a la Renta Diferido',                    3, '49', 'acreedora', 'pasivo');

-- Elemento 5 — Patrimonio Neto
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('50',   'Capital',                                         2, NULL, 'acreedora', 'patrimonio'),
('501',  'Capital Social',                                  3, '50', 'acreedora', 'patrimonio'),
-- NOTA: "509" (Diario de AVIMAS) no existe como subcuenta de 50 en el PCGE 2019
-- (solo tiene 501 y 502). "Capital adicional" es en realidad el elemento 52
-- completo (CAPITAL ADICIONAL), no una subcuenta de 50. Requiere reclasificar
-- ese saldo de apertura histórico al elemento correcto.
('59',   'Resultados Acumulados',                           2, NULL, 'acreedora', 'patrimonio'),
('591',  'Utilidades no Distribuidas',                      3, '59', 'acreedora', 'patrimonio'),
('5911', 'Utilidad del Ejercicio',                          4, '591','acreedora', 'patrimonio'),
('5912', 'Pérdida del Ejercicio',                           4, '591','deudora',   'patrimonio');

-- Elemento 6 — Gastos por Naturaleza
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('60',   'Compras',                                         2, NULL, 'deudora', 'gasto'),
('601',  'Mercaderías',                                     3, '60', 'deudora', 'gasto'),
('602',  'Materias Primas',                                 3, '60', 'deudora', 'gasto'),
('603',  'Materiales Auxiliares, Suministros y Repuestos',  3, '60', 'deudora', 'gasto'),  -- tanto AVIMAS como Valencia registran "Suministros" directo en esta cuenta (no usan la divisionaria 6032); se mantiene así por ser el uso real
('604',  'Envases y Embalajes',                             3, '60', 'deudora', 'gasto'),
('609',  'Costos Vinculados con las Compras',               3, '60', 'deudora', 'gasto'),
('61',   'Variación de Inventarios',                        2, NULL, 'deudora', 'gasto'),  -- nombre actualizado en PCGE 2019 (antes "Variación de Existencias")
('611',  'Mercaderías',                                     3, '61', 'deudora', 'gasto'),
('612',  'Materias Primas',                                 3, '61', 'deudora', 'gasto'),
('613',  'Materiales Auxiliares, Suministros y Repuestos',  3, '61', 'deudora', 'gasto'),  -- el Excel de Valencia usa "616" para esto en el asiento de destino de Compras; ese código no existe, el vigente es 613
('614',  'Envases y Embalajes',                             3, '61', 'deudora', 'gasto'),
('62',   'Gastos de Personal y Directores',                 2, NULL, 'deudora', 'gasto'),  -- nombre actualizado en PCGE 2019 (antes "...Personal, Directores y Gerentes")
('621',  'Remuneraciones',                                  3, '62', 'deudora', 'gasto'),
('625',  'Atención al Personal',                            3, '62', 'deudora', 'gasto'),  -- gasto de bienestar de personal (comidas, etc.) que sí puede originarse en una factura de compra
('627',  'Seguridad, Previsión Social y Otras Contribuciones', 3, '62', 'deudora', 'gasto'),
('629',  'Beneficios Sociales de los Trabajadores',         3, '62', 'deudora', 'gasto'),
('63',   'Gastos de Servicios Prestados por Terceros',      2, NULL, 'deudora', 'gasto'),
('631',  'Transporte, Correos y Gastos de Viaje',           3, '63', 'deudora', 'gasto'),
('632',  'Asesoría y Consultoría',                          3, '63', 'deudora', 'gasto'),
('634',  'Mantenimiento y Reparaciones',                    3, '63', 'deudora', 'gasto'),
('635',  'Alquileres',                                      3, '63', 'deudora', 'gasto'),
('636',  'Servicios Básicos',                               3, '63', 'deudora', 'gasto'),
('637',  'Publicidad, Publicaciones, Relaciones Públicas',  3, '63', 'deudora', 'gasto'),
('638',  'Servicios de Contratistas',                       3, '63', 'deudora', 'gasto'),
('639',  'Otros Servicios Prestados por Terceros',          3, '63', 'deudora', 'gasto'),
('64',   'Gastos por Tributos',                             2, NULL, 'deudora', 'gasto'),
-- NOTA: ambos Excel usan "640"/"649" como si fueran subcuentas fijas de "Tributos" —
-- ninguna de las dos existe. El elemento 64 en realidad se subdivide por nivel de
-- gobierno y tipo de deuda:
('641',  'Gobierno Nacional',                               3, '64', 'deudora', 'gasto'),
('642',  'Gobierno Regional',                                3, '64', 'deudora', 'gasto'),
('643',  'Gobierno Local',                                   3, '64', 'deudora', 'gasto'),
('644',  'Otros Gastos por Tributos',                        3, '64', 'deudora', 'gasto'),
('645',  'Gastos en Deuda Tributaria',                       3, '64', 'deudora', 'gasto'),
('6452', 'Intereses - Fraccionamiento',                      4, '645','deudora', 'gasto'),  -- esta es la cuenta oficial para el interés de un fraccionamiento tributario — resuelve en parte la duda de "8021"/"4082" que usa AVIMAS: el interés va aquí, el capital adeudado va en 401/46 como pasivo real
('65',   'Otros Gastos de Gestión',                         2, NULL, 'deudora', 'gasto'),
('651',  'Seguros',                                         3, '65', 'deudora', 'gasto'),
('653',  'Suscripciones',                                   3, '65', 'deudora', 'gasto'),
('656',  'Suministros',                                     3, '65', 'deudora', 'gasto'),  -- usar solo si el consumo NO pasó por Compras/Inventario (confirmar criterio con el contador); si vino de una compra, corresponde 603
('659',  'Otros Gastos de Gestión',                         3, '65', 'deudora', 'gasto'),
-- NOTA: "663 Cargas Excepcionales" SÍ aparece en el Estado de G y P real de
-- Valencia — pero no existe en ningún elemento del PCGE 2019 vigente. Se
-- confirmó que su propia plantilla (un trabajo universitario, no un sistema
-- profesional validado) usa terminología de un plan de cuentas peruano anterior
-- a la modificación 2019. Por criterio del proyecto, ese gasto se reclasifica a
-- 659 Otros Gastos de Gestión — no se agrega "663" al catálogo global.
('67',   'Gastos Financieros',                              2, NULL, 'deudora', 'gasto'),
('671',  'Gastos en Operaciones de Endeudamiento y Otros',  3, '67', 'deudora', 'gasto'),
('673',  'Intereses por Préstamos y otras Obligaciones',    3, '67', 'deudora', 'gasto'),  -- el interés de un préstamo específico (lo que ambos Excel registran como "671 INTERESES") corresponde más precisamente aquí
('679',  'Otros Gastos Financieros',                        3, '67', 'deudora', 'gasto'),
('68',   'Valuación y Deterioro de Activos y Provisiones',  2, NULL, 'deudora', 'gasto'),
('684',  'Depreciación de Propiedad, Planta y Equipo',      3, '68', 'deudora', 'gasto'),
('6841', 'Depreciación de Propiedad, Planta y Equipo - Costo', 4, '684','deudora', 'gasto'),  -- AVIMAS usa "683" para esto, que en el PCGE 2019 ya no significa depreciación de activo fijo (ahora es "arrendamiento operativo"); el código vigente es 684/6841
('69',   'Costo de Ventas',                                 2, NULL, 'deudora', 'gasto'),
('691',  'Mercaderías',                                     3, '69', 'deudora', 'gasto');

-- Elemento 7 — Ingresos
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('70',   'Ventas',                                          2, NULL, 'acreedora', 'ingreso'),
('701',  'Mercaderías',                                     3, '70', 'acreedora', 'ingreso'),  -- confirmado: uso agregado, tal cual lo hace el Diario de AVIMAS. Las divisionarias oficiales 7011/7012 distinguen exportación vs. venta local, NO gravado vs. no gravado (Valencia y la spec usan 7011/7012 así, mal) — si se necesita esa distinción para el cálculo de IGV, se resuelve con divisionarias propias bajo 701 o con los campos exonerado/inafecto que ya trae el dato de SIRE, no reusando 7011/7012
('702',  'Productos Terminados',                            3, '70', 'acreedora', 'ingreso'),  -- venta de bienes fabricados por la propia empresa (manufactura), distinto de 701 que es reventa de mercadería comprada
('703',  'Servicios Terminados',                            3, '70', 'acreedora', 'ingreso'),  -- Valencia y la spec usan "704" para Prestación de Servicios; en el PCGE 2019 ese código pasó a significar "Subproductos, desechos y desperdicios" — el código vigente para servicios es 703
('704',  'Subproductos, Desechos y Desperdicios',           3, '70', 'acreedora', 'ingreso'),  -- venta de remanentes/sobrantes del proceso productivo (empresas de manufactura)
('75',   'Otros Ingresos de Gestión',                       2, NULL, 'acreedora', 'ingreso'),
('759',  'Otros Ingresos de Gestión',                       3, '75', 'acreedora', 'ingreso'),
('77',   'Ingresos Financieros',                            2, NULL, 'acreedora', 'ingreso'),
('779',  'Otros Ingresos Financieros',                      3, '77', 'acreedora', 'ingreso');

-- Elemento 9 — Cuentas de destino / función (no son de balance ni resultado por
-- naturaleza). El propio PCGE 2019 deja este elemento a criterio de cada entidad
-- ("Contabilidad Analítica de Explotación: Costos de Producción y Gastos por
-- Función" — no hay una numeración oficial fija de 92/94/95/96). La convención
-- usada aquí coincide con lo que usan tanto AVIMAS como Valencia, así que se
-- mantiene tal cual. El Balance de 8 columnas de Valencia además usa divisionarias
-- extendidas (943, 953, etc. en vez de 94/95) — se normalizan a 94/95/96 llanas,
-- sin las divisionarias analíticas adicionales, por estar fuera del alcance de
-- Fase 1.
INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo) VALUES
('79',   'Cargas Imputables a Cuentas de Costos y Gastos',  2, NULL, 'acreedora', 'destino'),
('791',  'Cargas Imputables a Cuentas de Costos y Gastos',  3, '79', 'acreedora', 'destino'),
('92',   'Costo de Producción',                             2, NULL, 'deudora',   'destino'),
('94',   'Gastos de Administración',                        2, NULL, 'deudora',   'destino'),
('95',   'Gastos de Venta',                                 2, NULL, 'deudora',   'destino'),
('96',   'Gastos Financieros',                              2, NULL, 'deudora',   'destino');

-- Cuenta de control interna (no es PCGE estándar, es una cuenta de seguimiento
-- propia del contador de AVIMAS para fraccionamiento tributario; confirmado que
-- no existe en el elemento de Cuentas de Orden del PCGE tampoco). El capital
-- adeudado de un fraccionamiento va como pasivo real en 401 (Gobierno Nacional)
-- o 46 (Cuentas por Pagar Diversas) según corresponda, y el interés en 6452
-- (ver Elemento 6 arriba) — no se agrega "8021" al catálogo global. Si un
-- contador insiste en llevar ese control interno adicional, se le da de alta
-- como cuenta propia de su empresa (empresa_id no nulo).
-- ('8021', 'Fraccionamiento tributario por pagar (control interno)', 2, NULL, 'acreedora', 'pasivo')

-- ============================================================
-- FUERA DE ALCANCE A PROPÓSITO (no se cargan en Fase 0):
--   - Elemento 8 completo (Márgenes y Resultados del Ejercicio: 80-89) y las
--     divisionarias analíticas extendidas de Elemento 9 (911, 921, 942-969) que
--     aparecen en el Balance de 8 columnas de Valencia — contabilidad analítica
--     avanzada que ni la spec ni el cumplimiento mensual de IGV/Renta de una
--     EIRL pequeña requieren. Se agregan bajo demanda si un caso real lo pide.
--
-- PENDIENTES DE CONFIRMAR CON EL CONTADOR (no bloquean Fase 0, pero si no se
-- resuelven antes de generar asientos reales, esos montos históricos quedarán
-- mal clasificados):
--   1. Cuenta "383" del Diario de AVIMAS — no existe en el PCGE 2019.
--   2. Cuenta "8021" — de dónde sale el fraccionamiento tributario real (ver
--      nota arriba: capital → 401/46, interés → 6452).
--   3. Si el giro de AVIMAS es realmente "Envases y Embalajes" (26) o si su
--      inventario debería ir en "Mercaderías" (20) u otra cuenta del Elemento 2.
--   4. Reclasificar el saldo de apertura patrimonial que AVIMAS registró en
--      "509" hacia el elemento 52 (Capital Adicional) o hacia 501 (Capital
--      Social), según corresponda.
--   5. Confirmar con Valencia a qué activo real corresponde el monto que su
--      plantilla registra genéricamente en "381 Otros Activos".
-- ============================================================

-- ============================================================
-- 7. TIPOS DE GASTO (seed) — clasificación en lenguaje llano para la
-- pantalla de imputación manual (Fase 1). El contador elige por
-- "nombre_visible", nunca por código PCGE de memoria. Mapeado contra el
-- catálogo ya corregido de la sección 6 — usa subconsultas por código
-- en vez de IDs fijos porque el orden de inserción puede variar.
-- ============================================================

-- Compras
-- (UNION ALL en vez de la sintaxis "VALUES ... AS alias(cols)" por
-- compatibilidad: esta última requiere MySQL 8.0.19+ y el servidor real
-- del proyecto corre MariaDB 10.11, donde ese soporte es menos fiable.)
INSERT INTO tipos_gasto (nombre_visible, cuenta_id, aplica_a, orden)
SELECT v.nombre_visible, c.id, 'compra', v.orden FROM (
    SELECT 'Mercadería' AS nombre_visible, '601' AS codigo, 10 AS orden
    UNION ALL SELECT 'Materia Prima',                          '602', 20
    UNION ALL SELECT 'Suministros',                             '603', 30
    UNION ALL SELECT 'Atención al Personal',                    '625', 40
    UNION ALL SELECT 'Transporte',                               '631', 50
    UNION ALL SELECT 'Asesoría y Consultoría (Honorarios)',      '632', 60
    UNION ALL SELECT 'Reparación y Mantenimiento',                '634', 70
    UNION ALL SELECT 'Alquileres',                                '635', 80
    UNION ALL SELECT 'Servicios Básicos',                         '636', 90
    UNION ALL SELECT 'Publicidad',                                '637', 100
    UNION ALL SELECT 'Servicios de Contratistas',                 '638', 110
    UNION ALL SELECT 'Otros Servicios',                           '639', 120
    UNION ALL SELECT 'Seguros',                                   '651', 130
    UNION ALL SELECT 'Suscripciones',                             '653', 140
    UNION ALL SELECT 'Suministros Diversos (fuera de inventario)', '656', 150
    UNION ALL SELECT 'Otras Cargas de Gestión',                   '659', 160
    UNION ALL SELECT 'Activo Fijo (Maquinaria y Equipo)',         '333', 170
) AS v
JOIN cuentas_contables c ON c.codigo = v.codigo AND c.empresa_id IS NULL;

-- Ventas
INSERT INTO tipos_gasto (nombre_visible, cuenta_id, aplica_a, orden)
SELECT v.nombre_visible, c.id, 'venta', v.orden FROM (
    SELECT 'Mercadería' AS nombre_visible, '701' AS codigo, 10 AS orden
    UNION ALL SELECT 'Productos Terminados (manufactura)', '702', 15
    UNION ALL SELECT 'Servicios', '703', 20
    UNION ALL SELECT 'Subproductos y Desechos', '704', 25
) AS v
JOIN cuentas_contables c ON c.codigo = v.codigo AND c.empresa_id IS NULL;

-- ============================================================
-- 8. SALDOS DE APERTURA (Inventario Inicial)
-- Completa la sección "Períodos" de la spec, que agrupa esta tabla junto
-- a periodos_contables. Se llena manualmente al arrancar una empresa en
-- el sistema; en años siguientes se genera sola desde el cierre del
-- período anterior (ver cierres_periodo en Fase 1).
-- ============================================================
CREATE TABLE saldos_apertura (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id  INT UNSIGNED NOT NULL,
    periodo_id  INT UNSIGNED NOT NULL,
    cuenta_id   INT UNSIGNED NOT NULL,
    debe        DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    haber       DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (periodo_id) REFERENCES periodos_contables(id) ON DELETE CASCADE,
    FOREIGN KEY (cuenta_id)  REFERENCES cuentas_contables(id),
    UNIQUE KEY uk_periodo_cuenta (periodo_id, cuenta_id)
) ENGINE=InnoDB;

-- NOTA: la validación "suma de activo = suma de pasivo + patrimonio" (2.1 de
-- la spec) no se hace aquí a nivel de base de datos (MySQL/MariaDB no valida
-- across-rows en un CHECK simple) — se implementa en la capa de servicio de
-- PHP antes de insertar, rechazando el guardado si no cuadra. Documentado
-- para que no se asuma que la tabla sola ya garantiza esa regla.
