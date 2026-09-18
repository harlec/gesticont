<?php
/**
 * GestiCont — CRON: Alertas de certificados
 * Ejecutar: 0 7 * * * php /var/www/gesticont/cron/alertas_certificados.php >> storage/logs/alertas.log 2>&1
 */
define('ROOT', dirname(__DIR__));
if (file_exists(ROOT . '/.env')) { $env = parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW); foreach ($env as $k => $v) putenv("$k=$v"); }
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';
$log = fn(string $msg) => print("[" . date('Y-m-d H:i:s') . "] $msg\n");
$log("=== ALERTAS CERTIFICADOS ===");
$db = Model::db();
$db->query("CALL sp_generar_alertas_certificados()", []);
$vencen = $db->query("SELECT ec.empresa_id, ec.cert_hasta, e.razon_social, DATEDIFF(ec.cert_hasta, CURDATE()) as dias FROM empresa_certificados ec JOIN empresas e ON e.id=ec.empresa_id WHERE ec.estado='activo' AND ec.cert_hasta BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND ec.alerta_enviada=0")->fetchAll(PDO::FETCH_ASSOC);
foreach ($vencen as $c) {
    $log("  Alerta: {$c['razon_social']} — cert vence en {$c['dias']} días ({$c['cert_hasta']})");
    $db->query("UPDATE empresa_certificados SET alerta_enviada=1 WHERE empresa_id=? AND cert_hasta=?", [$c['empresa_id'], $c['cert_hasta']]);
}
$log("Alertas generadas: " . count($vencen));
$log("=== FIN ===");
