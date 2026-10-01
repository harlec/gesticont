<?php
/**
 * GestiCont — Borra la data contable anterior al período mínimo (por defecto
 * Periodo::minimo(), 202601 = se conserva 2026 en adelante y se borra 2025 y antes).
 *
 *   php cron/purgar_anterior.php                      → SIMULACIÓN: solo cuenta lo que borraría
 *   php cron/purgar_anterior.php --empresa=3          → simulación de una sola empresa
 *   php cron/purgar_anterior.php --ejecutar --respaldo-hecho   → borra de verdad
 *
 * IRREVERSIBLE. Antes de ejecutar de verdad hay que sacar un respaldo, p. ej.:
 *   mysqldump --single-transaction -u USUARIO -p BASE > respaldo_antes_de_purgar.sql
 *
 * Qué borra (solo lo anterior al mínimo):
 *   registro_ventas / registro_compras (y sus imputaciones, en cascada), resumen_mensual,
 *   caja_movimientos, planillas, honorarios, y los años contables anteriores completos
 *   (periodos_contables → asientos y detalle, saldos de apertura, parámetros y cierres).
 * Qué NO toca: comprobantes emitidos, guías, declaraciones, alertas, usuarios/empresas,
 *   plan de cuentas, reglas de imputación, préstamos ni los movimientos de caja ligados a un
 *   préstamo (el saldo de un préstamo del 2025 sigue vivo en 2026).
 * Cada empresa va en su propia transacción; si algo falla, esa empresa queda como estaba.
 * Una empresa sin saldos de apertura del primer año conservado se SALTA (borrar 2025 le
 * dejaría el balance 2026 sin punto de partida) salvo que se pase --forzar.
 */
define('ROOT', dirname(__DIR__));
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo por línea de comandos.\n"); }
if (file_exists(ROOT . '/.env')) {
    foreach (parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW) as $k => $v) putenv("$k=$v");
}
date_default_timezone_set('America/Lima');
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';

$opts      = getopt('', ['ejecutar', 'respaldo-hecho', 'forzar', 'empresa::', 'hasta::']);
$ejecutar  = isset($opts['ejecutar']);
$forzar    = isset($opts['forzar']);
$hasta     = $opts['hasta'] ?? Periodo::minimo();                 // se conserva este período y los posteriores
$soloEmp   = isset($opts['empresa']) ? (int)$opts['empresa'] : null;

if (!preg_match('/^\d{6}$/', $hasta)) exit("--hasta debe ser YYYYMM.\n");
if ($ejecutar && !isset($opts['respaldo-hecho'])) {
    exit("Para borrar de verdad agrega también --respaldo-hecho (confirma que ya sacaste el respaldo de la base).\n");
}
$fechaCorte = substr($hasta, 0, 4) . '-' . substr($hasta, 4, 2) . '-01';
$anioMin    = (int)substr($hasta, 0, 4);

$pdo = Model::db();
$existe = function (string $tabla) use ($pdo): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $s->execute([$tabla]); return (bool)$s->fetchColumn();
};
$tieneCol = function (string $tabla, string $col) use ($pdo): bool {
    $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $s->execute([$tabla, $col]); return (bool)$s->fetchColumn();
};

// [etiqueta, tabla, condición (con ? = empresa_id, luego los demás parámetros), parámetros extra]
$pasos = [
    ['ventas SIRE',            'registro_ventas',   "empresa_id = ? AND periodo < ?",           [$hasta]],
    ['compras SIRE',           'registro_compras',  "empresa_id = ? AND periodo < ?",           [$hasta]],
    ['resumen mensual',        'resumen_mensual',   "empresa_id = ? AND periodo < ?",           [$hasta]],
    ['movimientos de caja',    'caja_movimientos',  "empresa_id = ? AND fecha < ? AND prestamo_id IS NULL", [$fechaCorte]],
    ['planillas',              'planillas',         "empresa_id = ? AND periodo < ?",           [$hasta]],
    ['honorarios',             'honorarios',        "empresa_id = ? AND periodo < ?",           [$hasta]],
    ['asientos del diario',    'asientos',          "empresa_id = ? AND fecha < ?",             [$fechaCorte]],
    ['años contables',         'periodos_contables',"empresa_id = ? AND anio < ?",              [$anioMin]],
];

echo ($ejecutar ? "*** EJECUCIÓN REAL ***" : "SIMULACIÓN (no se borra nada)"), " — se conserva {$hasta} en adelante; se borra lo anterior.\n\n";

$empresas = $pdo->query("SELECT id, ruc, razon_social FROM empresas ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$totalGlobal = 0;

foreach ($empresas as $e) {
    $id = (int)$e['id'];
    if ($soloEmp !== null && $id !== $soloEmp) continue;
    echo "── Empresa {$id} · {$e['razon_social']} ({$e['ruc']})\n";

    // Seguro: el primer año conservado debe tener su apertura.
    $s = $pdo->prepare("
        SELECT COUNT(*) FROM saldos_apertura sa JOIN periodos_contables pc ON pc.id = sa.periodo_id
        WHERE sa.empresa_id = ? AND pc.anio = ?
    ");
    $s->execute([$id, $anioMin]);
    $tieneApertura = (int)$s->fetchColumn() > 0;
    $s = $pdo->prepare("SELECT COUNT(*) FROM periodos_contables WHERE empresa_id = ? AND anio < ?");
    $s->execute([$id, $anioMin]);
    $hayAniosViejos = (int)$s->fetchColumn() > 0;
    if ($hayAniosViejos && !$tieneApertura && !$forzar) {
        echo "   SALTADA: no tiene saldos de apertura de {$anioMin}; al borrar los años anteriores su balance {$anioMin} perdería el punto de partida.\n";
        echo "            Carga la Apertura {$anioMin} (o cierra el año anterior) y repite, o usa --forzar si estás seguro.\n\n";
        continue;
    }

    $conteos = [];
    foreach ($pasos as [$etq, $tabla, $cond, $extra]) {
        if (!$existe($tabla)) continue;
        // Las columnas que algunas instalaciones no tienen se omiten en vez de fallar.
        if (str_contains($cond, 'prestamo_id') && !$tieneCol($tabla, 'prestamo_id')) $cond = str_replace(' AND prestamo_id IS NULL', '', $cond);
        if (preg_match('/\b(periodo|fecha|anio)\b/', $cond, $m) && !$tieneCol($tabla, $m[1])) continue;
        $c = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$cond}");
        $c->execute(array_merge([$id], $extra));
        $conteos[] = [$etq, $tabla, $cond, $extra, (int)$c->fetchColumn()];
    }
    foreach ($conteos as [$etq, , , , $n]) echo sprintf("   %-22s %8s\n", $etq, number_format($n));

    if (!$ejecutar) { echo "\n"; continue; }

    $pdo->beginTransaction();
    try {
        // Orden: primero lo que cuelga de otras tablas; los años contables al final (borra en cascada sus asientos).
        foreach ($conteos as [$etq, $tabla, $cond, $extra, $n]) {
            $d = $pdo->prepare("DELETE FROM {$tabla} WHERE {$cond}");
            $d->execute(array_merge([$id], $extra));
            $totalGlobal += $d->rowCount();
        }
        $pdo->commit();
        echo "   ✓ borrado.\n\n";
    } catch (Throwable $ex) {
        $pdo->rollBack();
        echo "   ✗ ERROR, esta empresa quedó como estaba: " . $ex->getMessage() . "\n\n";
    }
}

echo $ejecutar ? "Filas borradas (directas): " . number_format($totalGlobal) . "\n"
               : "Simulación terminada. Para borrar de verdad: php cron/purgar_anterior.php --ejecutar --respaldo-hecho\n";
