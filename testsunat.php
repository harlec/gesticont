<?php
$ruc           = '20532078718';
$usuario       = 'GESTICON';
$clave_sol     = 'Ikm169uhn';
$client_id     = '787a62f8-ece3-45b0-8a67-9a7130a56814';
$client_secret = '6jUPQCp+a++wvb2yyjoxJQ==';

$endpoint = "https://api-seguridad.sunat.gob.pe/v1/clientessol/{$client_id}/oauth2/token/";

$postData = http_build_query([
    'grant_type'    => 'password',
    'scope'         => 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes',
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'username'      => $ruc . $usuario,
    'password'      => $clave_sol,
]);

echo '<pre style="padding:20px;font-family:monospace;">';
echo "Endpoint: $endpoint\n";
echo "Username: {$ruc}{$usuario}\n";
echo "Client ID: $client_id\n";
echo "Datos POST:\n$postData\n\n";

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP: $code\n";
echo json_encode(json_decode($body), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo '</pre>';
?>