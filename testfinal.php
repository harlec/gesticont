<?php
/**
 * GestiCont — Test final integración SIRE
 * Borrar después de verificar
 */
define('ROOT', __DIR__);
if (file_exists('.env')) { $env = parse_ini_file('.env', false, INI_SCANNER_RAW); foreach ($env as $k => $v) putenv("$k=$v"); }
require_once 'services/SunatApiService.php';

$ruc           = '20532078718';
$client_id     = '787a62f8-ece3-45b0-8a67-9a7130a56814';
$client_secret = '6jUPQCp+a++wvb2yyjoxJQ==';
$usuario_sol   = 'GESTICON';
$clave_sol     = 'Ikm169uhn';
$periodo       = '202511';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px;background:#0f172a;color:#e2e8f0;">';
echo "=== GESTICONT — TEST INTEGRACIÓN SIRE ===\n\n";

$sunat = new SunatApiService();

try {
    echo "1. Obteniendo token...\n";
    $token = $sunat->getToken($client_id, $client_secret, $ruc, $usuario_sol, $clave_sol);
    echo "   ✓ Token OK\n\n";

    echo "2. Leyendo ventas {$periodo}...\n";
    $ventas = $sunat->getAllVentasPeriodo($token, $periodo);
    echo "   ✓ Total: " . count($ventas) . " comprobantes\n";
    foreach ($ventas as $v) {
        echo "   {$v['tipo_comp']}-{$v['serie']}-{$v['correlativo']} | {$v['fecha_emision']}"
           . " | {$v['cliente_nombre']} | Base: S/{$v['base_imponible']} | IGV: S/{$v['igv']} | Total: S/{$v['total']}\n";
    }

    echo "\n3. Leyendo compras {$periodo}...\n";
    try {
        $compras = $sunat->getAllComprasPeriodo($token, $periodo);
        echo "   ✓ Total: " . count($compras) . " comprobantes\n";
        foreach (array_slice($compras, 0, 5) as $c) {
            echo "   {$c['proveedor_ruc']} | {$c['proveedor_nombre']} | Total: S/{$c['total']}\n";
        }
    } catch (Exception $e) {
        echo "   ⚠ Compras: " . $e->getMessage() . "\n";
    }

    echo "\n✅ INTEGRACIÓN SIRE FUNCIONANDO\n";
    echo "   Listo para activar los crons en Plesk\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
?>
