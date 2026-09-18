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
echo "=== TEST COMPROBANTES DECLARADOS RCE 202601 ===\n\n";

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
$periodo = '202601';

// Endpoint confirmado — propuesta con codTipoResumen=5 = declarado
$url = "{$base}/libros/rce/propuesta/web/propuesta/{$periodo}/busqueda"
    . "?codTipoOpe=3&page=1&perPage=20&codTipoResumen=5";

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$hdrs,
    CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data  = json_decode($body, true);
$total = $data['paginacion']['totalRegistros'] ?? 0;
$regs  = $data['registros'] ?? [];

echo "HTTP: {$code} | Total declarados: {$total}\n\n";

if ($code === 200 && $total > 0) {
    echo "Primeros 10 comprobantes declarados:\n";
    echo str_pad('Tipo', 6) . str_pad('Serie-Número', 20) . str_pad('Fecha', 14)
       . str_pad('Proveedor', 35) . str_pad('Base', 16) . "IGV\n";
    echo str_repeat('-', 100) . "\n";

    foreach (array_slice($regs, 0, 10) as $r) {
        $tipo  = ['01'=>'FAC','07'=>'NC ','08'=>'ND '][$r['codTipoCDP']] ?? $r['codTipoCDP'];
        $serie = $r['numSerieCDP'] . '-' . $r['numCDP'];
        $fecha = $r['fecEmision'] ?? '';
        $prov  = substr($r['nomRazonSocialProveedor'] ?? '-', 0, 32);
        $m     = $r['montos'] ?? [];
        $base2 = number_format((float)($m['mtoBIGravadaDG'] ?? 0), 2);
        $igv   = number_format((float)($m['mtoIgvIpmDG']   ?? 0), 2);

        echo str_pad($tipo, 6) . str_pad($serie, 20) . str_pad($fecha, 14)
           . str_pad($prov, 35) . str_pad('S/'.$base2, 16) . "S/{$igv}\n";
    }

    echo "\n✅ Confirmado — se pueden traer los comprobantes declarados\n";
    echo "Total páginas con perPage=20: " . ceil($total/20) . " páginas\n";
} else {
    echo "✗ No se pudieron obtener comprobantes\n";
    echo substr($body, 0, 200) . "\n";
}

echo "\n=== FIN ===\n</pre>";
?>