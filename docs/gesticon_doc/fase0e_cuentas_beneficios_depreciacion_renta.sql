-- ============================================================
-- GestiCont — Motor Contable, FASE 0e
-- Agrega al catálogo las cuentas que piden las plantillas de asientos manuales del
-- rediseño (provisión de CTS, gratificaciones y vacaciones, depreciación, impuesto a
-- la renta). Verificadas una por una contra el PCGE oficial del MEF
-- (https://www.mef.gob.pe/contenidos/conta_publ/documentac/PCGE_2019.pdf):
--   4114 Gratificaciones por pagar · 4115 Vacaciones por pagar · 4151 Compensación por
--   tiempo de servicios · 6214 Gratificaciones · 6215 Vacaciones · 6291 Compensación por
--   tiempo de servicio · 881 Impuesto a las ganancias – Corriente.
--   684 Depreciación de propiedad, planta y equipo: OJO, el diseño propone la 681, pero en
--   el PCGE oficial la 681 es "Depreciación de propiedades de inversión"; la de propiedad,
--   planta y equipo es la 684 (contrapartida: 395 Depreciación acumulada).
-- Nota: en el PCGE 2019 el elemento 9 (94, 95, 96…) queda a criterio de cada entidad; se
-- mantienen los códigos 94/95/96 que ya usa el sistema.
--
-- Solo agrega; si una cuenta ya existe no se duplica (se puede ejecutar más de una vez).
-- ============================================================

INSERT INTO cuentas_contables (codigo, nombre, nivel, padre_codigo, naturaleza, tipo)
SELECT v.codigo, v.nombre, v.nivel, v.padre, v.naturaleza, v.tipo FROM (
    SELECT '88'   AS codigo, 'Impuesto a la Renta'                          AS nombre, 2 AS nivel, NULL AS padre, 'deudora'   AS naturaleza, 'gasto'  AS tipo UNION ALL
    SELECT '881',            'Impuesto a las Ganancias – Corriente',                   3,          '88',          'deudora',                  'gasto'         UNION ALL
    SELECT '684',            'Depreciación de Propiedad, Planta y Equipo',             3,          '68',          'deudora',                  'gasto'         UNION ALL
    SELECT '6214',           'Gratificaciones',                                        4,          '621',         'deudora',                  'gasto'         UNION ALL
    SELECT '6215',           'Vacaciones',                                             4,          '621',         'deudora',                  'gasto'         UNION ALL
    SELECT '6291',           'Compensación por Tiempo de Servicio',                    4,          '629',         'deudora',                  'gasto'         UNION ALL
    SELECT '4114',           'Gratificaciones por Pagar',                              4,          '411',         'acreedora',                'pasivo'        UNION ALL
    SELECT '4115',           'Vacaciones por Pagar',                                   4,          '411',         'acreedora',                'pasivo'        UNION ALL
    SELECT '4151',           'Compensación por Tiempo de Servicios',                   4,          '415',         'acreedora',                'pasivo'
) v
WHERE NOT EXISTS (SELECT 1 FROM cuentas_contables c WHERE c.codigo = v.codigo AND c.empresa_id IS NULL);
