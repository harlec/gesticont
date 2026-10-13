<?php
/** Libro Diario (rediseño). Variables: $empresa, $periodo, $asientos, $pendientes. */
$id = (int)$empresa['id'];
$contActiva = 'diario'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$nAuto = count(array_filter($asientos, fn($a) => $a['origen'] !== 'manual'));
$nMan  = count($asientos) - $nAuto;
$contTitulo = 'Libro Diario';
$contDesc = Periodo::etiqueta($periodo, true) . ' · ' . count($asientos) . ' asiento' . (count($asientos) === 1 ? '' : 's');
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/diario/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>'
              . '<button type="button" class="gc-btn" onclick="openModal(\'diario-regenerar\')">Volver a generar</button>';
require ROOT . '/views/layout/contabilidad_head.php';
$fmt = fn($v) => abs((float)$v) < 0.005 ? '' : number_format((float)$v, 2);
foreach (['diario_error' => 'neg', 'diario_aviso' => 'warn', 'diario_ok' => 'ok'] as $key => $tipo):
    if (empty($_SESSION[$key])) continue; ?>
<div class="gc-alert <?= $tipo ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$key]) ?></span></div>
<?php unset($_SESSION[$key]); endforeach;
$libroClave = 'diario'; require ROOT . '/views/layout/regimen_badge.php'; ?>

<?php if ((int)$pendientes['ventas'] > 0 || (int)$pendientes['compras'] > 0): ?>
<div class="gc-alert warn"><span class="msg">Quedan <b><?= (int)$pendientes['ventas'] ?> venta(s)</b> y <b><?= (int)$pendientes['compras'] ?> compra(s)</b> sin clasificar en este período. El asiento generado no las incluye hasta que las clasifiques.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/imputacion">Clasificar ahora</a></div>
<?php endif; ?>
<div class="gc-alert info"><span class="msg">Los asientos <b>automáticos</b> salen de tus ventas, compras, cobros y pagos clasificados. Los <b>manuales</b> los registras tú en <a href="/empresas/<?= $id ?>/asientos">Asientos manuales</a>. Al volver a generar solo se rehacen los automáticos.</span></div>

<?php if (empty($asientos)): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin asientos en este período</b>Genera los asientos cuando las compras y ventas estén clasificadas.
    <div style="margin-top:14px;"><button type="button" class="gc-btn gc-btn-primary" onclick="openModal('diario-regenerar')">Generar asientos</button></div></div></div>
<?php else: $totD = $totH = 0.0; ?>
<div class="gc-seg no-print" style="width:max-content;max-width:100%;" id="dia-filtro" role="group" aria-label="Tipo de asiento">
    <button type="button" class="is-active" data-f="">Todos (<?= count($asientos) ?>)</button>
    <button type="button" data-f="auto">Automáticos (<?= $nAuto ?>)</button>
    <button type="button" data-f="manual">Manuales (<?= $nMan ?>)</button>
</div>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:720px;">
    <thead><tr><th style="width:60px;">N°</th><th style="width:100px;">Fecha</th><th>Cuenta</th><th class="r" style="width:130px;">Debe</th><th class="r" style="width:130px;">Haber</th></tr></thead>
    <?php foreach ($asientos as $a):
        $sd = array_sum(array_column($a['lineas'], 'debe')); $sh = array_sum(array_column($a['lineas'], 'haber'));
        $totD += $sd; $totH += $sh; $man = $a['origen'] === 'manual'; ?>
    <tbody class="dia-asiento" data-tipo="<?= $man ? 'manual' : 'auto' ?>">
        <tr style="background:var(--gc-brand-soft);">
            <td class="mono" style="font-weight:700;color:var(--gc-brand);"><?= $a['correlativo'] ?></td>
            <td class="mono" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
            <td colspan="3"><span class="gc-chip <?= $man ? 'warn' : '' ?>" style="margin-right:8px;"><?= $man ? 'Manual' : 'Automático' ?></span><b><?= htmlspecialchars($a['glosa']) ?></b></td>
        </tr>
        <?php foreach ($a['lineas'] as $l): $esHaber = (float)$l['haber'] > 0 && (float)$l['debe'] == 0; ?>
        <tr>
            <td></td><td></td>
            <td style="<?= $esHaber ? 'padding-left:36px;' : '' ?>"><span class="mono" style="font-weight:700;"><?= $l['codigo'] ?></span> <?= htmlspecialchars($l['nombre']) ?></td>
            <td class="r num"><?= $fmt($l['debe']) ?></td>
            <td class="r num"><?= $fmt($l['haber']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
    <tfoot><tr><td colspan="3">Total del período</td><td class="r num"><?= number_format($totD, 2) ?></td><td class="r num"><?= number_format($totH, 2) ?></td></tr></tfoot>
</table>
</section>
<script>
document.querySelectorAll('#dia-filtro button').forEach(function (b) {
    b.addEventListener('click', function () {
        var f = b.getAttribute('data-f');
        document.querySelectorAll('#dia-filtro button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        document.querySelectorAll('.dia-asiento').forEach(function (g) { g.style.display = (!f || g.getAttribute('data-tipo') === f) ? '' : 'none'; });
    });
});
</script>
<?php endif; ?>

<div class="gc-modal-back" id="diario-regenerar" onmousedown="if(event.target===this)closeModal('diario-regenerar')">
  <form class="gc-modal sm" method="POST" action="/empresas/<?= $id ?>/diario/generar">
    <h2>¿Volver a generar <?= Periodo::etiqueta($periodo, true) ?>?</h2>
    <p style="margin:12px 0;">Se rehacen los asientos <b>automáticos</b> con lo que hoy está clasificado. Los asientos manuales no se tocan.</p>
    <input type="hidden" name="periodo" value="<?= $periodo ?>">
    <div class="gc-row" style="justify-content:flex-end;margin-top:18px;"><button type="button" class="gc-btn" onclick="closeModal('diario-regenerar')">Cancelar</button><button type="submit" class="gc-btn gc-btn-primary">Sí, generar</button></div>
  </form>
</div>
