<?php
ini_set('display_errors', 1);

$ruc           = '20532078718';
$client_id     = '787a62f8-ece3-45b0-8a67-9a7130a56814';
$client_secret = '6jUPQCp+a++wvb2yyjoxJQ==';
$usuario_sol   = 'GESTICON';
$clave_sol     = 'Ikm169uhn';
$periodo       = '202511';
$base          = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv";

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#0f172a;color:#e2e8f0;">';

// Token
$ch = curl_init("https://api-seguridad.sunat.gob.pe/v1/clientessol/{$client_id}/oauth2/token/");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'password', 'scope' => 'https://api.sunat.gob.pe/v1/contribuyente/migeigv',
        'client_id'     => $client_id, 'client_secret' => $client_secret,
        'username'      => $ruc . $usuario_sol, 'password' => $clave_sol,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 20, CURLOPT_SSL_VERIFYPEER => false,
]);
$body = curl_exec($ch); curl_close($ch);
$token = json_decode($body, true)['access_token'] ?? null;
echo "Token: " . ($token ? "✓ OK" : "✗") . "\n\n";
if (!$token) die("</pre>");

$hdrs = ["Authorization: Bearer {$token}", "Accept: application/json"];

// Ver JSON completo de ventas
echo "=== JSON COMPLETO VENTAS ===\n";
$ch = curl_init("{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes?page=1&perPage=5");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$data = json_decode($body, true);
echo "HTTP: {$code}\n";
// Mostrar el primer registro completo con todos sus campos
if (!empty($data['registros'][0])) {
    echo "\nCampos del primer comprobante:\n";
    foreach ($data['registros'][0] as $key => $val) {
        echo "  {$key}: " . (is_array($val) ? json_encode($val) : $val) . "\n";
    }
}

// Probar compras con timeout mayor
echo "\n=== JSON COMPLETO COMPRAS ===\n";
$ch = curl_init("{$base}/libros/rce/propuesta/web/propuesta/{$periodo}/comprobantes?page=1&perPage=5");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $hdrs,
    CURLOPT_TIMEOUT        => 30,   // más tiempo
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);
echo "HTTP: {$code}" . ($err ? " | Error: {$err}" : "") . "\n";
if ($code === 200) {
    $dataC = json_decode($body, true);
    $total = $dataC['paginacion']['totalRegistros'] ?? 0;
    echo "Total compras: {$total}\n";
    if (!empty($dataC['registros'][0])) {
        echo "\nCampos del primer comprobante de compra:\n";
        foreach ($dataC['registros'][0] as $key => $val) {
            echo "  {$key}: " . (is_array($val) ? json_encode($val) : $val) . "\n";
        }
    }
} else {
    echo substr($body, 0, 200) . "\n";
}

echo "\n=== FIN ===\n</pre>";
?>