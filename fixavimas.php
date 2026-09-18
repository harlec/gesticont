<?php
define('ROOT', __DIR__);
if (file_exists('.env')) {
    $env = parse_ini_file('.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}
require_once 'services/EncryptService.php';
require_once 'core/Model.php';

// ── DATOS REALES DE AVIMAS ───────────────────────
$sol_usuario   = 'GESTICON';
$sol_clave     = 'Ikm169uhn';
$client_id     = 'dbbae6e3-340e-40ca-803e-8cbc93ab9560';
$client_secret = 'HRSR+4WLzUBfRTwjm7kvMw==';
// ────────────────────────────────────────────────

echo '<pre>';
$encrypt = new EncryptService();
$pdo     = Model::db();

// Verificar encriptación antes de tocar la BD
$encUsuario = $encrypt->encrypt($sol_usuario);
$encClave   = $encrypt->encrypt($sol_clave);
$encCid     = $encrypt->encrypt($client_id);
$encCsec    = $encrypt->encrypt($client_secret);

$ok = $encrypt->decrypt($encUsuario) === $sol_usuario
   && $encrypt->decrypt($encClave)   === $sol_clave
   && $encrypt->decrypt($encCid)     === $client_id
   && $encrypt->decrypt($encCsec)    === $client_secret;

if (!$ok) die("✗ Error verificando encriptación\n</pre>");
echo "✓ Encriptación verificada\n";
echo "  sol_usuario longitud encriptado: " . strlen($encUsuario) . " chars\n\n";

// Obtener empresa_id de AVIMAS
$s = $pdo->prepare("SELECT id FROM empresas WHERE ruc = '20609044765'");
$s->execute();
$empresaId = $s->fetchColumn();
echo "empresa_id: {$empresaId}\n";

// Eliminar TODOS los certificados anteriores de AVIMAS
$pdo->prepare("DELETE FROM empresa_certificados WHERE empresa_id = ?")->execute([$empresaId]);
echo "Certificados anteriores eliminados\n";

// Insertar uno limpio
$ins = $pdo->prepare("
    INSERT INTO empresa_certificados
        (empresa_id, cert_path, cert_password, sol_usuario, sol_clave,
         api_client_id, api_client_secret, ambiente, estado, configurado_por)
    VALUES (?, '', ?, ?, ?, ?, ?, 'produccion', 'activo', 1)
");
$ins->execute([
    $empresaId,
    $encrypt->encrypt('sin_cert'),
    $encUsuario,
    $encClave,
    $encCid,
    $encCsec,
]);
echo "Certificado insertado\n";

// Verificar leyendo de BD
$v = $pdo->prepare("
    SELECT id, LENGTH(sol_usuario) as lon_sol, sol_usuario,
           sol_clave, api_client_id, api_client_secret
    FROM empresa_certificados
    WHERE empresa_id = ? AND estado = 'activo'
    ORDER BY id DESC LIMIT 1
");
$v->execute([$empresaId]);
$cert = $v->fetch(PDO::FETCH_ASSOC);

echo "\nVerificación (ID:{$cert['id']}):\n";
echo "  sol_usuario longitud en BD: {$cert['lon_sol']} chars\n";

$resultados = [
    'sol_usuario'    => [$cert['sol_usuario'],    $sol_usuario],
    'sol_clave'      => [$cert['sol_clave'],       $sol_clave],
    'api_client_id'  => [$cert['api_client_id'],  $client_id],
    'api_client_secret' => [$cert['api_client_secret'], $client_secret],
];

$todoOk = true;
foreach ($resultados as $campo => [$encBD, $original]) {
    try {
        $dec = $encrypt->decrypt($encBD);
        $ok  = $dec === $original;
        echo "  {$campo}: " . ($ok ? "✓" : "✗ [{$dec}] != [{$original}]") . "\n";
        if (!$ok) $todoOk = false;
    } catch (Exception $e) {
        echo "  {$campo}: ✗ ERROR\n";
        $todoOk = false;
    }
}

echo "\n" . ($todoOk ? "✅ AVIMAS configurado correctamente\n" : "✗ Hay errores — revisar\n");
echo "Borra este archivo cuando termines\n</pre>";
?>