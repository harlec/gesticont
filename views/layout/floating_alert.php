<?php
/**
 * Alerta flotante y no invasiva para diagnósticos de control (descuadres,
 * verificaciones que no coinciden) en las pantallas de estados financieros.
 * Antes estos avisos eran un bloque grande incrustado arriba del contenido
 * — en pantallas con poco margen (ej. Balance General con explicación de
 * dos párrafos) tapaban toda la tabla y obligaban a hacer scroll para ver
 * cualquier dato. Ahora es una pastilla fija en una esquina, colapsada por
 * defecto, que no ocupa espacio del documento — se expande solo si el
 * contador quiere leer el detalle, y se puede descartar.
 *
 * Variables esperadas antes de este require:
 *   $falertTipo         'warn' | 'neg'
 *   $falertResumen       string — línea corta, siempre visible al expandir
 *   $falertDetalleHtml   string — HTML del detalle completo
 *   $falertId            string — único en la página (para el toggle)
 */
$falertBg     = $falertTipo === 'neg' ? 'var(--gc-neg-soft)'   : 'var(--gc-warn-soft)';
$falertFg     = $falertTipo === 'neg' ? 'var(--gc-neg)'        : 'var(--gc-warn)';
$falertBorder = $falertTipo === 'neg' ? 'var(--gc-neg-border)' : 'var(--gc-warn-border)';
?>
<div id="<?= $falertId ?>" class="gc-float-alert" style="position:fixed;left:20px;bottom:24px;max-width:380px;z-index:9998;background:var(--gc-surface);border:1px solid <?= $falertBorder ?>;border-radius:12px;box-shadow:0 16px 34px -18px rgba(22,41,79,0.35);overflow:hidden;">
    <button type="button" class="gc-float-alert-toggle" data-target="<?= $falertId ?>-body"
            style="display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;background:<?= $falertBg ?>;color:<?= $falertFg ?>;border:none;cursor:pointer;text-align:left;font-family:inherit;">
        <span style="font-size:15px;flex-shrink:0;">⚠</span>
        <span style="font-size:12.5px;font-weight:700;flex:1;line-height:1.3;"><?= htmlspecialchars($falertResumen) ?></span>
        <span class="gc-float-alert-caret" style="font-size:10px;flex-shrink:0;transition:transform .15s;">▾</span>
        <span class="gc-float-alert-close" role="button" title="Descartar" style="font-size:14px;flex-shrink:0;opacity:.6;padding:0 2px;">×</span>
    </button>
    <div id="<?= $falertId ?>-body" style="display:none;padding:12px 16px;font-size:12.5px;color:var(--gc-label);line-height:1.5;max-height:50vh;overflow-y:auto;">
        <?= $falertDetalleHtml ?>
    </div>
</div>
<script>
(function () {
    var root = document.getElementById('<?= $falertId ?>');
    if (!root) return;
    var toggle = root.querySelector('.gc-float-alert-toggle');
    var caret = root.querySelector('.gc-float-alert-caret');
    var body = document.getElementById('<?= $falertId ?>-body');
    var close = root.querySelector('.gc-float-alert-close');
    toggle.addEventListener('click', function (e) {
        if (e.target === close) return;
        var open = body.style.display === 'block';
        body.style.display = open ? 'none' : 'block';
        caret.style.transform = open ? '' : 'rotate(180deg)';
    });
    close.addEventListener('click', function (e) {
        e.stopPropagation();
        root.style.display = 'none';
    });
})();
</script>
