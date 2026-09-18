<?php
define('ROOT', __DIR__);
if (file_exists('.env')) {
    $env = parse_ini_file('.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}
require_once 'services/SunatApiService.php';
require_once 'services/EncryptService.php';
require_once 'core/Model.php';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#0f172a;color:#e2e8f0;">';
echo "=== RESUMEN GENERADO TODOS LOS PERÍODOS ===\n\n";

$db = Model::db();
$stmt = $db->prepare("
    SELECT e.ruc, ec.sol_usuario, ec.sol_clave, ec.api_client_id, ec.api_client_secret
    FROM empresas e
    INNER JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
    WHERE e.ruc = '20609044765' LIMIT 1
");
$stmt->execute();
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

$enc   = new EncryptService();
$token = (new SunatApiService())->getToken(
    $enc->decrypt($emp['api_client_id']),
    $enc->decrypt($emp['api_client_secret']),
    $emp['ruc'],
    $enc->decrypt($emp['sol_usuario']),
    $enc->decrypt($emp['sol_clave'])
);
echo "Token: ✓ OK\n\n";

$base = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv";
$hdrs = ["Authorization: Bearer {$token}", "Accept: application/json"];

function sireGet(string $url, array $hdrs): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$hdrs,
        CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code'=>$code, 'body'=>$body, 'data'=>json_decode($body,true)];
}

$periodos = ['202507','202508','202509','202510','202511','202512','202601','202602'];

echo str_pad('Período', 10)
   . str_pad('Estado', 15)
   . str_pad('Compras docs', 14)
   . str_pad('Base compras', 18)
   . str_pad('IGV compras', 16)
   . str_pad('Ventas docs', 13)
   . "Base ventas\n";
echo str_repeat('-', 90) . "\n";

foreach ($periodos as $p) {
    // Resumen compras generado
    $rC = sireGet("{$base}/libros/rvierce/resumen/web/resumen/{$p}/resumencomprobantes/rce/?codTipoResumen=5", $hdrs);
    // Resumen ventas generado
    $rV = sireGet("{$base}/libros/rvierce/resumen/web/resumen/{$p}/resumencomprobantes/rvie/?codTipoResumen=5", $hdrs);

    if ($rC['code'] === 200) {
        $regs    = $rC['data']['registros'] ?? [];
        $cntC    = array_sum(array_column($regs, 'cntDocumentos'));
        $baseC   = array_sum(array_column($regs, 'mtoBIGravadoDG'));
        $igvC    = array_sum(array_column($regs, 'mtoIgvIpmDG'));
        $estadoC = '✅ Generado';
    } else {
        $cntC = $baseC = $igvC = 0;
        $estadoC = $rC['code'] === 422 ? '— No pres.' : "ERR {$rC['code']}";
    }

    if ($rV['code'] === 200) {
        $regsV = $rV['data']['registros'] ?? [];
        $cntV  = array_sum(array_column($regsV, 'cntDocumentos'));
        $baseV = array_sum(array_column($regsV, 'mtoTotalBIGravada'));
    } else {
        $cntV = $baseV = 0;
    }

    echo str_pad($p, 10)
       . str_pad($estadoC, 15)
       . str_pad($cntC, 14)
       . str_pad('S/' . number_format($baseC, 2), 18)
       . str_pad('S/' . number_format($igvC, 2), 16)
       . str_pad($cntV, 13)
       . 'S/' . number_format($baseV, 2) . "\n";
}

echo "\n=== FIN ===\n</pre>";
?>