<?php
/**
 * Sub-navegación del desplegable "Comprobantes" — Ventas, Compras,
 * Sincronizar y Clasificar viven bajo un solo ítem en el menú horizontal
 * (views/layout/nav.php); esta franja secundaria, más chica, es la que deja
 * moverse entre esas 4 sin salir de la sección.
 * Requiere $empresa en el scope. $subtabActiva: 'ventas'|'compras'|'sync'|'imputacion'.
 */
$subtabs = [
    ['ventas',     '📄', 'Ventas'],
    ['compras',    '🧾', 'Compras'],
    ['sync',       '🔄', 'Sincronizar'],
    ['imputacion', '🏷️', 'Clasificar'],
];
?>
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach ($subtabs as [$ruta, $icon, $label]):
        $activo = $ruta === $subtabActiva;
    ?>
    <a href="/empresas/<?= $empresa['id'] ?>/<?= $ruta ?>"
       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
              background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
        <span><?= $icon ?></span><?= $label ?>
    </a>
    <?php endforeach; ?>
</div>
