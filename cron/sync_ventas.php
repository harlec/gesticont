<?php
/**
 * GestiCont — CRON: Sincronización ventas via SIRE
 * 0 6 * * * php /ruta/cron/sync_ventas.php >> storage/logs/sync_ventas.log 2>&1
 */
define('ROOT', dirname(__DIR__));
if (file_exists(ROOT . '/.env')) {
    $env = parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/EncryptService.php';
require_once ROOT . '/services/SunatApiService.php';

$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] $msg\n");
$log("=== INICIO SYNC VENTAS ===");

$db      = Model::db();
$encrypt = new EncryptService();
$sunat   = new SunatApiService();

// Período anterior
$periodo = date('Ym', strtotime('first day of last month'));
$log("Período: {$periodo}");

// Empresas activas con credenciales
$stmtEmp = $db->prepare("
    SELECT e.id, e.ruc, e.razon_social,
           ec.sol_usuario, ec.sol_clave,
           ec.api_client_id, ec.api_client_secret
    FROM empresas e
    INNER JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
    WHERE e.activo = 1
    ORDER BY e.id
");
$stmtEmp->execute();
$empresas = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);
$log("Empresas: " . count($empresas));

foreach ($empresas as $empresa) {
    try {
        $log("Procesando: {$empresa['razon_social']} ({$empresa['ruc']})");

        // Desencriptar credenciales
        $usuario    = $encrypt->decrypt($empresa['sol_usuario']);
        $clave      = $encrypt->decrypt($empresa['sol_clave']);
        $clientId   = $encrypt->decrypt($empresa['api_client_id']   ?? '');
        $clientSec  = $encrypt->decrypt($empresa['api_client_secret'] ?? '');

        if (empty($clientId) || empty($clientSec)) {
            $log("  Sin credenciales API — saltando");
            continue;
        }

        // Obtener token
        $token = $sunat->getToken($clientId, $clientSec, $empresa['ruc'], $usuario, $clave);

        // Obtener ventas
        $ventas = $sunat->getAllVentasPeriodo($token, $periodo);
        $log("  Ventas obtenidas: " . count($ventas));

        $ins = 0; $dup = 0;
        foreach ($ventas as $v) {
            // Verificar si ya existe
            $stmtChk = $db->prepare("SELECT id FROM registro_ventas WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?");
            $stmtChk->execute([$empresa['id'], $v['tipo_comp'], $v['serie'], $v['correlativo']]);
            if ($stmtChk->fetch()) { $dup++; continue; }

            // Insertar
            $db->prepare("
                INSERT INTO registro_ventas
                    (empresa_id, tipo_comp, serie, correlativo, fecha_emision,
                     cliente_tipo_doc, cliente_num_doc, cliente_nombre,
                     moneda, base_imponible, igv, total, estado_sunat,
                     fuente, sync_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'sire_api',NOW())
            ")->execute([
                $empresa['id'], $v['tipo_comp'], $v['serie'], $v['correlativo'],
                $v['fecha_emision'], $v['cliente_tipo_doc'], $v['cliente_num_doc'],
                $v['cliente_nombre'], $v['moneda'], $v['base_imponible'],
                $v['igv'], $v['total'],
                $v['estado_sunat'] === '1' ? 'aceptado' : 'anulado',
            ]);
            $ins++;
        }
        $log("  Insertados: {$ins} | Duplicados: {$dup}");

    } catch (Exception $e) {
        $log("  ERROR {$empresa['ruc']}: " . $e->getMessage());
    }
}
$log("=== FIN SYNC VENTAS ===");
