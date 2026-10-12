<?php
require_once ROOT . '/services/CobroPagoService.php';
require_once ROOT . '/services/BalanceService.php';

/**
 * Cuentas por pagar (compras) y por cobrar (ventas): saldo pendiente por proveedor o cliente al
 * cierre de un período, con su antigüedad. Es un reporte de solo lectura: el saldo ya vive en 421 /
 * 121 (lo que queda después de los pagos/cobros), aquí solo se detalla comprobante por comprobante.
 *
 * Saldo de un comprobante = total − lo pagado/cobrado hasta la fecha de corte. Una nota de crédito
 * (total negativo) no se cobra ni se paga: queda como saldo negativo a favor y resta del tercero.
 * El detalle se cuadra contra el saldo de 421/121 del Balance: la diferencia es lo que no tiene
 * comprobante detrás (saldo de apertura u otros asientos).
 */
class CuentasPendientesService
{
    public const TRAMOS = ['0-30' => [0, 30], '31-60' => [31, 60], '61-90' => [61, 90], 'Más de 90' => [91, PHP_INT_MAX]];

    /**
     * @return array{terceros:array,total:float,tramos:array<string,float>,libro:?float,sin_detalle:?float,cuenta:string,corte:string}
     */
    public static function generar(int $empresaId, string $origen, string $periodo): array
    {
        $c     = CobroPagoService::config($origen);
        $pdo   = Model::db();
        $corte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        $esVenta = $origen === 'venta';

        $tercero = $esVenta ? 'd.cliente_num_doc' : 'd.proveedor_ruc';
        $nombre  = $esVenta ? 'd.cliente_nombre'  : 'd.proveedor_nombre';

        $stmt = $pdo->prepare("
            SELECT d.id, d.tipo_comp, d.serie, d.correlativo, d.fecha_emision, d.total,
                   {$tercero} AS doc, {$nombre} AS nombre,
                   COALESCE((SELECT SUM(m.monto) FROM caja_movimientos m
                             WHERE m.empresa_id = d.empresa_id AND m.{$c['col']} = d.id AND m.fecha <= ?), 0) AS pagado
            FROM {$c['tabla']} d
            WHERE d.empresa_id = ? AND d.estado_imputacion = 'imputado' AND d.estado_sunat = '1'
              AND d.fecha_emision <= ?
            ORDER BY nombre, d.fecha_emision, d.id
        ");
        $stmt->execute([$corte, $empresaId, $corte]);

        $terceros = []; $total = 0.0;
        $tramos = array_fill_keys(array_keys(self::TRAMOS), 0.0);
        $finCorte = strtotime($corte);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $totDoc = round((float)$d['total'], 2);
            // Nota de crédito: sin pagos; el saldo es el propio total (negativo). Resto: total − pagado.
            $saldo = $totDoc < 0 ? $totDoc : round($totDoc - (float)$d['pagado'], 2);
            if (abs($saldo) < CobroPagoService::TOLERANCIA) continue;      // ya cancelado

            $dias = max(0, (int)floor(($finCorte - strtotime($d['fecha_emision'])) / 86400));
            $clave = ($d['doc'] ?? '') . '|' . ($d['nombre'] ?? '');
            $terceros[$clave] ??= ['doc' => (string)($d['doc'] ?? ''), 'nombre' => (string)($d['nombre'] ?? '—'), 'saldo' => 0.0, 'docs' => []];
            $terceros[$clave]['saldo'] = round($terceros[$clave]['saldo'] + $saldo, 2);
            $terceros[$clave]['docs'][] = [
                'tipo' => $d['tipo_comp'], 'numero' => $d['serie'] . '-' . $d['correlativo'], 'fecha' => $d['fecha_emision'],
                'total' => $totDoc, 'pagado' => $totDoc < 0 ? 0.0 : round((float)$d['pagado'], 2), 'saldo' => $saldo, 'dias' => $dias,
            ];
            $total = round($total + $saldo, 2);
            foreach (self::TRAMOS as $nom => [$ini, $fin]) if ($dias >= $ini && $dias <= $fin) { $tramos[$nom] = round($tramos[$nom] + $saldo, 2); break; }
        }
        // Mayor saldo primero
        uasort($terceros, fn($a, $b) => $b['saldo'] <=> $a['saldo']);

        // Saldo según el Mayor (421 acreedor / 121 deudor) para cuadrar el detalle
        $libro = null;
        $cuenta = $c['cuentaContra'];
        $anio = (int)substr($periodo, 0, 4);
        $per = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $per->execute([$empresaId, $anio]);
        if ($pid = $per->fetchColumn()) {
            foreach (BalanceService::comprobacion($empresaId, (int)$pid, $corte)['filas'] as $f) {
                if ($f['codigo'] === $cuenta) { $libro = round($esVenta ? $f['debe'] - $f['haber'] : $f['haber'] - $f['debe'], 2); break; }
            }
            $libro ??= 0.0;
        }

        return [
            'terceros' => array_values($terceros), 'total' => $total, 'tramos' => $tramos,
            'libro' => $libro, 'sin_detalle' => $libro === null ? null : round($libro - $total, 2),
            'cuenta' => $cuenta, 'corte' => $corte,
        ];
    }
}
