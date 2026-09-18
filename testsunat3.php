<?php
ini_set('display_errors', 1);
set_time_limit(60);

$ruc           = '20532078718';
$client_id     = '787a62f8-ece3-45b0-8a67-9a7130a56814';
$client_secret = '6jUPQCp+a++wvb2yyjoxJQ==';
$usuario_sol   = 'GESTICON';
$clave_sol     = 'Ikm169uhn';
$periodo       = '202511';
$anio          = '2025';
$mes           = '11';
$base          = "https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv";

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#0f172a;color:#e2e8f0;">';

// Token
$ch = curl_init("https://api-seguridad.sunat.gob.pe/v1/clientessol/{$client_id}/oauth2/token/");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'grant_type'    => 'password',
        'scope'         => 'https://api.sunat.gob.pe/v1/contribuyente/migeigv',
        'client_id'     => $client_id, 'client_secret' => $client_secret,
        'username'      => $ruc . $usuario_sol, 'password' => $clave_sol,
    ]),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_TIMEOUT        => 20, CURLOPT_SSL_VERIFYPEER => false,
]);
$body  = curl_exec($ch); curl_close($ch);
$token = json_decode($body, true)['access_token'] ?? null;
echo "Token: " . ($token ? "✓ OK" : "✗") . "\n\n";
if (!$token) die("</pre>");

$hdrs = ["Authorization: Bearer {$token}", "Accept: application/json"];

function sireGet(string $url, array $hdrs): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$hdrs, CURLOPT_TIMEOUT=>20, CURLOPT_SSL_VERIFYPEER=>false]);
    $body = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return ['code'=>$code, 'body'=>$body, 'data'=>json_decode($body,true)];
}

// Endpoints que devuelven JSON con comprobantes directamente
$endpoints = [
    // Consulta propuesta paginada (JSON directo sin ticket)
    "propuesta paginada p=1"
        => "{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}?page=1&perPage=20",
    "propuesta paginada p=1 v2"
        => "{$base}/libros/rvie/propuesta/web/{$periodo}?page=1&perPage=20",
    "propuesta incluidos"
        => "{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}/incluidos?page=1&perPage=20",
    "propuesta comprobantes"
        => "{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}/comprobantes?page=1&perPage=20",
    // Resumen casillas (para PDT)
    "reporte casillas"
        => "{$base}/libros/rvie/propuesta/web/propuesta/{$periodo}/reportecasillas",
    "reporte consolidado"
        => "{$base}/libros/rvierce/gestionlibro/web/registroslibros/{$periodo}/reporteconsolidado?codLibro=140000",
    // Estado del libro
    "estado libro RVIE"
        => "{$base}/libros/rvierce/gestionlibro/web/registroslibros/{$periodo}/estadolibro?codLibro=140000",
    "estado libro RCE"
        => "{$base}/libros/rvierce/gestionlibro/web/registroslibros/{$periodo}/estadolibro?codLibro=150000",
];

foreach ($endpoints as $nombre => $url) {
    $r = sireGet($url, $hdrs);
    $icono = $r['code'] === 200 ? '✅' : $r['code'];
    echo "{$icono} {$nombre}: HTTP {$r['code']}";
    if ($r['code'] === 200) {
        $data = $r['data'];
        echo " | items: " . count($data['data'] ?? $data['registros'] ?? $data ?? []);
        echo "\n  Resp: " . substr($r['body'], 0, 300);
    } elseif ($r['code'] !== 500) {
        echo " | " . substr($r['body'], 0, 100);
    }
    echo "\n\n";
}

echo "=== FIN ===\n</pre>";
?>