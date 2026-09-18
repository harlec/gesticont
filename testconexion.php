<?php
$dominios = [
    'api.sunat.gob.pe',
    'apisire.sunat.gob.pe',
    'api-sire.sunat.gob.pe',
    'api-seguridad.sunat.gob.pe',
    'sire.sunat.gob.pe',
    'e-menu.sunat.gob.pe',
];

echo '<pre>';
foreach ($dominios as $d) {
    $ip = gethostbyname($d);
    $ok = $ip !== $d;
    echo ($ok ? "✓" : "✗") . " {$d} → " . ($ok ? $ip : "NO RESUELVE") . "\n";
}
echo '</pre>';
?>