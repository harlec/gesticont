<?php
require_once ROOT . '/services/BalanceService.php';

/**
 * GestiCont — Estado de Flujo de Efectivo. Ver
 * plan-completo-motor-contable.md sección 2.15.
 *
 * Se calcula 100% a partir de caja_movimientos clasificados por cuenta
 * contrapartida — nunca por diferencia de balances, para que el control
 * obligatorio (Saldo Final aquí = saldo real de 101/104 en el Balance)
 * sea una verificación real y no una tautología.
 */
class FlujoEfectivoService
{
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte): array
    {
        $pdo = Model::db();

        $stmt = $pdo->prepare("
            SELECT cm.tipo, c.codigo, SUM(cm.monto) AS monto
            FROM caja_movimientos cm
            JOIN cuentas_contables c ON c.id = cm.cuenta_id
            JOIN periodos_contables pc ON pc.empresa_id = cm.empresa_id AND pc.id = ?
            WHERE cm.empresa_id = ? AND cm.fecha <= ? AND YEAR(cm.fecha) = pc.anio
            GROUP BY cm.tipo, c.codigo
        ");
        $stmt->execute([$periodoContableId, $empresaId, $fechaCorte]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $mapa = []; // "ingreso:121" => monto
        foreach ($filas as $f) $mapa[$f['tipo'] . ':' . $f['codigo']] = (float)$f['monto'];
        $get = fn(string $tipo, string $codigo) => $mapa["{$tipo}:{$codigo}"] ?? 0.0;
        $sumaTipo = function (string $tipo) use ($filas) {
            $t = 0.0;
            foreach ($filas as $f) if ($f['tipo'] === $tipo) $t += (float)$f['monto'];
            return $t;
        };

        $cuentasInversion = ['333', '334', '335', '336'];

        // Actividad de Inversión (compra de activo fijo pagada en efectivo)
        $compraActivoFijo = 0.0;
        foreach ($cuentasInversion as $cod) $compraActivoFijo += $get('egreso', $cod);
        $flujoInversion = round(-$compraActivoFijo, 2);

        // Actividad de Financiamiento
        $prestamosRecibidos = $get('ingreso', '451');
        $amortizacion       = $get('egreso', '451');
        $flujoFinanciamiento = round($prestamosRecibidos - $amortizacion, 2);

        // Actividad de Operación: la spec (2.15) solo nombra 3 líneas
        // (cobranza, pago a proveedores, pago a trabajadores), pero un
        // egreso operativo real tiene más categorías (ESSALUD, ONP, AFP,
        // tributos, etc.) — si solo se restaran esas 3, el Saldo Final no
        // reconciliaría con la Caja real y el control de la spec fallaría
        // en falso. Por eso "Operación" es todo lo que NO es Inversión ni
        // Financiamiento (catch-all), y las 3 líneas con nombre son solo
        // el desglose informativo dentro de ese total.
        $cobranzaClientes = $get('ingreso', '121');
        $pagoProveedores  = $get('egreso', '421');
        $pagoTrabajadores = $get('egreso', '411');

        $totalIngresos = $sumaTipo('ingreso');
        $totalEgresos  = $sumaTipo('egreso');
        $ingresoOperacionTotal = $totalIngresos - $prestamosRecibidos;
        $egresoOperacionTotal  = $totalEgresos - $compraActivoFijo - $amortizacion;

        // Neto de todo lo operativo que no tiene línea con nombre propio en
        // la spec (tributos, ESSALUD, ONP, AFP, otros cobros/pagos) — se
        // muestra como una sola línea para no perder el detalle sin tener
        // que enumerar cada cuenta posible.
        $otrosIngresosOp = round($ingresoOperacionTotal - $cobranzaClientes, 2);
        $otrosEgresosOp  = round($egresoOperacionTotal - $pagoProveedores - $pagoTrabajadores, 2);
        $otrosOperacion  = round($otrosIngresosOp - $otrosEgresosOp, 2);

        $flujoOperacion = round($ingresoOperacionTotal - $egresoOperacionTotal, 2);

        $aumentoNeto = round($flujoOperacion + $flujoInversion + $flujoFinanciamiento, 2);

        // Saldo inicial de efectivo: saldo de apertura de 101+104.
        $stmtIni = $pdo->prepare("
            SELECT COALESCE(SUM(sa.debe),0) - COALESCE(SUM(sa.haber),0) AS saldo
            FROM saldos_apertura sa JOIN cuentas_contables c ON c.id = sa.cuenta_id
            WHERE sa.empresa_id = ? AND sa.periodo_id = ? AND c.codigo IN ('101','104')
        ");
        $stmtIni->execute([$empresaId, $periodoContableId]);
        $saldoInicial = round((float)$stmtIni->fetchColumn(), 2);

        $saldoFinalCalculado = round($saldoInicial + $aumentoNeto, 2);

        // Control obligatorio: el saldo final calculado aquí debe coincidir
        // con el saldo real de 101/104 en el Balance de Comprobación — si
        // no coincide, hay un movimiento de caja que no se registró como
        // debía (spec 2.15).
        $balance = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCorte);
        $saldoRealCaja = 0.0;
        foreach ($balance['filas'] as $f) {
            // Neto de las dos columnas, no solo "activo": si Caja queda
            // sobregirada (más egresos que saldo disponible) el Balance la
            // voltea a la columna "pasivo" — mismo criterio ya aplicado en
            // BalanceGeneralService para cuentas del elemento 4 en posición
            // deudora. Leer solo "activo" aquí subestimaba el saldo real
            // exactamente en esos casos (detectado al simular un período
            // con más pagos que caja disponible).
            if (in_array($f['codigo'], ['101', '104'], true)) $saldoRealCaja += $f['activo'] - $f['pasivo'];
        }
        $saldoRealCaja = round($saldoRealCaja, 2);

        return [
            'cobranza_clientes' => round($cobranzaClientes, 2),
            'pago_proveedores'  => round($pagoProveedores, 2),
            'pago_trabajadores' => round($pagoTrabajadores, 2),
            'otros_operacion'   => $otrosOperacion,
            'flujo_operacion'   => $flujoOperacion,
            'compra_activo_fijo' => round($compraActivoFijo, 2),
            'flujo_inversion'   => $flujoInversion,
            'prestamos_recibidos' => round($prestamosRecibidos, 2),
            'amortizacion'      => round($amortizacion, 2),
            'flujo_financiamiento' => $flujoFinanciamiento,
            'aumento_neto'      => $aumentoNeto,
            'saldo_inicial'     => $saldoInicial,
            'saldo_final_calculado' => $saldoFinalCalculado,
            'saldo_real_caja'   => $saldoRealCaja,
            'descuadre'         => round($saldoFinalCalculado - $saldoRealCaja, 2),
        ];
    }
}
