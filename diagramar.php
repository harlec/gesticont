<?php
define('ROOT', __DIR__);
if (file_exists('.env')) {
  $env = parse_ini_file('.env', false, INI_SCANNER_RAW);
  foreach ($env as $k => $v) putenv("$k=$v");
}
require_once 'services/SunatApiService.php';
require_once 'services/EncryptService.php';
require_once 'core/Model.php';

function http_get_raw($url, $headers = []) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
    CURLOPT_HEADER         => true,   // incluimos cabeceras en la respuesta
  ]);
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  $err  = curl_error($ch);
  curl_close($ch);

  $rawHeaders = substr($res, 0, $hdrSize);
  $body       = substr($res, $hdrSize);
  return [$code, $rawHeaders, $body, $err];
}

// 1) Traer credenciales y token
$db = Model::db();
$stmt = $db->prepare("
  SELECT e.ruc, ec.sol_usuario, ec.sol_clave, ec.api_client_id, ec.api_client_secret
  FROM empresas e
  JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado='activo'
  WHERE e.ruc='20609044765' LIMIT 1
");
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$empresa) { die("Empresa no encontrada\n"); }

$enc = new EncryptService();
$usuario   = $enc->decrypt($empresa['sol_usuario']);
$clave     = $enc->decrypt($empresa['sol_clave']);
$clientId  = $enc->decrypt($empresa['api_client_id']);
$clientSec = $enc->decrypt($empresa['api_client_secret']);

$auth  = new SunatApiService();
$token = $auth->getToken($clientId, $clientSec, $empresa['ruc'], $usuario, $clave);

$periodo = '202511'; // NOV 2025
$ruc     = '20609044765';

// 2) Construir URL con parámetros completos
$base = 'https://api-sire.sunat.gob.pe';
$path = '/v1/contribuyente/migeigv/libros/rce/propuesta/web';
$q = http_build_query([
  'periodoSeleccionado' => $periodo,
  'codLibro'            => '080100',
  'numRuc'              => $ruc
]);
$url = $base . $path . '?' . $q;

$headers = [
  'Authorization: Bearer ' . $token,
  'Num-Ruc: ' . $ruc,
  'Accept: application/json'
];

echo "== GET $url ==\n";
list($code, $rawH, $body, $err) = http_get_raw($url, $headers);
echo "HTTP $code\n";
if ($err) echo "cURL error: $err\n";
echo "-- Headers --\n$rawH\n";
echo "-- Body (primeros 1200 chars) --\n" . substr($body, 0, 1200) . "\n";