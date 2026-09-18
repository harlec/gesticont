<?php
if (file_exists('.env')) {
    $env = parse_ini_file('.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}

// ── CAMBIA ESTOS 5 VALORES ───────────────────────
$ruc           = '20532078718';
$usuario       = 'GESTICON';
$clave_sol     = 'Ikm169uhn';                              // <- tu clave
$client_id     = '787a62f8-ece3-45b0-8a67-9a7130a56814';   // <- ya confirmado
$client_secret = '6jUPQCp+a++wvb2yyjoxJQ==';               // <- ya confirmado
// ─────────────────────────────────────────────────

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#1e293b;color:#e2e8f0;">';
echo "=== TEST SUNAT API COMPLETO ===\n\n";

// ── PASO 1: Token ────────────────────────────────
echo "--- PASO 1: Token OAuth2 ---\n";
$endpoint = "https://api-seguridad.sunat.gob.pe/v1/clientessol/{$client_id}/oauth2/token/";
$postData = http_build_query([
    'grant_type'    => 'password',
    'scope'         => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'username'      => $ruc . $usuario,
    'password'      => $clave_sol,
]);
$ch = curl_init($endpoint);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$postData, CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'], CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$json = json_decode($body, true);
if (!isset($json['access_token'])) {
    echo "✗ Error token: " . ($json['error_description'] ?? $body) . "\n";
    exit;
}
$token = $json['access_token'];
echo "✓ Token OK (expira en " . $json['expires_in'] . "s)\n\n";

// ── PASO 2: SIRE Ventas ──────────────────────────
$periodo = '202112';
echo "--- PASO 2: SIRE Ventas periodo $periodo ---\n";
$url = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/contribuyentes/{$ruc}/rvie/cabecera?periodo={$periodo}&estadoCp=&rucAdquiriente=&numeroSerie=&tipoDocumento=&numeroDocumento=&fechaEmisionDesde=&fechaEmisionHasta=&pageNumber=1&pageSize=10";
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer {$token}",'Accept: application/json'], CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$json = json_decode($body, true);
echo "HTTP: $code\n";
if ($code === 200) {
    echo "✓ SIRE Ventas OK\n";
    $total = $json['totalRegistros'] ?? $json['total'] ?? count($json['data'] ?? []);
    echo "Total registros: $total\n";
} else {
    echo "Respuesta: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
echo "\n";

// ── PASO 3: SIRE Compras ─────────────────────────
echo "--- PASO 3: SIRE Compras periodo $periodo ---\n";
$url = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/contribuyentes/{$ruc}/rce/cabecera?periodo={$periodo}&estadoCp=&rucProveedor=&numeroSerie=&tipoDocumento=&numeroDocumento=&fechaEmisionDesde=&fechaEmisionHasta=&pageNumber=1&pageSize=10";
$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>["Authorization: Bearer {$token}",'Accept: application/json'], CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>false]);
$body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$json = json_decode($body, true);
echo "HTTP: $code\n";
if ($code === 200) {
    echo "✓ SIRE Compras OK\n";
    $total = $json['totalRegistros'] ?? $json['total'] ?? count($json['data'] ?? []);
    echo "Total registros: $total\n";
} else {
    echo "Respuesta: " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
echo "\n";

echo "=== RESUMEN FINAL ===\n";
echo "✓ Token OAuth2: OK\n";
echo "Si SIRE ventas y compras devolvieron HTTP 200 el sistema\n";
echo "está 100% listo para sincronizar con SUNAT automaticamente.\n";
echo "\nPROXIMO PASO: Guardar client_id y client_secret en la BD\n";
echo "y activar los crons en Plesk.\n";
echo '</pre>';