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
echo "=== TEST VENTAS DECLARADAS RVIE 202601 ===\n\n";

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

$base    = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv";
$hdrs    = ["Authorization: Bearer {$token}", "Accept: application/json"];

// Probar ventas declaradas con codTipoResumen=5
foreach (['202601', '202602'] as $periodo) {
    echo "--- Ventas declaradas {$periodo} ---\n";

    $url = "{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes"
         . "?page=1&perPage=20&codTipoResumen=5";

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$hdrs,
        CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data  = json_decode($body, true);
    $total = $data['paginacion']['totalRegistros'] ?? 0;
    $regs  = $data['registros'] ?? [];

    echo "HTTP: {$code} | Total: {$total}\n";

    if ($code === 200 && $total > 0) {
        foreach (array_slice($regs, 0, 5) as $r) {
            $tipo  = ['01'=>'FAC','03'=>'BOL','07'=>'NC'][$r['codTipoCDP']] ?? $r['codTipoCDP'];
            $serie = $r['numSerieCDP'] . '-' . $r['numCDP'];
            $fecha = $r['fecEmision'] ?? '';
            $cli   = substr($r['nomRazonSocialCliente'] ?? '-', 0, 30);
            $base2 = number_format((float)($r['mtoBIGravada'] ?? 0), 2);
            $igv   = number_format((float)($r['mtoIGV']       ?? 0), 2);
            echo "  {$tipo} {$serie} | {$fecha} | {$cli} | Base: S/{$base2} | IGV: S/{$igv}\n";
        }
        echo "  ✅ Ventas declaradas confirmadas\n";
    } elseif ($code === 422) {
        echo "  — Período no presentado en SIRE\n";
    } else {
        echo "  Body: " . substr($body, 0, 150) . "\n";
    }
    echo "\n";
}

echo "=== FIN ===\n</pre>";
?>