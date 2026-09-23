<?php
require_once ROOT . '/services/BalanceService.php';
require_once ROOT . '/services/EstadoResultadosService.php';

/**
 * GestiCont — Dashboard de empresa. Todo se calcula al vuelo desde
 * registro_ventas/registro_compras/asientos (nunca de resumen_mensual,
 * una tabla que quedó del diseño anterior y que nada llena — ver
 * modules/empresas/views/detalle.php antes de este cambio).
 */
class DashboardEmpresaService
{
    public static function generar(int $empresaId, int $anio, string $periodoActivo): array
    {
        $pdo = Model::db();

        // ── Ventas/Compras del año, mes a mes (para el gráfico) ──────────
        $stmtVMes = $pdo->prepare("
            SELECT SUBSTRING(periodo, 5, 2) AS mes, SUM(total) AS total, SUM(igv) AS igv
            FROM registro_ventas WHERE empresa_id = ? AND periodo LIKE ?
            GROUP BY mes
        ");
        $stmtVMes->execute([$empresaId, "{$anio}%"]);
        $ventasPorMes = array_column($stmtVMes->fetchAll(PDO::FETCH_ASSOC), null, 'mes');

        $stmtCMes = $pdo->prepare("
            SELECT SUBSTRING(periodo, 5, 2) AS mes, SUM(total) AS total, SUM(igv) AS igv
            FROM registro_compras WHERE empresa_id = ? AND periodo LIKE ?
            GROUP BY mes
        ");
        $stmtCMes->execute([$empresaId, "{$anio}%"]);
        $comprasPorMes = array_column($stmtCMes->fetchAll(PDO::FETCH_ASSOC), null, 'mes');

        $meses = [];
        $totalVentasAnio = 0.0; $totalComprasAnio = 0.0; $igvVentasAnio = 0.0; $igvComprasAnio = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $key = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $v = (float)($ventasPorMes[$key]['total'] ?? 0);
            $c = (float)($comprasPorMes[$key]['total'] ?? 0);
            $meses[] = ['mes' => $key, 'ventas' => $v, 'compras' => $c];
            $totalVentasAnio  += $v;
            $totalComprasAnio += $c;
            $igvVentasAnio    += (float)($ventasPorMes[$key]['igv']  ?? 0);
            $igvComprasAnio   += (float)($comprasPorMes[$key]['igv'] ?? 0);
        }

        // ── Utilidad del año — del motor contable real, si ya hay asientos.
        // Si no hay período contable o asientos generados, queda null (no
        // se inventa un número aproximado bajo el nombre de "Utilidad").
        $utilidadAnio = null;
        $stmtPer = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContableId = $stmtPer->fetchColumn();

        $balanceCuadra = null; // null = sin datos todavía
        if ($periodoContableId) {
            $stmtParam = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
            $stmtParam->execute([$empresaId, $periodoContableId]);
            $parametros = $stmtParam->fetch(PDO::FETCH_ASSOC);
            if ($parametros) {
                $fechaCorte = min(date('Y-m-d'), "{$anio}-12-31");
                $balance = BalanceService::comprobacion($empresaId, (int)$periodoContableId, $fechaCorte);
                if (!empty($balance['filas'])) {
                    $balanceCuadra = abs($balance['totales']['debe'] - $balance['totales']['haber']) < 0.01;
                    $resultado = EstadoResultadosService::generar($empresaId, (int)$periodoContableId, $fechaCorte, $parametros);
                    $utilidadAnio = $resultado['utilidad_ejercicio'] ?? null;
                }
            }
        }

        // ── Pendientes por clasificar — la acción concreta del día ───────
        $stmtPendV = $pdo->prepare("SELECT COUNT(*) FROM registro_ventas WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1'");
        $stmtPendV->execute([$empresaId]);
        $pendVentas = (int)$stmtPendV->fetchColumn();

        $stmtPendC = $pdo->prepare("SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1'");
        $stmtPendC->execute([$empresaId]);
        $pendCompras = (int)$stmtPendC->fetchColumn();

        // ── Cuentas por cobrar / pagar abiertas (spec del campo cobrado/pagado) ──
        $stmtCxC = $pdo->prepare("SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS monto FROM registro_ventas WHERE empresa_id = ? AND estado_sunat = '1' AND cobrado = 0");
        $stmtCxC->execute([$empresaId]);
        $cxc = $stmtCxC->fetch(PDO::FETCH_ASSOC);

        $stmtCxP = $pdo->prepare("SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS monto FROM registro_compras WHERE empresa_id = ? AND estado_sunat = '1' AND pagado = 0");
        $stmtCxP->execute([$empresaId]);
        $cxp = $stmtCxP->fetch(PDO::FETCH_ASSOC);

        // ── Período activo (el que se está trabajando ahora mismo) ───────
        $stmtVAct = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total FROM registro_ventas WHERE empresa_id = ? AND periodo = ?");
        $stmtVAct->execute([$empresaId, $periodoActivo]);
        $ventasActivo = (float)$stmtVAct->fetchColumn();

        $stmtCAct = $pdo->prepare("SELECT COALESCE(SUM(total),0) AS total FROM registro_compras WHERE empresa_id = ? AND periodo = ?");
        $stmtCAct->execute([$empresaId, $periodoActivo]);
        $comprasActivo = (float)$stmtCAct->fetchColumn();

        // ── Alertas propias de esta empresa ───────────────────────────────
        $stmtAl = $pdo->prepare("SELECT titulo, fecha_vence, nivel FROM alertas WHERE empresa_id = ? AND resuelta = 0 ORDER BY fecha_vence ASC LIMIT 4");
        $stmtAl->execute([$empresaId]);
        $alertas = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

        return [
            'anio'               => $anio,
            'meses'              => $meses,
            'ventas_anio'        => round($totalVentasAnio, 2),
            'compras_anio'       => round($totalComprasAnio, 2),
            'utilidad_anio'      => $utilidadAnio !== null ? round($utilidadAnio, 2) : null,
            'igv_neto_anio'      => round($igvVentasAnio - $igvComprasAnio, 2),
            'balance_cuadra'     => $balanceCuadra,
            'pendientes'         => $pendVentas + $pendCompras,
            'pendientes_ventas'  => $pendVentas,
            'pendientes_compras' => $pendCompras,
            'cxc_cant'           => (int)$cxc['cant'],   'cxc_monto' => round((float)$cxc['monto'], 2),
            'cxp_cant'           => (int)$cxp['cant'],   'cxp_monto' => round((float)$cxp['monto'], 2),
            'periodo_activo'     => $periodoActivo,
            'ventas_activo'      => round($ventasActivo, 2),
            'compras_activo'     => round($comprasActivo, 2),
            'resultado_activo'   => round($ventasActivo - $comprasActivo, 2),
            'alertas'            => $alertas,
        ];
    }
}
