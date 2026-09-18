<?php
define('ROOT', __DIR__);
if (file_exists('.env')) {
    $env = parse_ini_file('.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}

require_once 'services/SunatApiService.php';
require_once 'services/SireComprasService.php';
require_once 'services/EncryptService.php';
require_once 'core/Model.php';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#0f172a;color:#e2e8f0;">';
echo "=== PRUEBA — SIRE COMPRAS (Propuesta RCE) ===\n\n";

$db = Model::db();
$stmt = $db->prepare("
    SELECT e.id, e.ruc, e.razon_social,
           ec.sol_usuario, ec.sol_clave,
           ec.api_client_id, ec.api_client_secret
    FROM empresas e
    INNER JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
    WHERE e.ruc = '20609044765'
    LIMIT 1
");
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) die("Empresa no encontrada\n</pre>");
echo "Empresa: {$empresa['razon_social']} | RUC: {$empresa['ruc']}\n\n";

$encrypt   = new EncryptService();
$usuario   = $encrypt->decrypt($empresa['sol_usuario']);
$clave     = $encrypt->decrypt($empresa['sol_clave']);
$clientId  = $encrypt->decrypt($empresa['api_client_id']);
$clientSec = $encrypt->decrypt($empresa['api_client_secret']);

try {
    echo "Obteniendo token...\n";
    $auth  = new SunatApiService();
    $token = $auth->getToken($clientId, $clientSec, $empresa['ruc'], $usuario, $clave);
    echo "✓ Token OK\n\n";

    $comprasSvc = new SireComprasService();

    $periodo   = '202511';         // NOV 2025
    $ruc       = '20609044765';    // AVIMAS
    $perPage   = 100;              // como en tu captura
    $codTipoOp = 3;                // como en tu captura

    $result = $comprasSvc->obtenerComprasPeriodo($token, $periodo, $ruc, $perPage, $codTipoOp);

    echo "Filas: {$result['total']} | "
       . "Base: S/" . number_format($result['sumas']['base'], 2)
       . " | IGV: S/" . number_format($result['sumas']['igv'], 2)
       . " | Total: S/" . number_format($result['sumas']['total'], 2) . "\n\n";

    // Mostrar primeras 10
    $mostrar = array_slice($result['registros'], 0, 10);
    foreach ($mostrar as $v) {
        $tipo = ['01'=>'FAC','03'=>'BOL','07'=>'NC ','08'=>'ND '][$v['tipo_comp']] ?? $v['tipo_comp'];
        echo "  {$tipo} {$v['serie']}-{$v['correlativo']} | {$v['fecha_emision']} | "
           . substr($v['proveedor_nombre'], 0, 40)
           . " | S/" . number_format($v['total'], 2) . "\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n</pre>";