# Motor Contable — Especificación técnica para GestiCont

## Contexto

Este documento especifica el **motor contable de partida doble** que falta implementar
en **GestiCont**, la plataforma SaaS multi-tenant existente para contadores peruanos que
ya tiene:
- Arquitectura multi-tenant funcionando (multi-empresa) con al menos un cliente.
- Integración SIRE (SUNAT) funcionando para extraer registros de compras y ventas.

La lógica contable aquí descrita está basada en el análisis de una plantilla Excel real
(`BALANCE_VALENCIA-2025.xlsx`, protegida con clave "contabilidad") que implementa un
módulo contable manual siguiendo el **PCGE** (Plan Contable General Empresarial de Perú),
para un contribuyente persona natural con negocio. Esa plantilla es la referencia de
la lógica de negocio a portar — **no** el modelo de datos a replicar literalmente (tiene
defectos de diseño que se corrigen aquí, ver sección "Correcciones respecto al Excel
original").

**Primer paso para Claude Code: auditar el proyecto actual de GestiCont** (tablas
existentes, endpoints SIRE ya construidos, estructura de tenant/empresa) y mapear qué de
lo siguiente ya existe, qué hay que adaptar, y qué hay que construir desde cero, antes de
escribir código nuevo.

---

## Alcance de Fase 1 (lo que se implementa ahora)

- Carga **manual** del Inventario Inicial (saldos de apertura), con fecha 1 de enero del
  año en curso. El saldo de apertura de este año es el balance que el contribuyente
  declaró al cierre del año anterior.
- Importación **automática** de Compras y Ventas vía la integración SIRE ya existente.
- Clasificación (imputación a cuenta contable) **100% manual**: cada comprobante
  importado queda "pendiente de imputar" y el usuario, con un botón/selector, le asigna
  el tipo de gasto/ingreso (Mercadería, Materia Prima, Honorarios, Alquileres, etc.), que
  internamente mapea a una cuenta PCGE.
- Planillas: si existe API o documento de declaración (PLAME) disponible para importar,
  se automatiza la extracción; si no, captura manual. Mismo patrón dual que compras/ventas.
- Generación automática de asientos contables (partida doble) a partir de los documentos
  ya imputados.
- Libro Diario, Libro Mayor, Balance de Comprobación y Estados Financieros, calculados
  automáticamente (no como hojas/tablas separadas que hay que "armar").

### Explícitamente fuera de Fase 1 (Fase 2)

- Motor de reglas de auto-clasificación (por RUC, palabra clave, tipo de comprobante).
- Sugerencias con nivel de confianza / auto-imputación.
- Aprendizaje de reglas a partir del historial de imputaciones manuales.

**Nota de diseño para no migrar después:** aunque en Fase 1 toda imputación es manual,
la tabla `imputaciones` debe tener el campo `regla_id` **nullable desde el día uno**
(siempre `NULL` en Fase 1). Así Fase 2 no requiere alterar la tabla ni migrar datos
históricos.

---

## Flujo de datos (orden real de generación)

```
Inventario Inicial (saldos de apertura, manual, 1 de enero)
        +
Compras / Ventas (SIRE, automático) ──► Imputación a cuenta (manual, botón)
        +
Planillas (PLAME si existe / manual)
        │
        ▼
   Generación de Asientos (partida doble, automático)
        │
        ▼
   LIBRO DIARIO  (fuente única de verdad — ver corrección abajo)
        │
        ▼
   LIBRO MAYOR   (agregación automática del Diario por cuenta)
        │
        ▼
   BALANCE DE COMPROBACIÓN (8 columnas clásicas, calculado)
        │
        ├──► Balance General (Estado de Situación Financiera)
        ├──► Estado de Resultados (Pérdidas y Ganancias)
        ├──► Estado de Cambios en el Patrimonio
        └──► Estado de Flujo de Efectivo
```

## Correcciones respecto al Excel original

1. **El Libro Diario debe ser la fuente única de verdad.** En el Excel original, el Mayor
   jalaba directo de una hoja de "asientos centralizados", saltándose el Diario (que
   quedaba casi vacío). En el sistema, cada compra/venta/planilla imputada debe insertar
   sus líneas directamente en el Diario, y el Mayor/Balance se calculan **siempre**
   agregando el Diario — nunca por un camino paralelo. Esto da trazabilidad completa:
   cada saldo del Mayor debe ser auditable hasta el asiento y el comprobante origen.
2. **Los porcentajes de distribución de gastos (30% administración / 70% ventas) y la
   tasa de Impuesto a la Renta (30%) deben ser parámetros configurables** por empresa y
   por período, no valores fijos en código (en el Excel estaban quemados en las
   fórmulas).
3. **El cierre de período debe generar automáticamente el saldo de apertura del período
   siguiente.** En el Excel esto era copiar y pegar los saldos finales a mano cada año —
   justo el tipo de error que se busca eliminar al sistematizar.

---

## Modelo de datos

Todas las tablas llevan `empresa_id` para colgar del tenant existente en GestiCont.

### Catálogo y configuración

**`cuentas_contables`**
- `id`, `codigo` (PCGE, 2/3/4 dígitos), `nombre`, `nivel`, `naturaleza` (deudora/acreedora),
  `tipo` (activo/pasivo/patrimonio/ingreso/gasto)
- Catálogo global compartido entre tenants; `empresa_id` nulable para cuentas propias de
  un cliente específico.

**`tipos_gasto`** (para la UI de clasificación en lenguaje llano)
- `id`, `nombre_visible` (ej. "Mercadería", "Materia Prima", "Honorarios",
  "Transporte", "Alquileres", "Servicios Básicos"), `cuenta_id`
- El usuario clasifica por `nombre_visible`, no por código PCGE de memoria.

**`parametros_contables`**
- `empresa_id`, `periodo_id`, `tasa_ir`, `pct_gastos_admin`, `pct_gastos_ventas`, `moneda`

**`reglas_imputacion`** (estructura lista para Fase 2, sin lógica de negocio en Fase 1)
- `id`, `empresa_id`, `tipo_criterio` ('ruc_contraparte' | 'palabra_clave' |
  'tipo_comprobante' | 'ruc_y_palabra'), `valor_criterio`, `cuenta_destino_id`,
  `prioridad`, `activa`, `veces_aplicada`

### Períodos

**`periodos_contables`**
- `empresa_id`, `anio`, `estado` (abierto/cerrado)

**`saldos_apertura`**
- `empresa_id`, `periodo_id`, `cuenta_id`, `debe`, `haber`
- Se llena manualmente al arrancar; luego se genera solo en el cierre del período anterior.

### Documentos fuente

**`documentos_sire`**
- `empresa_id`, `periodo_id`, `tipo` (compra/venta), `ruc_contraparte`, `razon_social`,
  `tipo_comprobante`, `serie`, `numero`, `fecha_emision`, `moneda`, `monto_base`, `igv`,
  `total`, `estado_imputacion` (pendiente/imputado/parcial)

**`planillas`**
- `empresa_id`, `periodo_id`, `trabajador`, `sueldo`, `essalud`, `onp_afp`, `origen`
  (import_plame / manual)

**`movimientos_caja`** / **`movimientos_banco`**
- Captura manual mientras no se automatice esa parte.

**`imputaciones`**
- `documento_sire_id`, `cuenta_id`, `monto`, `regla_id` (nullable, siempre NULL en
  Fase 1), `usuario_id`
- Permite dividir un documento entre varias cuentas si aplica (no siempre es 1 a 1).
- Guardar `usuario_id` es obligatorio para trazabilidad de quién clasificó qué.

### Núcleo — Libro Diario

**`asientos`** (cabecera)
- `empresa_id`, `periodo_id`, `correlativo`, `fecha`, `glosa`, `origen`
  (compra/venta/planilla/caja/manual/cierre), `documento_id` (referencia al origen)

**`asientos_detalle`** (líneas)
- `asiento_id`, `cuenta_id`, `debe`, `haber`
- Validación a nivel de servicio: **no se persiste un asiento si Σdebe ≠ Σhaber**.

### Todo lo demás es cálculo, no tabla física

Libro Mayor, Balance de Comprobación, Balance General, Estado de Resultados, Estado de
Cambios en el Patrimonio y Flujo de Efectivo se generan por consulta/vista agregando
`asientos_detalle` por cuenta y período. No materializar como tablas propias salvo
snapshots inmutables tras el cierre de un período.

### Cierre

**`cierres_periodo`**
- `empresa_id`, `periodo_id`, `fecha_cierre`, `usuario_id`, `resultado_ejercicio`
- Al ejecutarse, genera automáticamente `saldos_apertura` del período siguiente.

---

## Pantalla de clasificación (Fase 1 — UX)

- Lista de `documentos_sire` con `estado_imputacion = 'pendiente'`.
- Por cada documento: selector de `tipo_gasto` (nombre visible, no código PCGE) + botón
  para confirmar → crea el registro en `imputaciones` y cambia `estado_imputacion` a
  `imputado`.
- **Mejora de UX barata (no es el motor de reglas de Fase 2):** al abrir un documento de
  un RUC que ya se clasificó antes, precargar como sugerencia la última cuenta usada para
  ese RUC (`SELECT cuenta_id FROM imputaciones i JOIN documentos_sire d ON ... WHERE
  d.ruc_contraparte = X ORDER BY fecha DESC LIMIT 1`). El usuario sigue confirmando con
  el botón; esto es solo una consulta, no una regla.

---

## Instrucción para Claude Code

1. Revisar el proyecto actual de GestiCont: tablas existentes, estructura de
   tenant/empresa, endpoints SIRE ya implementados.
2. Mapear cada tabla/entidad de este documento contra lo que ya existe: reutilizar,
   adaptar o crear según corresponda — no duplicar lo que ya funciona.
3. Implementar lo faltante en el orden del flujo de datos: Inventario Inicial →
   Imputación manual → Generación de Asientos → Libro Diario → Libro Mayor → Balance de
   Comprobación → Estados Financieros → Cierre de Período.
4. Respetar las correcciones de diseño respecto al Excel original (Diario como fuente
   única, parámetros configurables, cierre con rollover automático).
5. Dejar la estructura de `reglas_imputacion` e `imputaciones.regla_id` lista para Fase 2
   sin implementar lógica de auto-clasificación todavía.
