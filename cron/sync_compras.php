<?php
/**
 * GestiCont — CRON: Sync Compras desde SIRE
 * Ejecutar: 30 6 * * * php /var/www/gesticont/cron/sync_compras.php >> storage/logs/sync_compras.log 2>&1
 */
define('ROOT', dirname(__DIR__));
if (file_exists(ROOT . '/.env')) { $env = parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW); foreach ($env as $k => $v) putenv("$k=$v"); }
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/EncryptService.php';
require_once ROOT . '/services/SunatApiService.php';

$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] $msg\n");
$log("=== INICIO SYNC COMPRAS ===");
$db = Model::db();
$encrypt = new EncryptService();
$sunat = new SunatApiService();
$empresas = $db->query("SELECT e.id, e.ruc, e.razon_social, ec.sol_usuario, ec.sol_clave, ec.ambiente FROM empresas e INNER JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo' WHERE e.activo = 1")->fetchAll(PDO::FETCH_ASSOC);
$periodo = date('Ym', strtotime('first day of last month'));
foreach ($empresas as $empresa) {
    try {
        $log("Procesando: {$empresa['razon_social']}");
        $usuario = $encrypt->decrypt($empresa['sol_usuario']);
        $clave   = $encrypt->decrypt($empresa['sol_clave']);
        $compras = $sunat->getSireCompras($empresa['ruc'], $usuario, $clave, $periodo, $empresa['ambiente']);
        $ins = 0;
        foreach ($compras as $c) {
            $existe = $db->query("SELECT id FROM registro_compras WHERE empresa_id=? AND proveedor_ruc=? AND tipo_comp=? AND serie=? AND correlativo=?", [$empresa['id'],$c['rucProveedor'],$c['tipoComp'],$c['serie'],$c['correlativo']])[0] ?? null;
            if (!$existe) {
                $db->query("INSERT INTO registro_compras (empresa_id,proveedor_ruc,proveedor_nombre,tipo_comp,serie,correlativo,fecha_emision,base_imponible,igv,total,estado_sunat,fuente,sync_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'sire_api',NOW())", [$empresa['id'],$c['rucProveedor'],$c['nombreProveedor']??'',$c['tipoComp'],$c['serie'],$c['correlativo'],$c['fechaEmision'],$c['baseImponible'],$c['igv'],$c['total'],$c['estadoSunat']]);
                $ins++;
            }
        }
        $log("  Insertados: $ins de " . count($compras));
    } catch (Exception $e) {
        $log("  ERROR: " . $e->getMessage());
    }
}
$log("=== FIN SYNC COMPRAS ===");
