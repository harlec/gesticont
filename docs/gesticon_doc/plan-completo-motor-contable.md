# Plan completo del Motor Contable — GestiCont

Este documento reemplaza la necesidad de consultar `BALANCE_VALENCIA-2025.xlsx`. Contiene
todas las fases del proyecto y toda la lógica de negocio extraída de esa plantilla,
generalizada como reglas de sistema (no como fórmulas de celda de un contribuyente
específico). Con **Inventario Inicial + Compras + Ventas + Planillas + Caja + Bancos**
como únicas entradas, el sistema debe poder generar automáticamente todo lo demás: Libro
Diario, Libro Mayor, Balance de Comprobación y los cuatro Estados Financieros.

---

## PARTE 1 — Fases del proyecto

### Fase 0 — Fundación (configuración, antes de mover datos reales)
- Catálogo de cuentas PCGE cargado (`cuentas_contables`), compartido entre tenants.
- `parametros_contables` por empresa/período: tasa IR, % distribución admin/ventas,
  moneda. Sin esto, nada del motor de asientos puede calcular correctamente.
- `periodos_contables` con apertura/cierre.
- Tabla `tipos_gasto` (nombres visibles → cuenta PCGE) para que la clasificación no
  requiera memorizar códigos.

### Fase 1 — Registro e imputación manual (alcance ya acordado)
- Carga manual de `saldos_apertura` (Inventario Inicial), fecha 1 de enero.
- Compras/Ventas importadas automáticamente vía SIRE.
- Planillas: import PLAME si está disponible, si no captura manual.
- Caja y Bancos: captura manual.
- Clasificación de cada documento a cuenta PCGE, 100% manual (botón/selector), con
  sugerencia no vinculante basada en la última cuenta usada para el mismo RUC.
- Generación automática de Asientos → Libro Diario → Libro Mayor → Balance de
  Comprobación → Estados Financieros (toda la lógica de la Parte 2 de este documento).
- Cierre de período con rollover automático al Inventario Inicial del año siguiente.

### Fase 2 — Motor de reglas de auto-clasificación
- `reglas_imputacion` activas (por RUC, palabra clave, tipo de comprobante), con el
  orden de especificidad y el manejo de conflictos ya definidos en la conversación previa
  (RUC+palabra > RUC > palabra clave > tipo de comprobante; empate real entre cuentas
  distintas → cola de revisión manual, nunca autoimputación silenciosa).
- Aprendizaje: ofrecer crear regla a partir de cada imputación manual sin regla previa.
- Niveles de confianza (autoimputar vs. sugerir) según qué tipo de criterio matcheó.

### Fase 3 — Cumplimiento y exportación SUNAT
- Exportación de Libro Diario y Libro Mayor en formato PLE (Programa de Libros
  Electrónicos) si el volumen del cliente lo exige.
- Validaciones de coherencia contra SIRE (que lo importado cuadre con lo declarado).
- Declaración jurada mensual (PDT 621) — cálculo de IGV a pagar (débito - crédito
  fiscal) e IR a cuenta, usando los mismos saldos que ya calcula el Balance.

### Fase 4 — Multiempresa avanzado y control interno
- Roles y permisos por empresa dentro del tenant (contador, asistente, solo lectura).
- Bitácora de auditoría de cambios sobre asientos ya generados (nunca editar un asiento
  cerrado directamente; se ajusta con un asiento de reverso + uno nuevo).
- Dashboard comparativo entre empresas/períodos para el contador que atiende varios
  clientes.

### Fase 5 — Analítica y proyección
- Reportes de tendencia (gastos por tipo, evolución de utilidad).
- Alertas automáticas (ej. IGV por pagar inusualmente alto, cuenta con saldo negativo
  que no debería tenerlo).

---

## PARTE 2 — Lógica de negocio completa (reemplaza al Excel)

### 2.1 Inventario Inicial (saldos de apertura)

Es el balance declarado al cierre del ejercicio anterior, cargado por cuenta PCGE con su
monto en el lado que corresponda (activo → debe, pasivo/patrimonio → haber). Reglas:
- La suma de todos los saldos de activo debe ser igual a la suma de pasivo + patrimonio
  (partida doble desde el origen). El sistema debe **rechazar** el guardado si no cuadra.
- Se carga una sola vez por período de apertura; en años siguientes se genera sola desde
  el cierre del período anterior (Fase 1, ver "Cierre").

### 2.2 Registro de Compras

Por cada documento de compra (de SIRE o manual), se necesita:
- Fecha, RUC/razón social del proveedor, tipo y número de comprobante.
- Monto base (afecto), monto inafecto, IGV, total.
- **Cuenta de destino por naturaleza**, asignada en la imputación: Mercadería (601),
  Materia Prima (602), Suministros (603), Gastos de personal (625), Transporte (631),
  Honorarios (632), Reparación/Mantenimiento (634), Alquileres (635), Servicios básicos
  (636), Publicidad (637), Servicios de contratistas (638), Otros servicios (639),
  Tributos (640), Seguros (651), Suscripciones (653), Suministros diversos (656), Otras
  cargas (659), o activo fijo (Inmueble/Maq./Equipo, 333) si es un bien de capital y no
  un gasto corriente.
- El sistema debe totalizar por cuenta y por mes (equivalente a la fila de totales de la
  hoja Compras del Excel), que es lo que alimenta la generación de asientos.

### 2.3 Registro de Ventas

Por cada documento de venta:
- Fecha, RUC/razón social del cliente, tipo y número de comprobante.
- Bienes gravados (7011), bienes no gravados (7012), prestación de servicios (704), IGV,
  total.
- Igual que compras, totalizado por cuenta y por mes.

### 2.4 Planillas

Por cada trabajador y período: sueldo, gratificación, asignación familiar, ESSALUD,
ONP/AFP (según régimen del trabajador), total remuneración. El total de aportes del
empleador (ESSALUD) y las retenciones del trabajador (ONP/AFP) deben quedar separados,
porque generan asientos distintos (gasto de personal vs. cuenta por pagar a la entidad
correspondiente).

### 2.5 Caja y Bancos

Movimientos de caja/banco por mes, clasificados en:
- **Ingresos**: préstamos recibidos, cobros a clientes.
- **Egresos**: pago de IGV, pago de Renta, ESSALUD, ONP, AFP, costos administrativos,
  remuneraciones, CTS, pago a proveedores, honorarios, obligaciones financieras, otras
  cuentas por pagar, tributos, otros gastos de gestión, gastos financieros.
- El saldo de caja se arrastra mes a mes (saldo inicial + ingresos - egresos = saldo
  final, que es el saldo inicial del mes siguiente).

### 2.6 Costo de Ventas

```
Costo de Ventas = Inventario Inicial de mercadería/materia prima/suministros
                 + Compras del período (cuenta 601/602/603)
                 − Inventario Final (conteo físico, dato manual — no se calcula solo)
```
**Importante**: el inventario final no puede calcularse automáticamente sin un módulo de
kardex/control de stock (que el Excel tampoco tenía — era un número digitado a mano tras
un conteo físico). Si Fase 1 no incluye kardex, este campo sigue siendo captura manual
mensual. Documentar esto explícitamente para que no se asuma que "todo" incluye esto.

### 2.7 Generación de Asientos (motor central de partida doble)

Cada fuente genera asientos según estas reglas fijas (los porcentajes son parámetros de
`parametros_contables`, no valores fijos):

**Por Compras** (asiento por naturaleza):
```
DEBE: cuenta de gasto por naturaleza (601, 602, ..., 659) = total de esa cuenta en el mes
DEBE: IGV (401.1) = IGV del período
HABER: Proveedores (421) = total de compras + IGV
```

**Reclasificación por destino** (de las cuentas de gasto por naturaleza a cuentas de
función, usando los % configurados, ej. 30% administración / 70% ventas — o el costo de
producción íntegro si aplica a manufactura):
```
DEBE: Costo de Producción (92) = gastos de naturaleza productiva
DEBE: Gastos de Administración (94) = Σ(gastos por naturaleza) × %admin
DEBE: Gastos de Venta (95) = Σ(gastos por naturaleza) × %ventas
HABER: Cargas Imputables a Cuentas de Costos (791) = suma de los tres anteriores
```

**Por Ventas**:
```
DEBE: Clientes/Cuentas por cobrar (121) = total de ventas + IGV
HABER: Ventas de mercadería (7011) = bienes gravados del mes
HABER: Prestación de servicios (704) = servicios del mes
HABER: IGV (401) = IGV de ventas del mes
```
Y el asiento de costo de venta correspondiente:
```
DEBE: Costo de Venta (691)
HABER: Mercadería (201) por el costo de lo vendido (de 2.6)
```

**Por Planillas**:
```
DEBE: Sueldos (621), Gratificaciones (625), Asignación familiar (6251), ESSALUD (6252)
HABER: ESSALUD por pagar (4031), ONP por pagar (4032), AFP por pagar (407),
       Remuneraciones por pagar (411)
```
Reclasificación por destino igual que compras (% admin/ventas configurable) hacia cuentas
94/95, con contrapartida en 791 (Cargas Imputables).

**Por Caja** (dos bloques, debe y haber de caja, separados):
```
DEBE de Caja: Caja y Bancos (10) = ingresos (préstamos + cobros a clientes)
HABER de Caja: Clientes (121) por lo cobrado, Obligaciones financieras (451) por lo
               prestado

HABER de Caja (egresos, un asiento por cada cuenta pagada): IGV, Renta, ESSALUD, ONP,
AFP, Costos Admin, Remuneraciones, CTS, Proveedores, Honorarios, Obligaciones
Financieras, Otras cuentas, Tributos, Otros gastos — cada uno con su cuenta contable
específica, y la contrapartida en Caja y Bancos (101) por el total pagado.
```

**Validación transversal**: ningún asiento se persiste si Σdebe ≠ Σhaber de sus líneas.

### 2.8 Libro Diario

Formato oficial SUNAT: número de asiento, fecha, glosa, referencia (número correlativo +
documento sustentatorio), código de cuenta (2/3/4 dígitos, con nombre resuelto por
`cuentas_contables`), columna de parciales, debe, haber. **Es la fuente única**: cada
asiento generado en 2.7 se inserta aquí directamente, no en una tabla intermedia.

### 2.9 Libro Mayor

Por cada cuenta PCGE usada: todas las líneas de `asientos_detalle` que la tocan,
agrupadas en formato "cuenta T" (columna debe, columna haber), con:
```
Suma Debe = Σ(debe) de esa cuenta en el período (incluye saldo de apertura si es cuenta
             de balance)
Suma Haber = Σ(haber) de esa cuenta en el período
```
Se calcula por consulta agregada — no se mantiene como tabla separada que hay que
recalcular manualmente.

### 2.10 Balance de Comprobación (8 columnas)

Por cada cuenta, en este orden exacto de cálculo:

1. **Sumas del Mayor**: Debe = suma debe del Mayor · Haber = suma haber del Mayor.
2. **Saldos**: Deudor = `IF(Debe > Haber, Debe - Haber, 0)` · Acreedor =
   `IF(Haber > Debe, Haber - Debe, 0)`.
3. Según la **clase de cuenta** (definida en `cuentas_contables.tipo`):
   - **Activo / Pasivo / Patrimonio** (clases 1 a 5) → columnas **Inventario**: Activo =
     `IF(Deudor > Acreedor, Deudor - Acreedor, 0)` · Pasivo =
     `IF(Acreedor > Deudor, Acreedor - Deudor, 0)`.
   - **Ingreso / Gasto por naturaleza** (clases 6 y 7) → columnas **Resultado por
     Naturaleza**: Pérdidas = `IF(Deudor > Acreedor, Deudor - Acreedor, 0)` · Ganancias =
     `IF(Acreedor > Deudor, Acreedor - Deudor, 0)`.
   - **Cuentas de destino/función** (clase 9) → columna **Resultado por Función**:
     Pérdidas = el monto de la reclasificación por destino calculado en 2.7 (viene del
     asiento de reclasificación, no de un cálculo IF adicional).

Este es el "corazón" del reporte — todos los estados financieros posteriores son sumas de
subconjuntos de estas columnas, nunca recálculos independientes.

### 2.11 Balance General (Estado de Situación Financiera)

```
Activo Corriente = Σ (columna Activo del Balance, cuentas clase 1 y 2 corrientes)
Activo No Corriente = Σ (columna Activo del Balance, cuentas clase 3 no corrientes)
TOTAL ACTIVO = Activo Corriente + Activo No Corriente

Pasivo Corriente = Σ (columna Pasivo del Balance, cuentas de corto plazo, ej. 40x, 42x)
Pasivo No Corriente = Σ (columna Pasivo del Balance, obligaciones financieras 45x, etc.)
TOTAL PASIVO = Pasivo Corriente + Pasivo No Corriente

Patrimonio = Capital Social (501) + Resultados Acumulados (591) +
             Resultado del Ejercicio (5911 positivo / 5912 negativo, viene de 2.12)
TOTAL PASIVO Y PATRIMONIO = TOTAL PASIVO + Patrimonio
```
**Control obligatorio**: TOTAL ACTIVO debe ser igual a TOTAL PASIVO Y PATRIMONIO. Si no
cuadra, hay un error en algún asiento anterior y el sistema debe alertarlo, no mostrar
el reporte como si estuviera bien.

### 2.12 Estado de Resultados (Pérdidas y Ganancias)

```
Ingresos por Ventas          = columna Ganancias, cuentas 70x (Resultado x Naturaleza)
(−) Costo de Ventas          = columna Pérdidas, cuenta 69x
= Utilidad Bruta

(−) Costo de Producción      = columna Pérdidas x Función, cuenta 92
(−) Gastos de Administración = columna Pérdidas x Función, cuenta 94
(−) Gastos de Venta          = columna Pérdidas x Función, cuenta 95
(−) Gastos Financieros       = columna Pérdidas x Función, cuenta 96
= Utilidad Operativa

(−) Cargas Excepcionales     = cuenta 663 (Resultado x Naturaleza, Pérdidas)
(+) Ingresos Financieros     = cuenta 779 (Resultado x Naturaleza, Ganancias)
= Utilidad Antes de Impuestos

(−) Impuesto a la Renta      = Utilidad Antes de Impuestos × tasa_ir (parámetro, no fijo)
= Utilidad del Ejercicio

(−) Reserva Legal            = Utilidad del Ejercicio × 10% (o el % legal vigente,
                                también parametrizable)
= Utilidad Antes de Repartición
```

### 2.13 Determinación del Impuesto a la Renta

```
IR Anual = Utilidad Antes de Impuestos × tasa_ir
Pagos a cuenta del período (ya pagados vía Caja, cuenta 401.7) = suma de lo pagado
Saldo por Regularizar = IR Anual − Pagos a cuenta

Coeficiente para pagos a cuenta del siguiente ejercicio =
    (IR Anual / Total de Ingresos del ejercicio) × 100
```

### 2.14 Estado de Cambios en el Patrimonio

Columnas: Capital, Reservas Legales, Resultados Acumulados, Total.
```
Saldo Inicial = valores del Inventario Inicial (2.1) para esas cuentas
+ Utilidad (Pérdida) Neta del Ejercicio (de 2.12)
− Distribuciones/reservas del período
= Saldo Final (se convierte en el saldo de apertura patrimonial del año siguiente)
```

### 2.15 Estado de Flujo de Efectivo

Tres bloques, cada uno como suma de movimientos de Caja/Banco (2.5) clasificados por
actividad:
```
ACTIVIDAD DE OPERACIÓN:
  Cobranza a clientes − (Pago a proveedores + Pago a trabajadores)
  = Flujo de Operación

ACTIVIDAD DE INVERSIÓN:
  − Compra de inmueble, maquinaria y equipo
  − Compra de inversiones
  = Flujo de Inversión

ACTIVIDAD DE FINANCIAMIENTO:
  + Préstamos recibidos
  − Amortización de obligaciones
  = Flujo de Financiamiento

Aumento/Disminución Neta de Efectivo = suma de los tres flujos
Saldo Inicial de Efectivo = saldo de Caja y Bancos al inicio del período (Mayor)
Saldo Final de Efectivo = Saldo Inicial + Aumento/Disminución Neta
```
**Control obligatorio**: el Saldo Final de Efectivo aquí calculado debe coincidir con el
saldo de la cuenta 101/104 en el Balance General. Si no coincide, hay un movimiento de
caja/banco que no se registró como debía.

### 2.16 Cierre de Período

Al cerrar:
1. Se calcula el resultado del ejercicio (2.12) y se registra como asiento final
   (Utilidad → 5911, o Pérdida → 5912).
2. Se generan los `saldos_apertura` del período siguiente tomando los saldos finales de
   todas las cuentas de Balance General (activo, pasivo, patrimonio) — **no** las cuentas
   de resultado (6, 7, 9), que se cierran a cero cada período.
3. El período queda marcado como `cerrado` y sus asientos pasan a ser inmutables (toda
   corrección posterior se hace con asiento de reverso + asiento nuevo, nunca editando
   directamente).

---

## PARTE 3 — Instrucción para Claude Code

1. Auditar el proyecto GestiCont actual: qué de la Parte 1 (Fase 0/1) y la Parte 2 ya
   existe, qué se adapta, qué se construye.
2. Implementar en el orden: Fase 0 → Fase 1, siguiendo exactamente la secuencia de la
   Parte 2 (2.1 a 2.16) — cada paso depende de que el anterior esté correcto, así que no
   saltar orden (ej. no construir Balance de Comprobación sin que el Mayor agregue
   correctamente del Diario primero).
3. Cada regla de la Parte 2 debe quedar como lógica de servicio testeable de forma
   aislada (dado un conjunto de asientos, el Balance de Comprobación calculado debe
   cuadrar) — no como fórmulas dispersas en la capa de presentación.
4. Los controles obligatorios marcados en 2.11 y 2.15 (activo = pasivo+patrimonio, saldo
   de efectivo del flujo = saldo de efectivo del balance) deben implementarse como
   validaciones automáticas que alerten, no como algo que el usuario tenga que revisar
   a ojo.
5. Fases 2 a 5 quedan fuera de este alcance inmediato — no implementar, pero las
   estructuras marcadas como "lista para Fase 2" (`reglas_imputacion`,
   `imputaciones.regla_id`) sí deben existir desde ahora.
