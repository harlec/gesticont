# Handoff: GestiCont — navegación superior + sistema de temas

## Overview
Rediseño de la interfaz de GestiCont (sistema contable para estudios contables en Perú). Se elimina el sidebar: **toda la navegación vive en una barra horizontal superior** con menús desplegables agrupados (patrón NubeCont, pero legible y moderno). Se agrega un **sistema de temas** (claro, tres pasteles, oscuro) seleccionable desde la propia interfaz y un color de acento configurable.

## About the Design Files
Los archivos de este paquete son **referencias de diseño hechas en HTML** — prototipos que muestran el aspecto y comportamiento deseados, **no código de producción para copiar tal cual**. La tarea es **recrear estos diseños en el entorno del codebase destino** (React, Vue, Laravel Blade, etc.) usando sus patrones y librerías ya establecidos. Si aún no existe un entorno, elegir el framework adecuado e implementarlos ahí.

`GestiCont Mono v2.dc.html` usa un runtime propio (`support.js`) con un mini lenguaje de plantillas (`<sc-for>`, `<sc-if>`, `{{ valor }}`) y una clase de lógica al final del archivo. Ábrelo en un navegador para ver el prototipo funcionando; **lee la clase `Component` al final del archivo para entender estado e interacciones**.

## Fidelity
**Alta fidelidad (hifi).** Colores, tipografía, espaciado y estados son definitivos. Recrear la UI fielmente, adaptando solo la capa técnica.

---

## Estructura global

Tres zonas fijas, de arriba hacia abajo:

1. **Barra de identidad** (sticky, fondo `--brand-d`, alto ~48px, padding `10px 26px`, `display:flex; gap:18px; align-items:center`)
2. **Barra de menú** (sticky, fondo `--brand`, `display:flex; flex-wrap:wrap; padding:0 18px`, borde inferior `1px solid --brand-d`)
3. **Contenido** (`--bg`), con cabecera de página (breadcrumb + título + acciones) y luego la vista activa.

Ambas barras van dentro de un contenedor `position:sticky; top:0; z-index:20`.

### 1. Barra de identidad (izquierda → derecha)
- **Logo**: cuadrado 28×28, `border-radius:8px`, fondo `--gold`, letra "G" en `#3a2b08`, Nunito 800 15px. Al lado "GestiCont" en Nunito 800 17px blanco. Click → Panel.
- Separador vertical `1px × 26px`, `rgba(255,255,255,.16)`.
- **Selector de empresa**: pastilla `background:rgba(255,255,255,.1)`, borde `1px solid rgba(255,255,255,.14)`, radio 10px, padding `6px 12px 6px 8px`, ancho mínimo 260px. Contiene avatar 26px (fondo `--gold`, inicial), nombre de empresa (13px, blanco, `text-overflow:ellipsis`) y línea mono 9.5px `RUC {ruc} · {régimen}`, más caret `▾`. Hover: `rgba(255,255,255,.16)`.
  - Al click abre panel blanco 340px (`top: 100% + 8px`, radio 12px, sombra `0 24px 50px -22px rgba(22,41,79,.4)`) con encabezado mono "CAMBIAR DE EMPRESA", una fila por empresa (avatar, nombre, RUC, badge "N pend." si hay pendientes, ✓ si es la actual) y pie "+ Registrar nueva empresa".
- **Spacer** (`flex:1`).
- **Selector de tema**: pastilla redonda (`border-radius:999px`, padding `5px 11px`, mismos fondos translúcidos) con punto de color 13px, etiqueta del tema activo y caret. Abre panel 230px alineado a la derecha: sección "TEMA" con 5 filas (punto de color + nombre + ✓) y sección "ACENTO" con 4 círculos de 24px (anillo `2px solid --gold` en el activo).
- **Fecha**: mono 11px, `--onbar-2`.
- **Usuario**: bloque con borde izquierdo `1px solid rgba(255,255,255,.16)`, padding-left 14px: avatar circular 28px con iniciales, nombre 12.5px blanco, rol mono 9px `--onbar-3`, e icono de salida `↪` (click → login).

### 2. Barra de menú
Ítems `display:flex; align-items:center; gap:7px; padding:11px 13px; font-size:13.5px`.
- Estado normal: color `--onbar-2` (#ccd8ef). Hover: fondo `rgba(255,255,255,.12)`, texto blanco.
- Estado activo/abierto: fondo `--brand-d`, texto blanco.
- Caret `▾` 9px en los ítems con submenú; badge dorado (fondo `--gold`, texto `#3a2b08`, mono 10px, radio 999px) para contadores.
- **Apertura**: click abre/cierra; con un menú ya abierto, pasar el mouse por otro lo cambia (hover-switch). Cierra con click fuera o `Esc`.

**Desplegable**: `position:absolute; top:100%; left:0; min-width:286px`, fondo `--surface`, borde `1px solid --line-strong` sin borde superior, sombra `0 26px 50px -24px rgba(22,41,79,.45)`, padding `6px 0 8px`, `z-index:50`.
- Título de grupo: mono 9px, `letter-spacing:.2em`, color `--label`, padding `11px 16px 5px`.
- Ítem: 13px, color `--ink`, padding `7px 16px`; hover fondo `--brand-soft`, texto `--brand-ink`. Puede llevar badge suave (fondo `--gold-soft`, texto `--gold-ink`) o ✓ (ítem actual, p.ej. el periodo).

**Estructura completa del menú** (grupo → ítems; ★ = pantalla implementada en el prototipo):

| Menú | Grupos e ítems |
|---|---|
| **Panel** | (sin submenú) → Panel principal ★ |
| **Empresa** | EMPRESA ACTUAL: Resumen de la empresa ★ · Datos y credenciales SUNAT · Usuarios con acceso — ADMINISTRACIÓN: Todas mis empresas ★ · Registrar nueva empresa |
| **Ventas** | VENTAS: Ver ventas del periodo ★ · Agregar nueva venta — HERRAMIENTAS: Consistencia de comprobantes · Subir ventas desde Excel · Subir ventas desde SIRE — LIBROS: Registro de Ventas PDF |
| **Compras** (badge = pendientes) | COMPRAS: Ver compras del periodo ★ · Agregar nueva compra — HERRAMIENTAS: Clasificar comprobantes ★ (badge "N pend.") · Subir compras desde Excel · Subir compras desde SIRE — LIBROS: Registro de Compras PDF |
| **Caja y bancos** | SALDOS: Saldo de cuentas financieras — INGRESOS Y COBROS: Todos los ingresos y cobros · Nuevo ingreso y/o cobro — EGRESOS Y GASTOS: Todos los egresos y gastos · Nuevo egreso y/o gasto — TRANSFERENCIAS: Transferencias entre cuentas |
| **Planilla** | RECIBOS POR HONORARIOS: Todos los recibos · Nuevo recibo por honorario — BOLETAS DE PAGO: Todas las boletas · Nueva boleta de pago |
| **Archivo** | ASIENTOS AUTOMÁTICOS: Cuentas financieras · Tipos de compras · Tipos de ventas · Tipos de asientos de diario — PLAN CONTABLE: Plan contable · Tipo de cambio — CENTRO DE COSTOS: Tipos de centro de costos |
| **Reportes** | ESTADOS FINANCIEROS: Estado de Resultados ★ · Estado de Situación Financiera · Balance de Comprobación — LIBROS: Libro Diario · Libro Mayor — DESCARGAS: PDF Registro de Ventas · PDF Registro de Compras · EXCEL Análisis de cuentas |
| **SUNAT** | VERIFICACIÓN: Verificar compras con la SUNAT · Verificar ventas con la SUNAT — CREDENCIALES: Clave SOL para SIRE y CPE · Credenciales API SIRE — HERRAMIENTAS: Comparar SIRE vs GestiCont · Vencimientos del periodo |
| *(spacer flex:1)* | |
| **PERIODO OCT 2025** | PERIODO DE TRABAJO: Mar 2025 … Jan 2026 (✓ en el activo; cambia el periodo global) |
| **Mi cuenta** | MI CUENTA: Perfil y preferencias · Usuarios del estudio · Cerrar sesión → login |

Los ítems sin pantalla implementada navegan a un estado **"MÓDULO EN CONSTRUCCIÓN"** (tarjeta con borde punteado `--line-strong`, radio 14px, padding 54px, centrada).

---

## Pantallas

Cabecera común de contenido: padding `20px 26px 16px`, breadcrumb mono 10.5px `--label`, título Nunito 800 26px `letter-spacing:-.02em`, y a la derecha botones "Sincronizar SIRE" (secundario) y "+ Nueva empresa" (primario). Cuerpo con padding `6px 26px 60px`.

### Login
Grid `1.1fr / 1fr` a pantalla completa. Izquierda: fondo `--brand-d`, dos círculos decorativos con borde `rgba(255,255,255,.07)`, logo arriba, titular Nunito 800 56px blanco ("Gestiona tus empresas desde un solo lugar."), tres chips translúcidos, pie mono. Derecha: fondo `--bg` centrado, tarjeta blanca 390px (radio 16px, borde `--line`, sombra `0 20px 50px -30px`), campos email/contraseña (labels mono 9.5px, inputs radio 9px, fondo `--surface-2`, foco borde `--brand-ink`), link "¿Olvidaste tu contraseña?", botón primario ancho completo y nota informativa.

### Panel principal (`inicio`)
- Fila de 4 KPIs (`grid auto-fit minmax(210px,1fr)`, gap 14px): EMPRESAS ACTIVAS, ALERTAS PENDIENTES, POR CLASIFICAR (valor en `--gold-ink`) y una tarjeta con dos botones de acción. Tarjeta: fondo `--surface`, borde `--line`, radio 14px, padding `18px 20px`; label mono 9.5px `letter-spacing:.18em`; cifra mono 32px.
- Grid `1.6fr / 1fr`: lista "Mis empresas" (filas clicables con avatar, nombre, RUC, badge de pendientes, `→`) y columna derecha con "Alertas activas" + tarjeta oscura "ESTADO DEL SISTEMA" (fondo `--brand-d`, radio 14px).

### Resumen de empresa (`empresa`)
Tarjeta de cabecera con avatar 48px, nombre Nunito 800 25px, línea mono `RUC · régimen`, y botonera (Ventas, Compras, Clasificar, Resultados, Balance, Editar + primario "Sincronizar SIRE"). Debajo 3 tarjetas: VENTAS DEL MES, COMPRAS DEL MES, CERTIFICADO DIGITAL. Si no hay ventas, tarjeta punteada "SIN VENTAS EN {periodo}".

### Registro de ventas (`ventas`)
Filtro de periodos como pastillas (mono 11px, radio 999px; activa = fondo `--brand-d`, texto blanco). 4 KPIs (COMPROBANTES, BASE IMPONIBLE, IGV 18%, TOTAL — este último en tarjeta `--brand-d`). Tabla: cabecera fondo `--surface-2`, th mono 9.5px `letter-spacing:.14em`; columnas #, TIPO (chip FAC azul `--brand-soft`/`--brand-ink`, NC rojo `--neg-soft`/`--neg`), SERIE–NÚMERO, FECHA, CLIENTE, BASE, IGV, TOTAL (los tres montos alineados a la derecha, mono, `font-variant-numeric:tabular-nums`, negativos en `--neg`). Pie con subtotales. Filas hover `--surface-2`, separador `1px solid --line-soft`.

### Clasificación de comprobantes (`clasificar`) — pantalla principal
- Tarjeta de progreso: "NN / NN clasificados", botones "Confirmar seleccionados (N)", "Seleccionar pendientes", "Limpiar"; barra de progreso 6px (`--track` / relleno `--brand-d`, `transition:width .3s ease`); fila de **reglas rápidas** (pastillas: "MOLINO LA PERLA → 6011", "Notas de crédito → 6091", "Transportes → 6311") que asignan cuenta en lote a los comprobantes que cumplen el criterio y no están confirmados.
- Tabla en grid `34px 70px 118px 94px minmax(150px,1fr) 124px 230px 104px`, gap 12px, `min-width:920px` con scroll horizontal: checkbox personalizado 16px, chip de tipo, serie, fecha, contraparte, monto (negativos en `--neg`), **select de cuenta destino** (plan contable 6011/6032/6311/6341/6591/6091) y estado: `PENDIENTE` (sin cuenta), botón `Confirmar` (con cuenta asignada) o chip `CONFIRMADO` (`--pos-soft`/`--pos`).

### Estado de Resultados (`resultados`)
Selector de periodo acumulado, nota en tarjeta `--gold-soft` sobre la estimación del costo de ventas (falta módulo de Inventario/Kardex), y tabla de líneas P&L (máx 860px): filas normales (etiqueta + cuenta mono + importe + % de ingresos) y filas de total (fondo `--brand-soft`, bordes `--brand-line`, importe 14px 700 en `--brand-ink`). Cálculo: Utilidad Bruta = Ingresos − Costo de Ventas (compras clasificadas ÷ 1.18).

---

## Interacciones y comportamiento
- **Navegación**: sin rutas en el prototipo; en producción cada ítem de menú debe ser una ruta real.
- **Menús**: click abre/cierra; hover cambia entre menús mientras haya uno abierto; `Esc` y click fuera cierran menús, selector de empresa y selector de tema (listeners en `document` con captura).
- **Cambio de empresa**: reinicia la selección de comprobantes; los datos (ventas/compras) dependen de la empresa activa.
- **Clasificación**: selección múltiple; confirmar solo aplica a filas con cuenta asignada; el contador de pendientes alimenta el badge del menú "Compras".
- **Periodo**: global, se elige desde el menú PERIODO o desde las pastillas de cada pantalla.
- **Tema**: cambia variables CSS en `document.documentElement` al instante, sin recarga. En producción conviene persistirlo por usuario.
- **Responsive**: la barra de menú usa `flex-wrap`, así que en anchos reducidos salta a una segunda fila; tablas con `overflow-x:auto`.

## State Management
```
screen        // login | inicio | empresa | ventas | clasificar | resultados | módulos en construcción
period        // "Oct 2025" por defecto
empresaIdx    // empresa activa
openMenu      // id del menú desplegado | null
switcherOpen  // bool  selector de empresa
themeOpen     // bool  selector de tema
theme         // nombre del tema | null (usa el valor por defecto)
accent        // hex del acento | null
assign        // { "empresaIdx|serie": cuenta }
confirmed     // { "empresaIdx|serie": true }
sel           // { "empresaIdx|serie": true } selección múltiple
```
Datos necesarios del backend: empresas (nombre, RUC, régimen), comprobantes de compra y venta por empresa y periodo, plan contable (cuentas destino), estado de credenciales SUNAT/SIRE, y persistencia de la clasificación.

## Design Tokens

Todo el color pasa por variables CSS en `:root`. Tema **Claro** (base):

```
--bg:#f6f5f2   --surface:#ffffff  --surface-2:#faf9f6  --track:#f0eee8
--line:#e7e4dd --line-soft:#f1efe9 --line-strong:#ddd9d1
--brand-line:#dfe4ee --brand-soft:#f2f4f9
--ink:#1d1c1a  --muted:#6c675f  --label:#615c54  --faint:#8f8a81
--brand-d:#16294f  --brand:#23407a  --brand-ink:#23407a
--onbar:#ffffff --onbar-2:#ccd8ef --onbar-3:#93a5c8
--gold:#e8b33c --gold-soft:#fbf2dd --gold-ink:#7a5a0f
--pos:#2f6a43 --pos-soft:#e6f0e8 --neg:#8a3a3a --neg-soft:#f7e6e6
```

Temas alternativos (solo cambian las variables; el resto del CSS es idéntico) — ver constante `THEMES` en el archivo:
- **Pastel menta** — `--bg:#f1f6f3`, `--brand-d:#1f5136`, `--brand:#2e6d4b`, `--brand-ink:#26603f`
- **Pastel lavanda** — `--bg:#f5f3fa`, `--brand-d:#3f3566`, `--brand:#5a4a8f`, `--brand-ink:#544595`
- **Pastel durazno** — `--bg:#fbf4ef`, `--brand-d:#6d3125`, `--brand:#8a4634`, `--brand-ink:#8f4a37`
- **Oscuro** — `--bg:#141519`, `--surface:#1c1e24`, `--surface-2:#23262e`, `--line:#2e323b`, `--ink:#eceae5`, `--muted:#a9a49b`, `--brand-d:#26375c`, `--brand:#3b5288`, `--brand-ink:#9db9ee`, `--gold-soft:#3a2e12`, `--gold-ink:#edc873`, `--pos:#7cc295`, `--neg:#e08b8b`

**Acento configurable**: al elegir un acento se sobrescriben `--brand-d` y `--brand-ink` con el color, y se derivan `--brand` = `color-mix(in oklab, acento 84%, white)`, `--brand-soft` = 8%, `--brand-line` = 18%. No se aplica en el tema Oscuro. Opciones: `#16294f`, `#1f5136`, `#5a2b5c`, `#7a3b18`.

**Tipografía**: `Nunito` (Google Fonts, 400/500/600/700/800) para toda la UI y títulos (800, `letter-spacing:-0.02em`); `JetBrains Mono` (400/500/700) para cifras, etiquetas en versalitas, RUC, fechas y códigos de cuenta. Tamaños: títulos 26px · nombre de empresa 25px · cifras KPI 32/26/23px · texto base 13–13.5px · labels mono 9–9.5px con `letter-spacing:.14–.24em`.

**Otros**: radios 7/8/9/12/14/999px · gaps 8/12/14/18/26px · sombra de panel `0 24px 50px -22px rgba(22,41,79,.4)` · sombra de dropdown `0 26px 50px -24px rgba(22,41,79,.45)` · transición de barra de progreso `width .3s ease`.

## Assets
Ninguno externo salvo las fuentes de Google (Nunito, JetBrains Mono). No hay imágenes ni iconos de librería: los pocos glifos (`▾ ✓ → ↪ +`) son caracteres de texto. Si el codebase ya tiene set de iconos, sustituirlos por los equivalentes.

## Files
- `GestiCont Mono v2.dc.html` — prototipo completo (plantilla + lógica al final del archivo)
- `support.js` — runtime necesario para abrir el prototipo en el navegador (no portar a producción)
