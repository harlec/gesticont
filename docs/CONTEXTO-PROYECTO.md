# GestiCont — Contexto del proyecto

Documento de traspaso: qué es el sistema, cómo está armado, qué decisiones se tomaron y qué falta.
Complementa (no reemplaza) `docs/gesticon_doc/spec-motor-contable-gesticont.md` y `plan-completo-motor-contable.md`, que describen la lógica contable en detalle.

Última actualización: 2026-09-26 (regla general y rango "año vigente" incluidos).

---

## 1. Qué es

SaaS contable peruano en producción (`gesticont.harlec.com.pe`). Un **contador** administra varias **empresas**; el sistema sincroniza compras y ventas desde SUNAT (SIRE), las clasifica contra el plan de cuentas (PCGE), genera el Libro Diario y de ahí calcula Mayor, Balance de Comprobación, Balance General, Estado de Resultados, Cambios en el Patrimonio y Flujo de Efectivo. Reemplaza los Excel de contabilidad que usaban los contadores.

Roles: `superadmin`, `contador`, `operador`, `cliente`. Cada usuario solo ve las empresas donde tiene fila en `empresa_usuarios` (el superadmin ve todas).

## 2. Stack y despliegue

- PHP 8.1+ **sin framework**, PDO, MySQL/MariaDB. Sin build de frontend: HTML/CSS/JS en las vistas.
- Composer: `greenter/greenter` (facturación electrónica), `phpmailer/phpmailer`, `setasign/fpdf` (PDFs). `vendor/` va en el repo/servidor y el autoload se carga en `index.php`.
- Hosting en Plesk. Producción: `/var/www/vhosts/harlec.com.pe/gesticont.harlec.com.pe`.
- Configuración por variables de entorno: `DB_HOST/PORT/NAME/USER/PASS`, `ENCRYPT_KEY` (mín. 32 caracteres, encripta credenciales SOL/API), `APP_DEBUG` (`true` muestra errores; en producción está apagado y **un error fatal se ve como página en blanco**), `SESSION_NAME`.
- Zona horaria `America/Lima`.

## 3. Estructura del código

```
index.php              entrada; define ROOT, carga vendor/autoload
core/                  App (rutas), Router, Auth, Model (PDO), Periodo (período activo)
modules/<mod>/         Controller + views/  (auth, empresas, certificados, sync, registros,
                       imputacion, diario, balance, apertura, planillas, caja, cierre, dashboard, ...)
services/              lógica de negocio reutilizable (AsientoService, BalanceService, PdfReport, ...)
views/layout/          base.php, nav.php, floating_alert.php, comprobantes_subtabs.php, print_header.php
public/css/            theme.css (tokens --gc-*), layout.css, nav.css
cron/                  sync_ventas/compras, cierre_mensual, alertas_certificados
docs/gesticon_doc/     specs y migraciones SQL (fase*.sql)
```

Rutas: todas en `core/App.php` (`$router->get/post('/empresas/{id}/...', 'modulo/Controller@metodo')`).

## 4. Convenciones y trampas (leer antes de tocar código)

- **Placeholders de ruta en minúscula**: el Router solo reemplaza `{[a-z_]+}`. `{reglaId}` nunca matchea (404); usar `{rid}`, `{pid}`, `{cid}`. Los parámetros llegan como string y se posicionan por orden.
- **Cada controlador debe hacer `require_once` de lo que usa** (`core/Periodo.php`, servicios). El Router solo carga `Model` y `Auth`. Ya falló dos veces (`SyncController`, `ReglasImputacionController`) con `Class "Periodo" not found`.
- **CSRF**: solo el login lo valida. El resto de formularios POST no llevan token.
- **Migraciones**: numeradas y aplicadas **a mano** en producción (phpMyAdmin). **Nunca editar una migración ya aplicada** (duplica inserts en instalaciones nuevas); todo cambio va en un archivo nuevo `faseNx_*.sql`.
- **FPDF trabaja en Latin-1**: todo texto pasa por `PdfReport::t()`. Tildes y ñ funcionan; símbolos Unicode (≠ → ✓ ⚠ emoji, y también la raya larga —) salen como `?`. Usar palabras o `-`.
- **`Model::update()`** arma el UPDATE con las claves del arreglo: si se agrega una columna en código sin migrarla en producción, el guardado revienta (`Unknown column`). Pasó con `vende_tipo`.
- **`gesticont_db.sql` empieza con `CREATE DATABASE gesticont; USE gesticont;`**: siempre carga en la base llamada `gesticont`, ignore la que se pase por línea de comandos.
- **`gesticont_db_sync.sql` está desactualizado** frente a producción: no trae `periodo` ni los valores `estado_sunat = '1'` (default `'aceptado'`), `proveedor_tipo_doc`, `id_sire`, etc. que el código real usa. Para pruebas locales hay que parchar esas columnas (ver §10).
- `fase1c_reserva_legal.sql` da "Duplicate column pct_reserva_legal" en instalaciones nuevas: es inofensivo (fase0 ya la crea).
- **Ancho de páginas**: `gc-content gc-w-content` (1600 px) para todas; en formularios el contenedor exterior es ancho y la tarjeta interior queda cómoda.
- **Meses en español**: usar `Periodo::etiqueta($yyyymm, $largo=false)`. No depender de `setlocale` (era la causa de los meses en inglés).
- **`SunatApiService.php`**: por decisión del usuario **no se modifica el manejo de errores/token** (se probó una mejora y se revirtió porque producción ya funcionaba). Lo único tocado después fue la lectura del RUC del proveedor (§7).

## 5. Modelo contable — lo importante

- **Plan de cuentas (PCGE 2019)** en `cuentas_contables` (`empresa_id IS NULL` = catálogo global). Se validó cuenta por cuenta contra el PDF oficial del MEF. **Criterio: corregir hacia el PCGE oficial, nunca copiar los códigos de un Excel de contador** (los Excel traen códigos obsoletos, p. ej. 704 = servicios pasó a ser subproductos; servicios es **703**; 616 no existe, es 613).
- **`naturaleza` (deudora/acreedora) y `tipo` (activo/pasivo/patrimonio/ingreso/gasto/destino) son ejes independientes.** Una contra-activo (395 Depreciación Acumulada, 19) es tipo `activo` pero naturaleza `acreedora`; 5912 Pérdida es patrimonio pero deudora. El lado Debe/Haber y el signo en los totales se deciden por **naturaleza**, no por tipo. Fue la causa de varios bugs (Apertura).
- **Se guarda poco, se calcula mucho**: las tablas físicas son documentos fuente (`registro_ventas`, `registro_compras`, `planillas`, `caja_movimientos`), `saldos_apertura`, `imputaciones` y `asientos`/`asientos_detalle`. Mayor, Balances y estados son cálculo (`BalanceService` agrega `saldos_apertura` + `asientos_detalle`).
- **Imputación**: cada comprobante SIRE queda `pendiente` hasta clasificarse; clasificar inserta en `imputaciones` (cuenta, monto neto = total − IGV, `regla_id` si vino de una regla) y marca `imputado`. Cobro/pago se declara al clasificar (SUNAT no lo informa) y genera movimiento de Caja contra 121/421.
- **`tipos_gasto`** es un catálogo **global** (no por empresa) con nombre amigable → cuenta, `aplica_a` compra/venta/ambos.
- **Apertura (Inventario Inicial)**: el monto se escribe positivo (se usa `abs`); el lado lo decide la naturaleza; debe cuadrar Activo = Pasivo + Patrimonio para guardar. El cuadre no depende de ganancia/pérdida.
- Costo de Ventas aparece en 0: **no existe módulo de Inventario Final/Kardex** todavía.

## 6. Migraciones (orden) — `docs/gesticon_doc/`

| Archivo | Qué hace |
|---|---|
| `fase0_plan_contable.sql` | tablas base: plan de cuentas, tipos_gasto, períodos, parámetros, saldos_apertura, `reglas_imputacion` (inactiva) + seed PCGE |
| `fase0c_cuentas_venta_adicionales.sql` | 702, 704 |
| `fase0d_activo_diferido.sql` | 37/371 Activo Diferido (pedido de un contador real) |
| `fase1_imputacion_diario.sql` | imputaciones, asientos, `estado_imputacion` en registros |
| `fase1b` … `fase1f` | generación de asientos, reserva legal, planillas, caja, cobro/pago |
| **`fase1g_perfil_comercial.sql`** | columnas `vende_tipo`, `compra_tipo` y cuentas por defecto en `empresas` |
| **`fase1h_reglas_origen.sql`** | `reglas_imputacion.aplica_a` ('compra'/'venta') + reparte reglas existentes |

**Confirmar que `fase1g` y `fase1h` están aplicadas en producción** (fase1g faltaba y provocó el error `Unknown column 'vende_tipo'`).

## 7. Funcionalidades construidas (estado)

**Base**: multiempresa, login, sincronización SIRE (RVIE ventas / RCE compras), Registro de Ventas/Compras, Caja, Planillas, Apertura, Diario, Mayor, los 5 estados financieros, Cierre de período, alertas de certificados, guías/comprobantes.

**UI/UX**
- Sistema de temas: 5 temas + 4 acentos (`--gc-*` en `theme.css`).
- Barra de identidad con selector de empresa y de período (el período elegido persiste en sesión vía `Periodo::resolver`, cada pantalla abre en ese mes).
- Alerta flotante no invasiva (`floating_alert.php`) para descuadres.
- Dashboard por empresa (KPIs, gráfico, panel "Por clasificar") y dashboard del contador (prioriza lo accionable). Servicios: `DashboardEmpresaService`, `DashboardContadorService`.
- Manual de uso publicado como Artifact y exportado a PDF (`~/Downloads/Manual GestiCont.pdf`).

**PDFs (FPDF)** — `services/PdfReport.php` (cabecera de marca, `fila`, `tabla`, `alerta`, `seccion`). Endpoints `/empresas/{id}/<pantalla>/pdf` para Apertura, Balance de Comprobación (horizontal), Balance General, Estado de Resultados, Cambios en el Patrimonio y Flujo de Efectivo. Reemplazó al "Imprimir" del navegador (se veía poco profesional). El botón "📄 Descargar PDF" sale de `views/layout/print_header.php`.

**Clasificación con reglas (Fase 2)**
- Pantalla `Comprobantes → Clasificar → ⚙️ Reglas de clasificación` (`/empresas/{id}/imputacion/reglas`) con pestañas **Compras (proveedores)** / **Ventas (clientes)**.
- Regla = RUC de la contraparte → cuenta destino; por origen (un mismo RUC puede tener una regla de compra y otra de venta).
- Detector: lista proveedores/clientes del historial sin regla, con pendientes, total histórico y monto; una línea explica cuántos comprobantes/contrapartes se revisaron y qué meses aún no tienen RUC.
- En Clasificar aparece el panel **💡 Propuestas**: grupos por regla (proveedor → cuenta) y, como respaldo, por **perfil comercial** de la empresa. Un clic clasifica todo el grupo (`clasificar-lote` acepta `cuenta_id` y `regla_id`; suma `veces_aplicada`).
- **Regla general por origen** ("todas las compras van a Mercaderías", pensada p. ej. para una farmacia): tarjeta ⚡ en cada pestaña. Se guarda como una regla con `valor_criterio = '*'` (una por origen, sin migración). Prioridad en las propuestas: **regla por RUC > regla general > perfil comercial**. También cubre comprobantes sin RUC. Cambiarla actualiza la misma fila.
- Una regla ya usada no se puede borrar (FK con `imputaciones.regla_id`): se desactiva.
- **Perfil comercial** (Editar empresa): qué vende/compra (productos/servicios/ambos) y cuenta por defecto para cada uno. Con "ambos" no se sugiere nada (no se puede adivinar).
- Solo está implementado el criterio `ruc_contraparte`; `palabra_clave`, `tipo_comprobante`, `ruc_y_palabra` existen en el enum pero sin uso.

**Credenciales SUNAT** (`/empresas/{id}/certificado`): pantalla ancha con instrucciones al costado; indicador "✓ Ingresado" por campo (nunca se muestran los valores, van encriptados); **un campo vacío conserva el valor guardado** (antes se perdían las credenciales de API al guardar). Instrucciones: usar un **usuario SOL secundario** con las carpetas *Comprobantes de pago*, *Sistema Integrado de Registros Electrónicos* y *Credenciales de API SUNAT*; el ID/clave de API se generan con el usuario principal (MIGE RCE y RVIE - SIRE, alcance Desktop).

**Rangos de sincronización SIRE**: un período, **todo el año vigente desde enero** (hasta el mes anterior; en enero incluye el mes actual) o los últimos 12 meses. Con varios meses se amplía el límite de ejecución a 600 s.

**Sincronización de compras**: se corrigió la lectura del RUC del proveedor (`SunatApiService::docProveedor`, prueba `numDocIdentidadProveedor` y variantes, con búsqueda heurística como último recurso). Al re-sincronizar, las compras ya guardadas con RUC vacío **se completan** (el resultado muestra "N completados con RUC de proveedor").

## 8. Decisiones de producto ya tomadas

- Una venta/compra **no se asume cobrada/pagada**: se declara al clasificar (checkbox + fecha), porque SUNAT no lo informa.
- Los pagos, ventas y compras van alimentando el balance; apertura se llena una vez por año y no hay que "actualizarla" después.
- Filtros de Clasificar por período con pendientes y por tipo (todos/ventas/compras).
- El motor **propone, no clasifica solo**: siempre hay un clic de confirmación.
- Dashboards: priorizar lo accionable sobre métricas de vanidad.

## 9. Pendiente / abierto

1. **Producción**: aplicar `fase1g` y `fase1h` si no están; subir `ReglaImputacionService.php`, `ReglasImputacionController.php`, `views/reglas.php`, `CertificadoController.php`, `certificados/views/index.php`, `SunatApiService.php`, `SyncController.php`, `sync/views/index.php`.
2. **Re-sincronizar Compras de todos los meses** para completar el RUC de proveedor (la pantalla de reglas lista los meses que faltan). Si sigue en "0 proveedores" tras re-sincronizar, el nombre del campo de SIRE no es el supuesto: pedir una fila cruda del JSON de compras y ajustar `docProveedor`.
3. **Cuentas PCGE faltantes** detectadas al comparar con el Formulario 710 de SUNAT (empresa ACSA): 13, 16, 17, 18, 22, 30/31, 34, 35, 36. Se preguntó dos veces si agregarlas; **sin confirmar**. (Ojo: las "casillas" del 710 no son códigos PCGE.)
4. Código muerto del "Imprimir" del navegador: clases `.no-print`/`.print-only` en vistas y bloque `@media print` en `nav.css`. Inofensivo; decidir si limpiar.
5. Módulo de Inventario Final/Kardex (Costo de Ventas ≠ 0), aportes/retiros de capital (Cambios en el Patrimonio), y las Fases 3–5 del plan (exportación SUNAT, multiempresa avanzado, analítica).
6. Cosmético: en Cambios en el Patrimonio sale `-0.00` cuando la reserva legal es 0.
7. Regenerar `gesticont_db_sync.sql` para que refleje el esquema real de producción.
8. El manual de uso (Artifact/PDF) no incluye reglas de clasificación, perfil comercial ni PDFs; actualizarlo si se va a compartir.

## 10. Receta de pruebas locales

Se usó siempre el mismo patrón, aislado y con limpieza total al final:

1. `mariadb-install-db` en un directorio temporal + `mariadbd` en un puerto propio. **El socket debe estar en una ruta corta** (`/tmp/x.sock`); las rutas largas de `scratchpad` superan el límite de 103 caracteres y el servidor aborta.
2. Cargar `gesticont_db.sql` (sin indicar base), luego `gesticont_db_sync.sql` y las migraciones `fase*.sql` en orden sobre la base `gesticont`.
3. Parchar el esquema local: `ALTER TABLE registro_compras/registro_ventas ADD COLUMN periodo VARCHAR(6)`, `proveedor_tipo_doc`, `estado_sunat='1'`; y para credenciales `sol_usuario TEXT`, `api_client_id/secret TEXT`.
4. El dump trae un admin (`admin@gesticont.pe`); ponerle contraseña con `password_hash`. Crear empresa y fila en `empresa_usuarios`.
5. `php -S 127.0.0.1:PUERTO index.php` con `DB_*`, `APP_DEBUG=true` y `ENCRYPT_KEY`.
6. Login con `curl` (extraer `_token` del formulario, cookie jar), golpear los endpoints y verificar con SQL. Los PDFs se validan con `file` y `pdftotext -layout`.
7. Apagar `php`, `mariadb-admin shutdown`, borrar directorio y socket.

## 11. Cómo trabajar con el usuario

- Responder en español, corto y directo; el usuario es contador/dueño del producto, no necesariamente desarrollador.
- No hacer commit ni push salvo que lo pida (los commits los hace el usuario).
- No inventar códigos contables: verificar contra el PCGE oficial.
- Antes de crear algo grande que roza un diseño ya acordado, confirmar el alcance; para lo demás, actuar y probar de punta a punta.
- Cuando el usuario reporta un error de producción, revisar primero si falta aplicar una migración o subir un archivo.
