<?php
/** Cuentas por cobrar / por pagar (rediseño). Variables: $empresa, $periodo, $r, $esVenta, $titulo, $tercero, $rutaBase, $origen. */
$id = (int)$empresa['id'];
$contActiva = $esVenta ? 'cuentas-por-cobrar' : 'cuentas-por-pagar'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = $titulo;
$contDesc = 'Saldo pendiente al ' . date('d/m/Y', strtotime($r['corte'])) . ' · ' . count($r['terceros']) . ' ' . strtolower($tercero) . (count($r['terceros']) === 1 ? '' : 's');
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/cobranzas?origen=' . $origen . '">Ir a Cobros y pagos</a>'
              . '<a class="gc-btn" href="' . $rutaBase . '/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>';
require ROOT . '/views/layout/contabilidad_head.php';
// Positivo = normal; negativo = nota de crédito/anticipo que sobra: "a favor".
$M = function ($v) use ($esVenta) {
    $v = (float)$v;
    if ($v < -0.004) return '<span style="color:var(--gc-pos);">S/ ' . number_format(abs($v), 2) . ' a favor</span>';
    return 'S/ ' . number_format($v, 2);
};
$tramoDe = function (int $d): string { foreach (CuentasPendientesService::TRAMOS as $n => [$a, $b]) if ($d >= $a && $d <= $b) return $n; return 'Más de 90'; };
?>
<section class="gc-kpis" id="cx-tarjetas">
    <button type="button" class="gc-kpi is-active" data-tramo="" style="text-align:left;cursor:pointer;">
        <div class="k">Total pendiente</div><div class="v num"><?= $M($r['total']) ?></div></button>
    <?php foreach ($r['tramos'] as $nom => $v): $rojo = $nom === 'Más de 90' && $v > 0.004; ?>
    <button type="button" class="gc-kpi" data-tramo="<?= htmlspecialchars($nom) ?>" style="text-align:left;cursor:pointer;">
        <div class="k"><?= $nom ?><?= $nom === 'Más de 90' ? '' : '' ?> días</div><div class="v num" style="<?= $rojo ? 'color:var(--gc-neg);' : '' ?>"><?= $M($v) ?></div></button>
    <?php endforeach; ?>
</section>

<?php if ($r['libro'] !== null): $ok = abs($r['sin_detalle']) < 0.02; ?>
<?php if ($ok): ?>
<div class="gc-alert ok"><span class="msg">Cuadra con el Mayor: la cuenta <?= $r['cuenta'] ?> tiene S/ <?= number_format($r['libro'], 2) ?>, igual al detalle por comprobante.</span></div>
<?php else: ?>
<div class="gc-alert warn"><span class="msg">La cuenta <?= $r['cuenta'] ?> del Mayor tiene S/ <?= number_format($r['libro'], 2) ?> y el detalle por comprobante suma S/ <?= number_format($r['total'], 2) ?>. Diferencia de <b>S/ <?= number_format(abs($r['sin_detalle']), 2) ?></b> sin comprobante detrás (saldo de apertura u otros asientos).</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/mayor?cuenta=<?= urlencode($r['cuenta']) ?>">Ver el Mayor de la <?= $r['cuenta'] ?></a></div>
<?php endif; endif; ?>

<?php if (empty($r['terceros'])): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin saldos pendientes</b>No hay comprobantes con saldo a esta fecha.</div></div>
<?php else: ?>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:760px;" id="cx-tabla">
    <thead><tr><th><?= $tercero ?> / Documento</th><th>Fecha</th><th class="r">Días</th><th class="r">Total</th><th class="r"><?= $esVenta ? 'Cobrado' : 'Pagado' ?></th><th class="r">Saldo</th></tr></thead>
    <?php foreach ($r['terceros'] as $t): ?>
    <tbody class="cx-tercero">
        <tr style="background:var(--gc-brand-soft);">
            <td colspan="5"><span class="mono" style="color:var(--gc-brand);font-weight:700;"><?= htmlspecialchars($t['doc']) ?></span> <b style="margin-left:6px;"><?= htmlspecialchars($t['nombre']) ?></b></td>
            <td class="r num" style="font-weight:700;"><?= $M($t['saldo']) ?></td>
        </tr>
        <?php foreach ($t['docs'] as $d): $esNc = $d['tipo'] === '07'; ?>
        <tr class="cx-doc" data-tramo="<?= htmlspecialchars($tramoDe((int)$d['dias'])) ?>">
            <td style="padding-left:28px;"><span class="gc-chip <?= $esNc ? 'info' : '' ?>" style="margin-right:8px;"><?= $esNc ? 'Nota de crédito' : 'Factura' ?></span><span class="mono"><?= htmlspecialchars($d['numero']) ?></span></td>
            <td class="mono" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
            <td class="r num" style="<?= $d['dias'] > 90 ? 'color:var(--gc-neg);font-weight:700;' : '' ?>"><?= $d['dias'] ?></td>
            <td class="r num"><?= number_format((float)$d['total'], 2) ?></td>
            <td class="r num" style="color:var(--gc-pos);"><?= $d['pagado'] > 0 ? number_format((float)$d['pagado'], 2) : '—' ?></td>
            <td class="r num" style="font-weight:600;"><?= $M($d['saldo']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <?php endforeach; ?>
    <tfoot><tr style="background:var(--gc-bg);font-weight:700;"><td colspan="5">Total de comprobantes con saldo</td><td class="r num"><?= $M($r['total']) ?></td></tr></tfoot>
</table>
</section>
<div class="gc-sub">Solo comprobantes clasificados. Saldo = total − lo <?= $esVenta ? 'cobrado' : 'pagado' ?> hasta el cierre del período. Las notas de crédito restan del saldo. Toca una tarjeta para ver solo esa antigüedad.</div>
<script>
(function () {
    var tarjetas = document.querySelectorAll('#cx-tarjetas .gc-kpi');
    tarjetas.forEach(function (b) {
        b.addEventListener('click', function () {
            var t = b.getAttribute('data-tramo');
            tarjetas.forEach(function (x) { x.classList.toggle('is-active', x === b); });
            document.querySelectorAll('#cx-tabla .cx-tercero').forEach(function (g) {
                var visibles = 0;
                g.querySelectorAll('.cx-doc').forEach(function (r) {
                    var ok = !t || r.getAttribute('data-tramo') === t;
                    r.style.display = ok ? '' : 'none'; if (ok) visibles++;
                });
                g.style.display = visibles ? '' : 'none';
            });
        });
    });
})();
</script>
<?php endif; ?>
