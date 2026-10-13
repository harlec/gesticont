# Handoff: Rediseño GestiCont (navegación, Inicio, Mis empresas, Comprobantes)

## Overview
Rediseño de GestiCont, software contable peruano usado por **contadores que llevan muchas empresas**, en su mayoría **personas mayores**. Objetivos:
1. Que lo más urgente (vencimientos SUNAT, comprobantes por clasificar) sea lo primero que se ve.
2. Cambiar de empresa rápido (buscador + Ctrl+K).
3. Un solo selector de período global (antes había dos que se contradecían).
4. Legibilidad alta: letra grande, contraste alto, botones ≥ 44px, texto en vez de iconos.

## About the Design Files
`GestiCont Inicio.dc.html` es un **prototipo de referencia en HTML** (se abre directo en el navegador junto con `support.js`). No es código de producción. La tarea es **recrear este diseño en el código existente de GestiCont** con sus frameworks, rutas, componentes y API reales. Los datos del prototipo (empresas, comprobantes, montos, fechas) son **ficticios** y se generan en el cliente; en producción vienen del backend.

Lógica del prototipo: dentro del `<script data-dc-script>` del archivo (clase `Component`, método `renderVals()` y `compVals()`). El marcado está entre `<x-dc>` y `</x-dc>`, todo con estilos inline.

## Fidelity
**Alta fidelidad.** Colores, tipografía, tamaños, espaciados, textos y comportamiento son finales. Recrear fielmente usando los componentes del código base.

---

## Design Tokens

### Colores
| Uso | Hex |
|---|---|
| Fondo app | `#f6f4ef` |
| Superficie (tarjetas, sidebar, header) | `#ffffff` |
| Borde principal | `#e2ded5` |
| Borde fila / divisor suave | `#efece5` |
| Borde de inputs/botones secundarios | `#d6d1c6` |
| Texto principal | `#1c1b19` |
| Texto secundario | `#5a5750` |
| Texto terciario oscuro | `#3d3b36` |
| Primario (azul) | `#1f4e79` |
| Primario hover | `#163a5c` |
| Primario suave (fondo seleccionado) | `#e6eef6` |
| Fila seleccionada / actual | `#f4f7fa` / `#eef3f8` |
| Borde botón secundario | `#b9c8d8` |
| Hover neutro | `#f0ede6` / `#efece5` |
| Éxito texto / fondo | `#1e6b3a` / `#e5f2e8` |
| Advertencia texto / fondo | `#7a4f00` / `#fbefd5` |
| Advertencia banner fondo / borde / texto | `#fdf6e7` / `#e8c98a` / `#5c3b00` |
| Peligro texto / fondo | `#a3261d` / `#fbe3e0` |
| Serie compras en gráfico | `#c98a3d` |
| Overlay modal | `rgba(28,27,25,0.38)` |
| Toast fondo | `#1c1b19` |

**Semáforo de vencimientos** (función `tone`): presentado → éxito; vencido (<0 días) → peligro; ≤7 días → advertencia; >7 días → azul suave (`#e6eef6`/`#1f4e79`).

### Tipografía
- **Atkinson Hyperlegible Next** (Google Fonts, 400/500/600/700): todo el texto.
- **Atkinson Hyperlegible Mono** (400/500): RUC y series de comprobantes.
- Montos: `font-variant-numeric: tabular-nums`.
- Base 17px, line-height 1.45.
- Escala: título página 26/700 (letter-spacing -0.01em) · título sección 22/700 · cifra grande 26–34/700 · cifra tarjeta 28/700 · texto 16–18 · etiquetas 15–16/600 secundario · chips 14–15/700 · mono 14–15.
- Nunca menos de 13px (solo atajos de teclado y etiqueta tipo de comprobante).

### Radios, sombras, tamaños
- Radios: tarjetas 16 · tarjetas pequeñas 12–14 · botones/inputs 10–12 · chips 999 · modal 18.
- Sombra modal: `0 24px 60px rgba(28,27,25,0.25)`. Toast: `0 10px 30px rgba(0,0,0,0.25)`. Nada más tiene sombra.
- Alto mínimo de controles: **44px** (primarios 50–54px). Checkboxes 20–22px.
- Espaciado: gap entre tarjetas 24 / 16 · padding tarjeta 24–28 · padding contenido 28×32 · max-width contenido 1360.

### Tamaño de letra (accesibilidad)
Control "Letra" en el header con 3 opciones: Normal (1), Grande (1.12), Muy grande (1.25). En el prototipo se aplica con `zoom` al contenedor raíz. En producción: escalar el `font-size` raíz (rem) y **guardar la preferencia por usuario**.

---

## Layout global
- **Sidebar fijo** izquierdo, 276px, blanco, borde derecho, sticky a 100vh, padding 22×16, gap 22:
  1. Logo: cuadro 34px azul con "G" + "GestiCont" 20/700.
  2. **Tarjeta de empresa actual** (botón): "Empresa actual", nombre 17/700, "RUC …" mono, separador, "Cambiar empresa" + atajo `Ctrl + K`. Abre el selector.
  3. Menú (solo texto, sin iconos), ítems de 46px: Inicio, Comprobantes, Caja, Contabilidad, Planillas, Honorarios, Apertura, Datos de la empresa. Activo: fondo `#e6eef6`, texto azul 700.
  4. Abajo: botón "Mis empresas" con subtítulo rojo de alerta ("N empresas con vencidos" o "Todo al día") y bloque de usuario (avatar iniciales, nombre, "Contador").
- **Header** sticky blanco, padding 14×32, wrap: título de página · selector de **Período** (‹ Mes Año ›, botones 44×44, deshabilitados con opacidad 0.35 en los extremos) · control **Letra** (A A A) · "Hoy: <fecha>".
- Se **eliminó** el selector de tema de la barra (si se conserva, va al menú de usuario).
- El período del header es **el único** selector de mes y lo usan todas las pantallas.

---

## Pantallas

### 1. Mis empresas (pantalla de entrada)
Lo primero que ve el contador al entrar.
- Fila de tarjetas de resumen (flex wrap, min 220px): con declaraciones vencidas (rojo) · vencen en los próximos 10 días (ámbar) · comprobantes por clasificar (ámbar) · sin certificado digital (rojo). Cifra 32/700 + etiqueta.
- Tabla: buscador (nombre o RUC, sin importar tildes) + nota "Ordenadas por vencimiento más cercano".
  - Columnas: Empresa (nombre + RUC · régimen) · Próximo vencimiento (fecha + chip semáforo con "Faltan N días" / "Vence mañana" / "Vence hoy" / "Vencido hace N días" / "Todo presentado") · Por presentar ("N de M") · Por clasificar · Certificado ("Activo" verde / "Falta subir" rojo) · botón "Abrir" ("Ir a inicio" si es la actual).
  - Orden: días al próximo vencimiento ascendente.
  - En pantallas angostas la tabla tiene min-width 900px y scroll horizontal (no comprimir columnas).

### 2. Selector de empresa (modal, Ctrl/Cmd + K)
- Modal de 680px arriba (padding-top 72), fondo con overlay; clic fuera o Esc cierra.
- Título "Cambiar de empresa" + "Cerrar". Input 54px con foco automático: "Escribe el nombre o RUC".
- Filtros chip: Todas (N) · Con vencidos (N) · Por clasificar (N).
- Resultados ordenados por vencimiento: nombre (+ "· actual"), RUC mono, a la derecha chip semáforo y "N por clasificar".
- Teclado: ↑/↓ mueve resaltado, Enter abre, Esc cierra. Hover también resalta.
- Pie: ayuda de teclado + "Ver todas mis empresas →".
- Al elegir: cambia empresa, va a Inicio, toast "Ahora trabajas en <empresa>".

### 3. Inicio (por empresa)
Fila 1 (flex wrap):
- **Vencimientos SUNAT** (2/3): cabecera con "Próximo: <fecha>" o "Tienes una declaración vencida", "N obligaciones pendientes", y un bloque grande con los días (42/700) coloreado por semáforo. Lista de obligaciones:
  - Registro de Ventas (SIRE), Registro de Compras (SIRE), IGV-Renta mensual (PDT 621), Planilla electrónica (PLAME) si la empresa tiene planilla; además el PDT del mes anterior si está vencido.
  - Cada fila: nombre 18/700, "Período X · vence el <fecha>", estado con punto de color, chip de días, botón de acción.
  - Estados: **bloqueado** ("Faltan clasificar N ventas", rojo → botón "Clasificar" que lleva a Comprobantes › Clasificar en el mes con pendientes) · **listo** ("Listo para presentar", verde → botón primario "Presentar") · **en espera** (PDT 621: "Primero presenta ventas y compras", sin botón) · **presentado** ("Presentado el …" → "Ver constancia").
  - Pie: "Fechas según el cronograma SUNAT para RUC terminado en N." **Las fechas del prototipo son de ejemplo; usar el cronograma oficial.**
- Columna derecha (1/3): tarjeta **Por clasificar** (cifra + explicación + "Clasificar ahora", o "Todo clasificado") y **Estado de la empresa** (balance cuadra, certificado digital, última sincronización).

Fila 2: **Resumen de <mes>** con 4 tarjetas: Ventas, Compras, IGV por pagar **o** "Saldo a favor de IGV" (nunca un número negativo sin explicar), Resultado estimado. Si el mes no tiene datos: estado vacío "Aún no hay comprobantes de <mes>" + "Sincronizar con SUNAT".

Fila 3: **Año** (gráfico de barras dobles ventas/compras por mes; tocar un mes cambia el período; meses futuros no clicables) + **Saldos pendientes** (por cobrar / por pagar con cantidad de comprobantes).

### 4. Comprobantes
Pestañas (54px, subrayado azul de 3px en la activa, con contadores): **Ventas · Compras · Clasificar · Cobros y pagos · Sincronizar SIRE**. Los contadores de Clasificar y Sincronizar se ven en ámbar cuando hay pendientes. Todas usan el período global. Si el mes no tiene datos (excepto Sincronizar) se muestra el estado vacío con "Sincronizar con SUNAT".

**4a. Ventas / Compras**
- 4 tarjetas: Comprobantes (sin anulados), Base imponible, IGV, Total.
- Barra: buscador (número, cliente/proveedor o RUC) · chips Todos / Boletas / Facturas / Anulados (con conteos) · "Descargar Excel".
- Tabla: Fecha · Comprobante (etiqueta BOL/FAC + serie mono) · Cliente/Proveedor (+ RUC o "Sin documento") · Base · IGV · Total (derecha, tabulares) · Cuenta ("Sin clasificar" ámbar, "Anulado" rojo con fila al 60%).
- Paginación "Mostrar 15 más" + "Mostrando X de Y". Tabla min-width 1000 con scroll horizontal.

**4b. Clasificar**
- Selector segmentado Ventas (N) / Compras (N) + botón "Reglas de clasificación (N)".
- **Propuesta en lote** (lo principal): "Asignar las N ventas pendientes a <cuenta general>", botón grande "Aplicar a las N ventas" + "Cambiar cuenta general". Nota: se marcan cobradas en la fecha del comprobante; las de crédito se corrigen en Cobros y pagos (enlace).
- **Revisión una por una**: checkbox "Seleccionar estas N"; barra de selección (fondo `#e6eef6`) con "N seleccionadas · Asignar a [cuenta] · Confirmar seleccionadas · Quitar selección". Cada fila: checkbox, serie/fecha, cliente, total, select de cuenta, checkbox "Ya se cobró" (marcado por defecto), botón "Confirmar".
- Estado completo: "Todas las ventas de <mes> están clasificadas".
- "Otros meses con ventas por clasificar": botones que cambian el período.
- Al clasificar se actualizan todos los contadores (pestaña, Inicio, Mis empresas, selector, estado de obligaciones).
- Plan de cuentas usado en el prototipo (sustituir por el real): ventas 701, 702, 704, 759; compras 601, 602, 603, 631, 636, 656.

**4c. Reglas de clasificación** (subvista de Clasificar, "← Volver a Clasificar")
- Segmentado Compras (proveedores) / Ventas (clientes).
- Tarjeta **Cuenta general** con select: cuenta para todos los que no tienen regla propia.
- **Reglas por RUC**: input RUC (solo dígitos, máx. 11) + select de cuenta + "Agregar regla". Errores: "El RUC debe tener 11 números." / "Ya existe una regla para ese RUC." Tabla: proveedor/cliente + RUC · cuenta · veces aplicada · "Quitar".
- Prioridad al clasificar: cuenta elegida manualmente > regla por RUC > cuenta general.

**4d. Cobros y pagos**
- Si hay ventas sin clasificar en el mes: banner ámbar "Hay N ventas de <mes> sin clasificar. No aparecen aquí hasta que las clasifiques." + "Ir a Clasificar".
- Segmentado Ventas (por cobrar) / Compras (por pagar) · chips Con saldo (por defecto) / Cobrados|Pagados / Todos.
- 3 tarjetas: Total del mes · Ya cobrado/pagado · Falta cobrar/pagar.
- Tabla: Comprobante · Cliente/Proveedor · Total · Cobrado/Pagado · Saldo · Estado (chip Cobrado/Pagado verde, Parcial ámbar, A crédito rojo) · botón "Registrar cobro/pago" solo si hay saldo. **Sin acción por fila cuando no hay nada que hacer** (antes "Pasar a crédito" en cada fila generaba ruido).
- **Modal Registrar cobro/pago** (540px): serie y cliente, saldo pendiente, Monto (con "Todo el saldo"), Fecha, Medio de pago (Efectivo, Transferencia, Yape / Plin, Tarjeta). Validación: monto > 0 y ≤ saldo. Al guardar: actualiza saldo y toast "Cobro de S/ X registrado en Caja (medio)". En producción debe crear el movimiento en Caja.

**4e. Sincronizar SIRE**
- Tarjeta de conexión: "Clave SOL conectada" (verde) + usuario secundario; botón "Descargar <mes pendiente>" si falta el último mes.
- Tabla por mes (más reciente arriba): Mes (clic → abre Ventas de ese mes) · Ventas (N comprobantes o "Sin descargar" ámbar + chip "N por clasificar") · Compras · Última descarga · botón "Descargar" (primario) o "Volver a descargar" (secundario). Mientras descarga: "Descargando…" y los otros botones al 50%.
- Nota: "SUNAT publica la propuesta de cada mes desde el día 1 del mes siguiente. Volver a descargar no borra lo que ya clasificaste." (Reemplaza las leyendas crípticas √V/√C, ★, ~.)

### Otras secciones
Caja, Planillas, Honorarios, Apertura y Datos de la empresa **no se rediseñaron**. Aplicarles el mismo layout, tokens y reglas de accesibilidad.

### 5. Contabilidad
Reemplaza el menú desplegable por una **barra de subsecciones visible** (tarjeta blanca) con 4 grupos rotulados (14px, mayúsculas, secundario): **Libros** (Libro Diario · Libro Mayor · Asientos manuales) · **Estados financieros** (Balance de comprobación · Balance general · Estado de resultados · Cambios en el patrimonio · Flujo de efectivo) · **Saldos** (Cuentas por cobrar · Cuentas por pagar) · **Cierre** (Cierre del año). Botones de 44px; el activo va relleno de azul. Cada botón muestra un chip ámbar "Revisar" si esa pantalla tiene un problema (en Cierre: "N pendientes" o "Cerrado").

Estructura común de cada subsección: título 24/700 + descripción (incluye el rango, p. ej. "Acumulado de enero a setiembre 2026") + acciones a la derecha (Descargar PDF/Excel) → **avisos** (verde ok / ámbar advertencia / azul info, cada uno con botón de acción que lleva a la corrección) → contenido. Todas usan el **período global**: se eliminan los selectores "Acumulado hasta / Al cierre de" de cada pantalla.

**Regla de presentación de montos:** las cifras se muestran en positivo y el signo va en la etiqueta ("(−) Costo de ventas"). Importe cero = "—" en gris. Saldos negativos de CxC/CxP = "S/ X a favor" en verde (nunca "S/ -625.66").

**5a. Libro Diario** (mes del período): chips Todos / Automáticos / Manuales. Tabla: N° · Fecha · Glosa y cuentas · Debe · Haber. Cabecera de asiento en `#faf9f6` con chip "Automático" (gris) o "Manual" (azul); las líneas del Haber se sangran 36px. Pie "Total del mes". Aviso verde si cuadra. "Generar / Regenerar" pasa a ser **"Volver a generar"** (secundario) y confirma que no toca los asientos manuales. Aviso info del tramo de ingresos sin definir (reemplaza el chip "General · Tramo de ingresos por definir").

**5b. Libro Mayor:** dos columnas. Izquierda (≤400px): buscador + lista de cuentas con movimiento (código mono, nombre, "Saldo X D/A"); la seleccionada lleva borde izquierdo azul de 4px. Reemplaza la grilla de chips con solo números. Derecha: cabecera con código, nombre, chip de destino (Balance · Activo, etc.), cifras Debe/Haber/Saldo; tabla Fecha · Glosa · Debe · Haber · Saldo acumulado (D/A); pie "Saldo al <fecha>".

**5c. Asientos manuales:** paso "1. Elige una plantilla" (tarjetas): Provisión de CTS (629/415 + destino 94/791), Gratificación (6214/4114 + destino), Vacaciones (6215/4115 + destino), Depreciación (681/395 + destino), Costo de ventas (691/201, pide **inventario final** y calcula saldo 201 − inventario), Impuesto a la Renta (881/4017, estimado 29.5%), En blanco. Paso "2. Completa el asiento": Fecha, Glosa, líneas (cuenta + Debe + Haber + Quitar; escribir en un lado borra el otro), "+ Agregar línea", totales. Barra inferior con estado en vivo: "Falta S/ X en el Haber para que cuadre" (rojo) / "El asiento cuadra" (verde). Validaciones: glosa, fecha dd/mm/2026, cuenta en cada línea con importe, ≥2 líneas, Debe = Haber. Lista "Asientos manuales de 2026" con Quitar. Si el año está cerrado no se muestra el formulario. **Los asientos manuales afectan Diario, Mayor y todos los estados.**

**5d. Balance de comprobación:** ver la pantalla en el prototipo: resultado del ejercicio destacado, activo vs. pasivo+patrimonio, 4 verificaciones (Diario cuadra, saldos cuadran, balance general cuadra, 9 = 79), cuentas agrupadas por elemento (1–9) que se pueden contraer, buscador, vista Completo / Solo saldos, panel lateral con el detalle mensual de cada cuenta y botón al Mayor.

**5e. Balance general:** tarjeta con la ecuación **Activo = Pasivo + Patrimonio** y chip "Cuadra" / "No cuadra: S/ X". Dos columnas con el detalle de cuentas (nombre + "Cuenta N"): Activo corriente (clases 1–2 y cuentas 4 con saldo deudor), Activo no corriente (clase 3; **la 39 resta como "(−) Depreciación acumulada"**), Pasivo corriente (clase 4 acreedora), Patrimonio (clase 5 + Resultado del ejercicio, rotulado "antes/después de impuestos"). Avisos: falta provisionar el IR (→ plantilla IR); depreciación acumulada mayor que el costo del activo (→ Mayor 395).

**5f. Estado de resultados** (por función): Ventas netas (70) · (−) Costo de ventas (69) · Utilidad bruta · (−) Gastos de administración (94) · (−) Gastos de venta (95) · [(−) Costos de producción (92) solo si hay importe] · Utilidad operativa · (+) Ingresos financieros (77) · (−) Gastos financieros (97) · Utilidad antes de impuestos · (−) IR (registrado en 881, o "estimado 29.5%, aún no registrado") · **Utilidad neta** (verde/rojo) · Reserva legal 10% ("se registra al cerrar el año"). Avisos: costo de ventas en 0 (→ plantilla costo de ventas); IR estimado (→ plantilla IR). **Nota:** el PCGE usa la 97 para gastos financieros; la pantalla actual muestra "Cuenta 96".

**5g. Cambios en el patrimonio:** columnas Capital · Resultados acumulados · Resultado del ejercicio · Total. Filas: Saldo al 1 de enero · Resultado del ejercicio · Reserva legal y dividendos (al cierre) · Saldo al <fecha> (en negrita).

**5h. Flujo de efectivo** (método directo): franja de 5 cajas Saldo inicial + Operación + Inversión + Financiamiento = **Saldo final** (la última en azul). Luego las tres actividades con sus líneas y subtotales, aumento neto, saldo inicial y final. Aviso verde si el saldo final = cuentas 10, si no ámbar con botón al Mayor 101.

**5i. Cuentas por cobrar / por pagar:** 5 tarjetas clicables que filtran: Total · 0–30 · 31–60 · 61–90 · Más de 90 días. Aviso de **conciliación**: saldo de la cuenta (121/421) vs. suma de comprobantes con saldo; si difiere, mostrar la diferencia, la causa probable (saldo de apertura o asiento manual sin comprobante) y un botón al Mayor. Tabla agrupada por cliente/proveedor (RUC mono + nombre + saldo del grupo); filas: tipo en palabras (Factura / Nota de crédito) + serie · Fecha · Días (rojo si >90 y hay saldo) · Total · Pagado/Cobrado · Saldo. Botón "Ir a Cobros y pagos".

**5j. Cierre del año:** lista de pasos con estado: ventas clasificadas · compras clasificadas · costo de ventas registrado · IR provisionado · libros cuadran (cada uno con su botón de corrección) + aviso informativo "El año aún no termina". El botón "Cerrar el año 2026" (rojo `#a3261d`) solo se activa sin pendientes; si no, dice "Faltan N pasos para poder cerrar". Confirmación en modal: lista de consecuencias + checkbox obligatorio "Entiendo que después no podré modificar los asientos de 2026" + "Sí, cerrar 2026". Cerrado: tarjeta verde + "Reabrir el año".

### ⚠ Errores contables detectados en el sistema actual (corregir)
1. **El Balance General no cuadra.** En ACSA a setiembre 2026: Total activo 1,692,987.97 vs. Pasivo + Patrimonio 1,550,639.04 → diferencia **142,348.93 = IR estimado**. El "Resultado del ejercicio" ya resta el IR del Estado de resultados, pero el IR no está registrado como pasivo (4017). Corrección: el Balance general debe tomar el resultado **de los libros**. Si el IR no está provisionado, mostrar el resultado antes de impuestos y avisar, con opción de registrar 881/4017.
2. **La depreciación acumulada (395) está en Pasivo corriente.** Debe presentarse en el Activo no corriente restando del costo. Además, 395 (209,483.00) es mayor que 333 (180,173.00); avisar al usuario.
3. **Cuentas por pagar muestra totales negativos** ("S/ -625.66") sin explicar. Mostrar "a favor" y la conciliación con la 421.

---

## Interactions & Behavior
- Toast inferior centrado, 2.6 s, para toda acción que cambia datos.
- Hover: botones primarios `#1f4e79 → #163a5c`; secundarios cambian el borde a azul; filas/ítems de menú fondo `#f0ede6`.
- Foco en inputs: borde `#1f4e79`. Mantener foco visible por teclado.
- Atajos globales: Ctrl/Cmd+K abre el selector; Esc cierra modales.
- Cambiar período reinicia la paginación y la selección.
- Sin animaciones (decisión intencional para el público objetivo).
- Responsive: las tarjetas hacen wrap; las tablas mantienen min-width con scroll horizontal; el header hace wrap.

## State Management
- Global: `empresaActual`, `periodo` (mes/año), `tamañoLetra` (persistir por usuario), `vista`.
- Por empresa (backend): obligaciones y su estado; comprobantes de ventas/compras por mes; clasificación (cuenta) por comprobante; cobros/pagos por comprobante; reglas por RUC y cuenta general (ventas y compras); estado de descarga SIRE por mes; certificado digital.
- Derivados: pendientes por clasificar por mes/empresa, próximo vencimiento y semáforo, estado de cada obligación (bloqueada si hay ventas sin clasificar; PDT 621 en espera hasta presentar RVIE y RCE).
- UI local: búsqueda/filtros, paginación, selección múltiple, modal de cobro, subvista de reglas.
- Contabilidad: subsección activa; cuenta seleccionada en el Mayor; borrador del asiento manual; filtro de antigüedad CxC/CxP. Backend: asientos manuales por empresa (fecha, glosa, líneas), estado de cierre del año, tramo de ingresos.
- Lógica de referencia en el prototipo: `ledger()` (saldos por cuenta incluyendo los asientos manuales) y `contVals()` (todos los estados y verificaciones).

## Assets
Sin imágenes ni iconos. Solo Google Fonts:
`https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;500;600;700&family=Atkinson+Hyperlegible+Mono:wght@400;500&display=swap`

## Files
- `GestiCont Inicio.dc.html`: prototipo completo (abrir en el navegador; requiere `support.js` en la misma carpeta). Prop `startView` = "Mis empresas" | "Inicio"; `textSize`; `showChart`.
- `support.js`: runtime del prototipo (no forma parte del diseño).
