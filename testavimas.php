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
echo "=== TEST SIRE — AVIMAS JM E.I.R.L. ===\n\n";

$db = Model::db();
$stmt = $db->prepare("
    SELECT e.id, e.ruc, e.razon_social,
           ec.sol_usuario, ec.sol_clave,
           ec.api_client_id, ec.api_client_secret, ec.ambiente
    FROM empresas e
    INNER JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
    WHERE e.ruc = '20609044765'
    LIMIT 1
");
$stmt->execute();
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) die("AVIMAS no encontrada\n</pre>");
echo "Empresa: {$empresa['razon_social']} | RUC: {$empresa['ruc']}\n\n";

$encrypt   = new EncryptService();
$usuario   = $encrypt->decrypt($empresa['sol_usuario']);
$clave     = $encrypt->decrypt($empresa['sol_clave']);
$clientId  = $encrypt->decrypt($empresa['api_client_id']);
$clientSec = $encrypt->decrypt($empresa['api_client_secret']);

$sunat = new SunatApiService();

try {
    echo "Obteniendo token...\n";
    $token = $sunat->getToken($clientId, $clientSec, $empresa['ruc'], $usuario, $clave);
    echo "✓ Token OK\n\n";

    // Períodos a consultar
    $periodos = [
        '202507', '202508', '202509', '202510',
        '202511', '202512', '202601', '202602',
    ];

    $resumenTotal = [];

    foreach ($periodos as $periodo) {
        echo "--- Ventas {$periodo} ---\n";
        try {
            $result   = $sunat->getVentasPeriodo($token, $periodo);
            $total    = $result['total'];
            $registros= $result['registros'];

            if ($total === 0) {
                echo "  Sin comprobantes en este período\n";
            } else {
                $baseTotal  = array_sum(array_column($registros, 'base_imponible'));
                $igvTotal   = array_sum(array_column($registros, 'igv'));
                $montoTotal = array_sum(array_column($registros, 'total'));

                echo "  ✓ {$total} comprobantes | Base: S/" . number_format($baseTotal, 2)
                   . " | IGV: S/" . number_format($igvTotal, 2)
                   . " | Total: S/" . number_format($montoTotal, 2) . "\n";

                // Detalle de cada comprobante
                foreach ($registros as $v) {
                    $tipo = ['01'=>'FAC','03'=>'BOL','07'=>'NC ','08'=>'ND '][$v['tipo_comp']] ?? $v['tipo_comp'];
                    echo "    {$tipo} {$v['serie']}-{$v['correlativo']} | {$v['fecha_emision']}"
                       . " | " . substr($v['cliente_nombre'], 0, 30)
                       . " | S/" . number_format($v['total'], 2) . "\n";
                }

                $resumenTotal[$periodo] = [
                    'cant'  => $total,
                    'base'  => $baseTotal,
                    'igv'   => $igvTotal,
                    'total' => $montoTotal,
                ];
            }
        } catch (Exception $e) {
            echo "  ⚠ " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    // Resumen general
    if (!empty($resumenTotal)) {
        echo "=== RESUMEN GENERAL ===\n";
        echo str_pad('Período', 10) . str_pad('Comprobantes', 14)
           . str_pad('Base', 16) . str_pad('IGV', 14) . "Total\n";
        echo str_repeat('-', 70) . "\n";

        $totBase = $totIgv = $totMonto = $totCant = 0;
        foreach ($resumenTotal as $per => $r) {
            echo str_pad($per, 10)
               . str_pad($r['cant'], 14)
               . str_pad('S/' . number_format($r['base'], 2), 16)
               . str_pad('S/' . number_format($r['igv'], 2), 14)
               . 'S/' . number_format($r['total'], 2) . "\n";
            $totBase  += $r['base'];
            $totIgv   += $r['igv'];
            $totMonto += $r['total'];
            $totCant  += $r['cant'];
        }
        echo str_repeat('-', 70) . "\n";
        echo str_pad('TOTAL', 10)
           . str_pad($totCant, 14)
           . str_pad('S/' . number_format($totBase, 2), 16)
           . str_pad('S/' . number_format($totIgv, 2), 14)
           . 'S/' . number_format($totMonto, 2) . "\n";
    }

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== FIN ===\n</pre>";
?>