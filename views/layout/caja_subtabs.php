<?php
/**
 * Sub-navegación de Caja y Bancos: movimientos, Libro Caja y Bancos
 * (matriz mensual) y préstamos bancarios. Requiere $empresa y $cajaTab
 * ('movimientos'|'libro'|'prestamos').
 */
$cajaTabs = [
    ['movimientos', '💵', 'Movimientos',            '/caja'],
    ['libro',       '📒', 'Libro Caja y Bancos',    '/caja/libro'],
    ['prestamos',   '🏦', 'Préstamos bancarios',    '/prestamos'],
];
?>
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
    <?php foreach ($cajaTabs as [$k, $ico, $lbl, $ruta]): $act = $k === $cajaTab; ?>
    <a href="/empresas/<?= $empresa['id'] ?><?= $ruta ?>"
       style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
              background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
        <span><?= $ico ?></span><?= $lbl ?>
    </a>
    <?php endforeach; ?>
</div>
