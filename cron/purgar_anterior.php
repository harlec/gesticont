<?php
/**
 * GestiCont — Borra la data contable anterior al período mínimo (por defecto
 * Periodo::minimo(), 202601 = se conserva 2026 en adelante y se borra 2025 y antes).
 * Lo mismo se puede hacer desde la pantalla "Depurar" (solo superadmin); este script es la
 * versión por línea de comandos, útil para varias empresas a la vez. La lógica está en
 * services/PurgaService.php (qué se borra y qué no se documenta ahí).
 *
 *   php cron/purgar_anterior.php                      → SIMULACIÓN: solo cuenta lo que borraría
 *   php cron/purgar_anterior.php --empresa=3          → simulación de una sola empresa
 *   php cron/purgar_anterior.php --ejecutar --respaldo-hecho   → borra de verdad
 *
 * IRREVERSIBLE. Antes de ejecutar de verdad hay que sacar un respaldo, p. ej.:
 *   mysqldump --single-transaction -u USUARIO -p BASE > respaldo_antes_de_purgar.sql
 * Cada empresa va en su propia transacción. Una empresa sin saldos de apertura del primer
 * año conservado se SALTA salvo que se pase --forzar.
 */
define('ROOT', dirname(__DIR__));
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo por línea de comandos.\n"); }
if (file_exists(ROOT . '/.env')) {
    foreach (parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW) as $k => $v) putenv("$k=$v");
}
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/PurgaService.php';

$opts     = getopt('', ['ejecutar', 'respaldo-hecho', 'forzar', 'empresa::', 'hasta::']);
$ejecutar = isset($opts['ejecutar']);
$forzar   = isset($opts['forzar']);
$hasta    = $opts['hasta'] ?? Periodo::minimo();
$soloEmp  = isset($opts['empresa']) ? (int)$opts['empresa'] : null;

if (!preg_match('/^\d{6}$/', $hasta)) exit("--hasta debe ser YYYYMM.\n");
if ($ejecutar && !isset($opts['respaldo-hecho'])) {
    exit("Para borrar de verdad agrega también --respaldo-hecho (confirma que ya sacaste el respaldo de la base).\n");
}

$pdo = Model::db();
echo ($ejecutar ? "*** EJECUCIÓN REAL ***" : "SIMULACIÓN (no se borra nada)"), " — se conserva {$hasta} en adelante; se borra lo anterior.\n\n";

$total = 0;
foreach ($pdo->query("SELECT id, ruc, razon_social FROM empresas ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $e) {
    $id = (int)$e['id'];
    if ($soloEmp !== null && $id !== $soloEmp) continue;
    echo "── Empresa {$id} · {$e['razon_social']} ({$e['ruc']})\n";

    $ap = PurgaService::apertura($pdo, $id, $hasta);
    if (!$ap['ok'] && !$forzar) {
        echo "   SALTADA: no tiene saldos de apertura de {$ap['anio']}; al borrar los años anteriores su balance {$ap['anio']} perdería el punto de partida.\n";
        echo "            Carga la Apertura {$ap['anio']} (o cierra el año anterior) y repite, o usa --forzar si estás seguro.\n\n";
        continue;
    }
    foreach (PurgaService::conteos($pdo, $id, $hasta) as $c) echo sprintf("   %-22s %8s\n", $c['etiqueta'], number_format($c['n']));
    if (!$ejecutar) { echo "\n"; continue; }

    $pdo->beginTransaction();
    try {
        do { $r = PurgaService::paso($pdo, $id, $hasta); $total += $r['borradas']; } while (!$r['terminado']);
        $pdo->commit();
        echo "   ✓ borrado.\n\n";
    } catch (Throwable $ex) {
        $pdo->rollBack();
        echo "   ✗ ERROR, esta empresa quedó como estaba: " . $ex->getMessage() . "\n\n";
    }
}
echo $ejecutar ? "Filas borradas (directas): " . number_format($total) . "\n"
               : "Simulación terminada. Para borrar de verdad: php cron/purgar_anterior.php --ejecutar --respaldo-hecho\n";
