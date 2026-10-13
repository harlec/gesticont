<?php
/**
 * Encabezado común de cada subsección de Contabilidad: título 24/700 + descripción (con el rango) + acciones a la derecha.
 * Variables: $contTitulo, $contDesc, $contAcciones (HTML opcional).
 */
?>
<div class="gc-row between" style="align-items:flex-start;">
    <div class="gc-stack" style="gap:4px;">
        <h2 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.01em;"><?= htmlspecialchars($contTitulo) ?></h2>
        <?php if (!empty($contDesc)): ?><div class="gc-sub"><?= $contDesc ?></div><?php endif; ?>
    </div>
    <?php if (!empty($contAcciones)): ?><div class="gc-row no-print" style="gap:10px;"><?= $contAcciones ?></div><?php endif; ?>
</div>
