<?php
/**
 * GestiCont — CRON: Cierre mensual
 * Ejecutar: 0 8 2 * * php /var/www/gesticont/cron/cierre_mensual.php >> storage/logs/cierre.log 2>&1
 */
define('ROOT', dirname(__DIR__));
if (file_exists(ROOT . '/.env')) { $env = parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW); foreach ($env as $k => $v) putenv("$k=$v"); }
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';

$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] $msg\n");
$periodo = date('Ym', strtotime('first day of last month'));
$log("=== CIERRE MENSUAL $periodo ===");
$db = Model::db();
$empresas = $db->query("SELECT id, ruc, razon_social, regimen FROM empresas WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
foreach ($empresas as $empresa) {
    try {
        $ventas = $db->query("SELECT SUM(base_imponible) as base, SUM(igv) as igv, SUM(total) as total, COUNT(*) as cant FROM registro_ventas WHERE empresa_id=? AND DATE_FORMAT(fecha_emision,'%Y%m')=? AND estado_sunat NOT IN ('anulado','rechazado')", [$empresa['id'], $periodo])[0] ?? [];
        $compras = $db->query("SELECT SUM(base_imponible) as base, SUM(igv) as igv, SUM(total) as total FROM registro_compras WHERE empresa_id=? AND DATE_FORMAT(fecha_emision,'%Y%m')=? AND estado_sunat!='anulado'", [$empresa['id'], $periodo])[0] ?? [];
        $igvRes = (float)($ventas['igv'] ?? 0) - (float)($compras['igv'] ?? 0);
        $saldoAnt = $db->query("SELECT saldo_igv_favor FROM resumen_mensual WHERE empresa_id=? ORDER BY periodo DESC LIMIT 1", [$empresa['id']])[0]['saldo_igv_favor'] ?? 0;
        $igvPagar = max(0, $igvRes - $saldoAnt);
        $nuevoSaldo = max(0, $saldoAnt - $igvRes);
        $baseRenta = (float)($ventas['base'] ?? 0);
        $renta = round($baseRenta * ($empresa['regimen'] === 'mype' ? 0.015 : 0.02), 2);
        $existe = $db->query("SELECT id FROM resumen_mensual WHERE empresa_id=? AND periodo=?", [$empresa['id'], $periodo])[0] ?? null;
        if ($existe) {
            $db->query("UPDATE resumen_mensual SET base_ventas=?,igv_ventas=?,base_compras=?,igv_compras=?,igv_resultante=?,saldo_favor_anterior=?,igv_a_pagar=?,saldo_igv_favor=?,base_renta=?,renta_a_pagar=?,estado='generado' WHERE id=?", [(float)($ventas['base']??0),(float)($ventas['igv']??0),(float)($compras['base']??0),(float)($compras['igv']??0),$igvRes,$saldoAnt,$igvPagar,$nuevoSaldo,$baseRenta,$renta,$existe['id']]);
        } else {
            $db->query("INSERT INTO resumen_mensual (empresa_id,periodo,base_ventas,igv_ventas,base_compras,igv_compras,igv_resultante,saldo_favor_anterior,igv_a_pagar,saldo_igv_favor,base_renta,renta_a_pagar,estado) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'generado')", [$empresa['id'],$periodo,(float)($ventas['base']??0),(float)($ventas['igv']??0),(float)($compras['base']??0),(float)($compras['igv']??0),$igvRes,$saldoAnt,$igvPagar,$nuevoSaldo,$baseRenta,$renta]);
        }
        $log("  {$empresa['razon_social']}: ventas S/" . number_format($ventas['base']??0,2) . " | renta S/" . number_format($renta,2));
    } catch (Exception $e) {
        $log("  ERROR {$empresa['ruc']}: " . $e->getMessage());
    }
}
$log("=== FIN CIERRE MENSUAL ===");
