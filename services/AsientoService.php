<?php
/**
 * GestiCont — Generación de asientos (Libro Diario) a partir de
 * compras/ventas ya imputadas. Ver docs/gesticon_doc/plan-completo-motor-contable.md
 * sección 2.7 para las reglas de negocio que implementa este servicio.
 */
class AsientoService
{
    /**
     * Genera (o regenera, si el período sigue abierto) los asientos de
     * Compras, Ventas y Reclasificación por Destino de un período YYYYMM.
     * No genera el asiento de Costo de Venta (spec 2.6): requiere
     * Inventario Final, que hoy es captura manual y todavía no existe
     * como pantalla — se deja pendiente a propósito, documentado abajo.
     */
    public static function generarPeriodo(int $empresaId, string $periodo, int $usuarioId): array
    {
        $pdo = Model::db();
        $anio = (int)substr($periodo, 0, 4);

        $periodoContableId = self::obtenerOCrearPeriodoContable($pdo, $empresaId, $anio);

        $stmtPer = $pdo->prepare("SELECT estado FROM periodos_contables WHERE id = ?");
        $stmtPer->execute([$periodoContableId]);
        $per = $stmtPer->fetch(PDO::FETCH_ASSOC);
        if (!$per || $per['estado'] === 'cerrado') {
            return ['error' => "El período {$anio} está cerrado — no se pueden generar ni regenerar asientos."];
        }

        $parametros = self::obtenerOCrearParametros($pdo, $empresaId, $periodoContableId);

        $resultado = ['periodo' => $periodo, 'asientos' => []];

        Model::beginTransaction();
        try {
            $asientoCompras = self::generarAsientoCompras($pdo, $empresaId, $periodoContableId, $periodo, $usuarioId);
            if ($asientoCompras) $resultado['asientos']['compras'] = $asientoCompras;

            $asientoVentas = self::generarAsientoVentas($pdo, $empresaId, $periodoContableId, $periodo, $usuarioId);
            if ($asientoVentas) $resultado['asientos']['ventas'] = $asientoVentas;

            $asientoPlanillas = self::generarAsientoPlanillas($pdo, $empresaId, $periodoContableId, $periodo, $usuarioId);
            if ($asientoPlanillas) $resultado['asientos']['planillas'] = $asientoPlanillas;

            $asientoCajaIngresos = self::generarAsientoCaja($pdo, $empresaId, $periodoContableId, $periodo, 'ingreso');
            if ($asientoCajaIngresos) $resultado['asientos']['caja_ingresos'] = $asientoCajaIngresos;

            $asientoCajaEgresos = self::generarAsientoCaja($pdo, $empresaId, $periodoContableId, $periodo, 'egreso');
            if ($asientoCajaEgresos) $resultado['asientos']['caja_egresos'] = $asientoCajaEgresos;

            $asientoDestino = self::generarAsientoReclasificacion($pdo, $empresaId, $periodoContableId, $periodo, $usuarioId, $parametros);
            if ($asientoDestino) $resultado['asientos']['reclasificacion'] = $asientoDestino;

            Model::commit();
        } catch (Exception $e) {
            Model::rollback();
            return ['error' => 'No se pudo generar: ' . $e->getMessage()];
        }

        if (empty($resultado['asientos'])) {
            $resultado['aviso'] = 'No hay compras ni ventas imputadas para este período — nada que generar.';
        }

        return $resultado;
    }

    private static function obtenerOCrearPeriodoContable(PDO $pdo, int $empresaId, int $anio): int
    {
        $stmt = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmt->execute([$empresaId, $anio]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['id'];

        $pdo->prepare("
            INSERT INTO periodos_contables (empresa_id, anio, estado, fecha_apertura)
            VALUES (?, ?, 'abierto', ?)
        ")->execute([$empresaId, $anio, "{$anio}-01-01"]);
        return (int)$pdo->lastInsertId();
    }

    private static function obtenerOCrearParametros(PDO $pdo, int $empresaId, int $periodoContableId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
        $stmt->execute([$empresaId, $periodoContableId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        // Se insertan los valores por defecto ya definidos en el esquema
        // (tasa_ir 29.50%, 30% admin / 70% ventas) — el contador debe
        // revisarlos y ajustarlos en parametros_contables si no aplican.
        $pdo->prepare("
            INSERT INTO parametros_contables (empresa_id, periodo_id)
            VALUES (?, ?)
        ")->execute([$empresaId, $periodoContableId]);

        $stmt->execute([$empresaId, $periodoContableId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private static function siguienteCorrelativo(PDO $pdo, int $empresaId): int
    {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(correlativo), 0) + 1 FROM asientos WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        return (int)$stmt->fetchColumn();
    }

    /** Borra un asiento previo del mismo período+origen+glosa, si existe (permite regenerar mientras el período esté abierto). */
    private static function borrarAsientoPrevio(PDO $pdo, int $empresaId, string $glosa): void
    {
        $stmt = $pdo->prepare("DELETE FROM asientos WHERE empresa_id = ? AND glosa = ?");
        $stmt->execute([$empresaId, $glosa]);
    }

    private static function insertarAsiento(PDO $pdo, int $empresaId, int $periodoContableId, string $fecha, string $glosa, string $origen, array $lineas): array
    {
        $correlativo = self::siguienteCorrelativo($pdo, $empresaId);
        $pdo->prepare("
            INSERT INTO asientos (empresa_id, periodo_id, correlativo, fecha, glosa, origen)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$empresaId, $periodoContableId, $correlativo, $fecha, $glosa, $origen]);
        $asientoId = (int)$pdo->lastInsertId();

        $sumaDebe = round(array_sum(array_column($lineas, 'debe')), 2);
        $sumaHaber = round(array_sum(array_column($lineas, 'haber')), 2);
        if ($sumaDebe !== $sumaHaber) {
            throw new Exception("Asiento '{$glosa}' descuadrado: Debe {$sumaDebe} ≠ Haber {$sumaHaber}");
        }

        $stmtLinea = $pdo->prepare("
            INSERT INTO asientos_detalle (asiento_id, cuenta_id, debe, haber) VALUES (?, ?, ?, ?)
        ");
        foreach ($lineas as $l) {
            if ($l['debe'] == 0 && $l['haber'] == 0) continue;
            $stmtLinea->execute([$asientoId, $l['cuenta_id'], $l['debe'], $l['haber']]);
        }

        return ['id' => $asientoId, 'correlativo' => $correlativo, 'glosa' => $glosa, 'lineas' => count($lineas)];
    }

    private static function cuentaId(PDO $pdo, string $codigo): int
    {
        $stmt = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = ? AND empresa_id IS NULL LIMIT 1");
        $stmt->execute([$codigo]);
        $id = $stmt->fetchColumn();
        if (!$id) throw new Exception("Cuenta contable {$codigo} no existe en el catálogo — revisar fase0_plan_contable.sql");
        return (int)$id;
    }

    private static function generarAsientoCompras(PDO $pdo, int $empresaId, int $periodoContableId, string $periodo, int $usuarioId): ?array
    {
        $stmtTot = $pdo->prepare("
            SELECT COALESCE(SUM(igv),0) AS igv, COALESCE(SUM(total),0) AS total, COUNT(*) AS cant
            FROM registro_compras
            WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'imputado' AND estado_sunat = '1'
        ");
        $stmtTot->execute([$empresaId, $periodo]);
        $tot = $stmtTot->fetch(PDO::FETCH_ASSOC);
        if ((int)$tot['cant'] === 0) return null;

        $stmtCuentas = $pdo->prepare("
            SELECT i.cuenta_id, SUM(i.monto) AS monto
            FROM imputaciones i
            JOIN registro_compras rc ON rc.id = i.registro_compra_id
            WHERE rc.empresa_id = ? AND rc.periodo = ? AND rc.estado_imputacion = 'imputado' AND rc.estado_sunat = '1'
            GROUP BY i.cuenta_id
        ");
        $stmtCuentas->execute([$empresaId, $periodo]);
        $porCuenta = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

        $glosa = "Centralización de Compras {$periodo}";
        self::borrarAsientoPrevio($pdo, $empresaId, $glosa);

        $lineas = [];
        foreach ($porCuenta as $c) {
            $lineas[] = ['cuenta_id' => (int)$c['cuenta_id'], 'debe' => round((float)$c['monto'], 2), 'haber' => 0];
        }
        $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '4011'), 'debe' => round((float)$tot['igv'], 2), 'haber' => 0];
        $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '421'), 'debe' => 0, 'haber' => round((float)$tot['total'], 2)];

        $fecha = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return self::insertarAsiento($pdo, $empresaId, $periodoContableId, $fecha, $glosa, 'compra', $lineas);
    }

    private static function generarAsientoVentas(PDO $pdo, int $empresaId, int $periodoContableId, string $periodo, int $usuarioId): ?array
    {
        $stmtTot = $pdo->prepare("
            SELECT COALESCE(SUM(igv),0) AS igv, COALESCE(SUM(total),0) AS total, COUNT(*) AS cant
            FROM registro_ventas
            WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'imputado' AND estado_sunat = '1'
        ");
        $stmtTot->execute([$empresaId, $periodo]);
        $tot = $stmtTot->fetch(PDO::FETCH_ASSOC);
        if ((int)$tot['cant'] === 0) return null;

        $stmtCuentas = $pdo->prepare("
            SELECT i.cuenta_id, SUM(i.monto) AS monto
            FROM imputaciones i
            JOIN registro_ventas rv ON rv.id = i.registro_venta_id
            WHERE rv.empresa_id = ? AND rv.periodo = ? AND rv.estado_imputacion = 'imputado' AND rv.estado_sunat = '1'
            GROUP BY i.cuenta_id
        ");
        $stmtCuentas->execute([$empresaId, $periodo]);
        $porCuenta = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

        $glosa = "Centralización de Ventas {$periodo}";
        self::borrarAsientoPrevio($pdo, $empresaId, $glosa);

        $lineas = [];
        $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '121'), 'debe' => round((float)$tot['total'], 2), 'haber' => 0];
        foreach ($porCuenta as $c) {
            $lineas[] = ['cuenta_id' => (int)$c['cuenta_id'], 'debe' => 0, 'haber' => round((float)$c['monto'], 2)];
        }
        $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '4011'), 'debe' => 0, 'haber' => round((float)$tot['igv'], 2)];

        $fecha = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return self::insertarAsiento($pdo, $empresaId, $periodoContableId, $fecha, $glosa, 'venta', $lineas);
    }

    /**
     * Asiento de planillas (spec 2.7 "Por Planillas"). La spec original
     * proponía cuentas que no existen en el PCGE 2019 (625 Gratificaciones,
     * 6251, 6252) — se corrige con las oficiales vigentes: 621
     * Remuneraciones (bruto) y 627 Seguridad, Previsión Social y Otras
     * Contribuciones (ESSALUD, aporte del empleador). El neto a pagar, el
     * ESSALUD por pagar y la retención de pensión (ONP o AFP según cada
     * trabajador) quedan separados en el Haber porque son pasivos distintos.
     */
    private static function generarAsientoPlanillas(PDO $pdo, int $empresaId, int $periodoContableId, string $periodo, int $usuarioId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(sueldo + gratificacion + asignacion_familiar), 0) AS bruto,
                COALESCE(SUM(essalud), 0) AS essalud,
                COALESCE(SUM(CASE WHEN regimen_pension = 'onp' THEN retencion_pension ELSE 0 END), 0) AS retencion_onp,
                COALESCE(SUM(CASE WHEN regimen_pension = 'afp' THEN retencion_pension ELSE 0 END), 0) AS retencion_afp,
                COALESCE(SUM(retencion_pension), 0) AS retencion_total,
                COUNT(*) AS cant
            FROM planillas
            WHERE empresa_id = ? AND periodo = ?
        ");
        $stmt->execute([$empresaId, $periodo]);
        $tot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ((int)$tot['cant'] === 0) return null;

        $bruto  = round((float)$tot['bruto'], 2);
        $essalud = round((float)$tot['essalud'], 2);
        $retOnp  = round((float)$tot['retencion_onp'], 2);
        $retAfp  = round((float)$tot['retencion_afp'], 2);
        $neto    = round($bruto - (float)$tot['retencion_total'], 2);

        $glosa = "Centralización de Planillas {$periodo}";
        self::borrarAsientoPrevio($pdo, $empresaId, $glosa);

        $lineas = [
            ['cuenta_id' => self::cuentaId($pdo, '621'), 'debe' => $bruto, 'haber' => 0],
        ];
        if ($essalud > 0) $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '627'), 'debe' => $essalud, 'haber' => 0];
        $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '411'), 'debe' => 0, 'haber' => $neto];
        if ($essalud > 0) $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '4031'), 'debe' => 0, 'haber' => $essalud];
        if ($retOnp > 0)  $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '4032'), 'debe' => 0, 'haber' => $retOnp];
        if ($retAfp > 0)  $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '417'),  'debe' => 0, 'haber' => $retAfp];

        $fecha = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return self::insertarAsiento($pdo, $empresaId, $periodoContableId, $fecha, $glosa, 'planilla', $lineas);
    }

    /**
     * Asiento de Caja y Bancos (spec 2.7 "Por Caja"). Se usa siempre la
     * cuenta 101 (Caja) como contrapartida — el módulo no distingue caja
     * física de cuenta bancaria (no hay conciliación bancaria todavía), así
     * que "Caja y Bancos" se trata como una sola bolsa por simplicidad.
     * Ingresos y egresos se generan como dos asientos separados (igual que
     * Compras/Ventas), no uno combinado, para mantener cada uno auditable
     * por su propio origen.
     */
    private static function generarAsientoCaja(PDO $pdo, int $empresaId, int $periodoContableId, string $periodo, string $tipo): ?array
    {
        $stmt = $pdo->prepare("
            SELECT cuenta_id, SUM(monto) AS monto
            FROM caja_movimientos
            WHERE empresa_id = ? AND tipo = ? AND DATE_FORMAT(fecha, '%Y%m') = ? AND cuenta_id IS NOT NULL
            GROUP BY cuenta_id
        ");
        $stmt->execute([$empresaId, $tipo, $periodo]);
        $porCuenta = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($porCuenta)) return null;

        $total = round(array_sum(array_column($porCuenta, 'monto')), 2);
        $etiqueta = $tipo === 'ingreso' ? 'Ingresos' : 'Egresos';
        $glosa = "Centralización de Caja - {$etiqueta} {$periodo}";
        self::borrarAsientoPrevio($pdo, $empresaId, $glosa);

        $lineas = [];
        if ($tipo === 'ingreso') {
            $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '101'), 'debe' => $total, 'haber' => 0];
            foreach ($porCuenta as $c) $lineas[] = ['cuenta_id' => (int)$c['cuenta_id'], 'debe' => 0, 'haber' => round((float)$c['monto'], 2)];
        } else {
            foreach ($porCuenta as $c) $lineas[] = ['cuenta_id' => (int)$c['cuenta_id'], 'debe' => round((float)$c['monto'], 2), 'haber' => 0];
            $lineas[] = ['cuenta_id' => self::cuentaId($pdo, '101'), 'debe' => 0, 'haber' => $total];
        }

        $fecha = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return self::insertarAsiento($pdo, $empresaId, $periodoContableId, $fecha, $glosa, 'caja', $lineas);
    }

    /**
     * Reclasifica hacia 94/95 (Gastos de Administración / Gastos de Venta)
     * las cuentas de Compras marcadas es_inventariable = 0 (la mercadería/
     * materia prima 601/602/603/604 se queda en 60 hasta que exista el
     * módulo de Kardex/Inventario Final, spec 2.6) más el gasto de personal
     * de Planillas (621+627) — un trabajador también se reparte por destino
     * igual que cualquier otro gasto operativo.
     */
    private static function generarAsientoReclasificacion(PDO $pdo, int $empresaId, int $periodoContableId, string $periodo, int $usuarioId, array $parametros): ?array
    {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(i.monto),0) AS total
            FROM imputaciones i
            JOIN registro_compras rc ON rc.id = i.registro_compra_id
            JOIN cuentas_contables c  ON c.id = i.cuenta_id
            WHERE rc.empresa_id = ? AND rc.periodo = ? AND rc.estado_imputacion = 'imputado' AND rc.estado_sunat = '1'
              AND c.tipo = 'gasto' AND c.es_inventariable = 0
        ");
        $stmt->execute([$empresaId, $periodo]);
        $totalCompras = (float)$stmt->fetchColumn();

        $stmtPlanilla = $pdo->prepare("
            SELECT COALESCE(SUM(sueldo + gratificacion + asignacion_familiar + essalud), 0)
            FROM planillas WHERE empresa_id = ? AND periodo = ?
        ");
        $stmtPlanilla->execute([$empresaId, $periodo]);
        $totalPlanilla = (float)$stmtPlanilla->fetchColumn();

        // Gastos pagados DIRECTO por Caja (alquiler, servicios, honorarios,
        // etc. sin pasar por una compra clasificada) — sin esto se quedaban
        // atascados en el elemento 6 y nunca llegaban al Balance General,
        // descuadrándolo en silencio (detectado al simular un egreso de
        // Caja contra una cuenta de gasto).
        $stmtCaja = $pdo->prepare("
            SELECT COALESCE(SUM(cm.monto), 0)
            FROM caja_movimientos cm
            JOIN cuentas_contables c ON c.id = cm.cuenta_id
            WHERE cm.empresa_id = ? AND cm.tipo = 'egreso' AND DATE_FORMAT(cm.fecha, '%Y%m') = ?
              AND c.tipo = 'gasto' AND c.es_inventariable = 0
        ");
        $stmtCaja->execute([$empresaId, $periodo]);
        $totalCaja = (float)$stmtCaja->fetchColumn();

        $total = round($totalCompras + $totalPlanilla + $totalCaja, 2);
        if ($total <= 0) return null;

        $pctAdmin  = (float)$parametros['pct_gastos_admin'];
        $pctVentas = (float)$parametros['pct_gastos_ventas'];

        $montoAdmin  = round($total * $pctAdmin / 100, 2);
        $montoVentas = round($total - $montoAdmin, 2); // evita descuadre por redondeo

        $glosa = "Reclasificación de gastos por destino {$periodo}";
        self::borrarAsientoPrevio($pdo, $empresaId, $glosa);

        $lineas = [
            ['cuenta_id' => self::cuentaId($pdo, '94'),  'debe' => $montoAdmin,  'haber' => 0],
            ['cuenta_id' => self::cuentaId($pdo, '95'),  'debe' => $montoVentas, 'haber' => 0],
            ['cuenta_id' => self::cuentaId($pdo, '791'), 'debe' => 0, 'haber' => round($montoAdmin + $montoVentas, 2)],
        ];

        $fecha = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return self::insertarAsiento($pdo, $empresaId, $periodoContableId, $fecha, $glosa, 'compra', $lineas);
    }
}
