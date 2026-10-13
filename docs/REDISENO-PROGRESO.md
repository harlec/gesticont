# Rediseño GestiCont — avance

Guía: `docs/design_handoff_gesticont_redesign/README.md` (prototipo en `GestiCont Inicio.dc.html`).
Se trabaja por fases; cada pantalla se verifica con capturas reales (base de desarrollo + Chrome).

## Hecho
- **Base**: paleta y tipografía (Atkinson Hyperlegible) en `public/css/theme.css`; componentes en `public/css/ui.css`
  (tarjetas, botones de 44 px, chips de estado, pestañas, tablas, avisos, ventanas). Los nombres `--gc-*` se conservaron:
  las pantallas aún no migradas toman la paleta nueva; además se les sube la letra mínima a 14 px y los controles a 44 px.
- **Estructura**: menú lateral + encabezado (`views/layout/sidebar.php`, `header.php`, `base.php`), período único,
  control de letra (Normal / Grande / Muy grande, se guarda en cookie), selector de empresa Ctrl+K.
  Se eliminaron los temas de color y el menú horizontal (`nav.php`, `nav.css`, `layout.css`).
- **Login, recuperar acceso, 403/404** sin Tailwind, con campos de 54 px.
- **Vencimientos SUNAT**: cronograma oficial 2026 por último dígito del RUC (`fase1l_cronograma_vencimientos.sql`,
  fuente: RS 281-2022/SUNAT, tabla publicada por SUNAT), `VencimientosService`, `CarteraService`.
  RVIE/RCE cuentan como presentados cuando SIRE los devuelve como *declarados*; PDT 621 y PLAME se marcan a mano
  ("Marcar como presentado": GestiCont no presenta nada ante SUNAT). Nuevo RUS: sin obligaciones mensuales.
- **Mis empresas** (entrada del sistema; `/dashboard` redirige) e **Inicio** de la empresa.

## Supuestos a confirmar con el contador
- Las fechas del cronograma mensual se aplican también a los registros SIRE (RVIE/RCE) y a PLAME. SUNAT publica
  ese cronograma para "obligaciones tributarias mensuales"; la relación exacta con SIRE no se verificó en la fuente.
- Buenos contribuyentes (BCE) y principales contribuyentes (PRICOS): la tabla trae la columna BCE pero la empresa no
  tiene aún el dato; hoy se usa siempre la columna por último dígito.

- **Comprobantes**: Ventas/Compras (con Excel), Clasificar, Cobros y pagos (con medio de pago), Sincronizar (por pasos).
- **Contabilidad completa**: Libro Diario, Mayor, Asientos manuales (con plantillas), Balance de comprobación,
  Balance general, Estado de resultados, Cambios en el patrimonio, Flujo de efectivo, Cuentas por cobrar/pagar
  (tarjetas que filtran por antigüedad; saldos negativos "a favor"), **Cierre del año** (5 pasos con botón de
  corrección, confirmación con casilla, y "Reabrir el año" → `CierreService::reabrirPeriodo`).
- Régimen tributario, Depurar, Datos de la empresa (editar), Usuarios, Crear empresa: toman la paleta y los controles nuevos.

## Pendiente
- Reescribir con los componentes nuevos (hoy solo heredan paleta/tamaños): Caja, Planillas, Honorarios, Apertura,
  Reglas de clasificación, Alertas, Usuarios, Crear empresa.
- Libro Diario: chips Todos/Automáticos/Manuales y confirmación en "Volver a generar".
- Cierre: `reabrirPeriodo` no recalcula el año siguiente; al cerrar de nuevo se regeneran sus saldos de apertura.

## Antes de producción
Aplicar con respaldo: `fase1j`, `fase1k`, `fase1l`, `fase1m`, `fase0e` (en `docs/gesticon_doc/`).
Marcar como presentados los PDT 621 / PLAME históricos y confirmar el cronograma de SIRE/PLAME con el contador.
