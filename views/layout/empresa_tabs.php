<?php
/**
 * Barra de navegación fija para todas las pantallas dentro de una empresa.
 * Requiere que $empresa esté definido en el scope que la incluye.
 * Una sola fila, scroll horizontal si no cabe — nunca se envuelve a varias
 * líneas, para no comerse espacio vertical de la página.
 */
$tabs = [
    ['',                    '🏢', 'Resumen'],
    ['ventas',              '📄', 'Ventas'],
    ['compras',             '🧾', 'Compras'],
    ['sync',                '🔄', 'Sincronizar'],
    ['certificado',         '🔑', 'Certificado'],
    ['imputacion',          '🏷️', 'Clasificar'],
    ['apertura',            '📋', 'Apertura'],
    ['planillas',           '👥', 'Planillas'],
    ['caja',                '💰', 'Caja'],
    ['diario',              '📖', 'Diario'],
    ['balance',             '⚖️', 'Balance'],
    ['balance-general',     '🏛️', 'Bal. General'],
    ['resultados',          '📊', 'Resultados'],
    ['cambios-patrimonio',  '📈', 'Patrimonio'],
    ['flujo-efectivo',      '💵', 'Flujo'],
    ['cierre',              '🔒', 'Cierre'],
];
$empresaTabsBase = '/empresas/' . $empresa['id'];
// Mismo rtrim que usa Router::dispatch() al hacer match de rutas — sin
// esto, una URL con "/" final nunca coincide exacto y "Resumen" no se
// resalta aunque el Router sí haya cargado esa página correctamente.
$empresaTabsPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
?>
<div style="background:white;border-bottom:1px solid #e2e8f0;margin:-28px -28px 20px;padding:0 20px;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;">
    <div style="display:inline-flex;gap:2px;">
    <?php foreach ($tabs as [$suffix, $icon, $label]):
        $href = $suffix === '' ? $empresaTabsBase : "{$empresaTabsBase}/{$suffix}";
        // Coincidencia exacta o de subruta (ej. /diario?periodo=X) — nunca
        // por prefijo simple, porque "balance" es prefijo literal de
        // "balance-general" (y la ruta vacía de "Resumen" es prefijo de
        // TODAS las demás) — esas marcarían la pestaña equivocada activa.
        $activo = $suffix === ''
            ? $empresaTabsPath === $href
            : ($empresaTabsPath === $href || strpos($empresaTabsPath, $href . '/') === 0);
    ?>
        <a href="<?= $href ?>"
           style="display:inline-flex;align-items:center;gap:5px;padding:12px 12px;font-size:12px;font-weight:600;text-decoration:none;
                  border-bottom:2px solid <?= $activo ? '#1e3a8a' : 'transparent' ?>;color:<?= $activo ? '#1e3a8a' : '#64748b' ?>;">
            <span style="font-size:13px;"><?= $icon ?></span><?= $label ?>
        </a>
    <?php endforeach; ?>
    </div>
</div>
