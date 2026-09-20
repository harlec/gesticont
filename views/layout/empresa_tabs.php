<?php
/**
 * Barra de navegación fija para todas las pantallas dentro de una empresa.
 * Requiere que $empresa esté definido en el scope que la incluye.
 * Una sola fila, scroll horizontal si no cabe — nunca se envuelve a varias
 * líneas, para no comerse espacio vertical de la página.
 *
 * Cada pestaña puede agrupar varias rutas bajo un solo ítem (ej.
 * "Comprobantes" cubre ventas/compras/sync/clasificar) — el href apunta
 * a la primera ruta del grupo, pero la pestaña se marca activa si la URL
 * actual coincide con cualquiera de las rutas listadas.
 *
 * "Certificado" no está aquí a propósito — el usuario decidió que no
 * necesita su propia pestaña; ya tiene acceso rápido desde la tarjeta
 * "Certificado digital" en Resumen.
 */
$tabs = [
    ['icon' => '🏢', 'label' => 'Resumen',      'rutas' => ['']],
    ['icon' => '📄', 'label' => 'Comprobantes', 'rutas' => ['ventas', 'compras', 'sync', 'imputacion']],
    ['icon' => '📋', 'label' => 'Apertura',     'rutas' => ['apertura']],
    ['icon' => '👥', 'label' => 'Planillas',    'rutas' => ['planillas']],
    ['icon' => '💰', 'label' => 'Caja',         'rutas' => ['caja']],
    ['icon' => '📖', 'label' => 'Diario',       'rutas' => ['diario']],
    ['icon' => '⚖️', 'label' => 'Balance',      'rutas' => ['balance']],
    ['icon' => '🏛️', 'label' => 'Bal. General', 'rutas' => ['balance-general']],
    ['icon' => '📊', 'label' => 'Resultados',   'rutas' => ['resultados']],
    ['icon' => '📈', 'label' => 'Patrimonio',   'rutas' => ['cambios-patrimonio']],
    ['icon' => '💵', 'label' => 'Flujo',        'rutas' => ['flujo-efectivo']],
    ['icon' => '🔒', 'label' => 'Cierre',       'rutas' => ['cierre']],
];
$empresaTabsBase = '/empresas/' . $empresa['id'];
// Mismo rtrim que usa Router::dispatch() al hacer match de rutas — sin
// esto, una URL con "/" final nunca coincide exacto y "Resumen" no se
// resalta aunque el Router sí haya cargado esa página correctamente.
$empresaTabsPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

$rutaCoincide = function (string $ruta) use ($empresaTabsBase, $empresaTabsPath): bool {
    $href = $ruta === '' ? $empresaTabsBase : "{$empresaTabsBase}/{$ruta}";
    // "Resumen" (ruta vacía) solo por coincidencia exacta — su href es
    // prefijo literal de todas las demás rutas y marcaría todo activo.
    if ($ruta === '') return $empresaTabsPath === $href;
    return $empresaTabsPath === $href || strpos($empresaTabsPath, $href . '/') === 0;
};
?>
<div style="background:white;border-bottom:1px solid #e2e8f0;margin:-28px -28px 20px;padding:0 20px;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;">
    <div style="display:inline-flex;gap:2px;">
    <?php foreach ($tabs as $tab):
        $primeraRuta = $tab['rutas'][0];
        $href = $primeraRuta === '' ? $empresaTabsBase : "{$empresaTabsBase}/{$primeraRuta}";
        $activo = false;
        foreach ($tab['rutas'] as $ruta) { if ($rutaCoincide($ruta)) { $activo = true; break; } }
    ?>
        <a href="<?= $href ?>"
           style="display:inline-flex;align-items:center;gap:5px;padding:12px 12px;font-size:12px;font-weight:600;text-decoration:none;
                  border-bottom:2px solid <?= $activo ? '#1e3a8a' : 'transparent' ?>;color:<?= $activo ? '#1e3a8a' : '#64748b' ?>;">
            <span style="font-size:13px;"><?= $tab['icon'] ?></span><?= $tab['label'] ?>
        </a>
    <?php endforeach; ?>
    </div>
</div>
