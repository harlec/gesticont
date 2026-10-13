<?php
/** Sub-navegación de Caja y Bancos. Requiere $empresa y $cajaTab ('movimientos'|'libro'|'prestamos'). */
$__cajaTabs = [['movimientos', 'Movimientos', '/caja'], ['libro', 'Libro Caja y Bancos', '/caja/libro'], ['prestamos', 'Préstamos bancarios', '/prestamos']];
?>
<nav class="gc-tabs no-print" aria-label="Caja y Bancos">
    <?php foreach ($__cajaTabs as [$__k, $__lbl, $__ruta]): ?>
    <a class="gc-tab <?= $__k === ($cajaTab ?? '') ? 'is-active' : '' ?>" href="/empresas/<?= (int)$empresa['id'] ?><?= $__ruta ?>"><?= $__lbl ?></a>
    <?php endforeach; ?>
</nav>
