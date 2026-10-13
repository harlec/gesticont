<?php
/** Honorarios · recibos de 4ta categoría (rediseño). Variables: $empresa, $periodo, $anio, $recibos, $tot, $resumenAnual. */
$id = (int)$empresa['id'];
$base = "/empresas/{$id}/honorarios";
$S = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : number_format((float)$v, 2);
foreach (['honorario_ok' => 'ok', 'honorario_error' => 'neg'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach; ?>

<div class="gc-stack" style="gap:4px;"><h2 style="margin:0;font-size:24px;font-weight:700;">Honorarios</h2>
    <div class="gc-sub">Recibos por honorarios (4.ª categoría) de <?= Periodo::etiqueta($periodo, true) ?></div></div>
<div class="gc-alert info"><span class="msg">Cada recibo se contabiliza como Debe 632 / Haber 424 (neto por pagar) + Haber 4017 (retención) al generar los asientos del mes. Se asume <b>pagado</b>: el pago del neto entra a Caja. La retención se entrega a SUNAT aparte, como egreso de Caja contra 4017.</span></div>

<?php if (!empty($recibos)): ?>
<section class="gc-kpis">
    <div class="gc-kpi"><div class="k">Monto bruto</div><div class="v num">S/ <?= number_format($tot['monto'], 2) ?></div></div>
    <div class="gc-kpi"><div class="k">Retención 4017</div><div class="v num">S/ <?= number_format($tot['retencion'], 2) ?></div></div>
    <div class="gc-kpi"><div class="k">Neto</div><div class="v num">S/ <?= number_format($tot['neto'], 2) ?></div></div>
    <div class="gc-kpi <?= $tot['pendiente'] > 0 ? 'warn' : 'ok' ?>"><div class="k">Por pagar</div><div class="v num">S/ <?= number_format($tot['pendiente'], 2) ?></div></div>
</section>
<?php endif; ?>

<section class="gc-card pad gc-stack" style="gap:14px;">
    <h3 class="gc-h3" style="margin:0;">Registrar un recibo</h3>
    <form method="POST" action="<?= $base ?>/crear" class="gc-row" style="gap:12px;align-items:flex-end;">
        <input type="hidden" name="periodo" value="<?= $periodo ?>">
        <div class="gc-field" style="width:170px;"><label class="gc-label">Fecha</label><input type="date" name="fecha" required value="<?= date('Y-m-d') ?>" class="gc-input"></div>
        <div class="gc-field" style="flex:2;min-width:240px;"><label class="gc-label">Prestador</label><input type="text" name="prestador_nombre" required placeholder="Nombre o razón social" class="gc-input"></div>
        <div class="gc-field" style="width:160px;"><label class="gc-label">RUC o DNI</label><input type="text" name="prestador_doc" maxlength="15" class="gc-input"></div>
        <div class="gc-field" style="width:170px;"><label class="gc-label">Recibo (E001-123)</label><input type="text" name="comprobante" maxlength="30" class="gc-input"></div>
        <div class="gc-field" style="width:150px;"><label class="gc-label">Monto bruto (S/)</label><input type="number" name="monto" id="hon-monto" step="0.01" min="0.01" required class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="width:150px;"><label class="gc-label">Retención 8 %</label><input type="number" name="retencion" id="hon-ret" step="0.01" min="0" placeholder="automática" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="flex:2;min-width:200px;"><label class="gc-label">Concepto</label><input type="text" name="descripcion" maxlength="200" class="gc-input"></div>
        <label class="gc-check" style="min-height:44px;"><input type="checkbox" name="pagado" checked> Ya se pagó</label>
        <button type="submit" class="gc-btn gc-btn-primary">Registrar</button>
    </form>
    <div class="gc-sub">La retención se calcula sola (8 %) cuando el recibo supera S/ 1,500. Déjala vacía para eso, o escríbela (0 si el prestador tiene suspensión de retenciones).</div>
</section>

<?php if (empty($recibos)): ?>
<div class="gc-card"><div class="gc-empty"><b>No hay recibos en <?= Periodo::etiqueta($periodo, true) ?></b>Regístralos arriba.</div></div>
<?php else: ?>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:900px;">
    <thead><tr><th>Fecha</th><th>Prestador</th><th>Recibo</th><th class="r">Monto</th><th class="r">Retención</th><th class="r">Neto</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recibos as $r): ?>
        <tr>
            <td class="mono" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td><b><?= htmlspecialchars($r['prestador_nombre']) ?></b><?php if ($r['prestador_doc']): ?><div class="mono gc-sub" style="font-size:14px;"><?= htmlspecialchars($r['prestador_doc']) ?></div><?php endif; ?></td>
            <td class="mono"><?= htmlspecialchars((string)$r['comprobante']) ?></td>
            <td class="r num"><?= $S($r['monto']) ?></td>
            <td class="r num"><?= $S($r['retencion']) ?></td>
            <td class="r num" style="font-weight:700;"><?= $S(HonorarioService::neto($r)) ?></td>
            <td><span class="gc-chip <?= $r['pagado'] ? 'ok' : 'warn' ?>"><?= $r['pagado'] ? 'Pagado' : 'Pendiente' ?></span></td>
            <td style="white-space:nowrap;text-align:right;">
                <?php if ($r['pagado']): ?>
                <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/pendiente" style="display:inline;"><input type="hidden" name="periodo" value="<?= $periodo ?>"><button type="submit" class="gc-btn">Pasar a pendiente</button></form>
                <?php else: ?>
                <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/pagar" class="gc-row" style="display:inline-flex;gap:8px;"><input type="hidden" name="periodo" value="<?= $periodo ?>">
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="gc-input" style="width:160px;"><button type="submit" class="gc-btn gc-btn-primary">Registrar pago</button></form>
                <?php endif; ?>
                <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/eliminar" style="display:inline;" onsubmit="return confirm('¿Quitar este recibo?');"><input type="hidden" name="periodo" value="<?= $periodo ?>"><button type="submit" class="gc-btn">Quitar</button></form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="3">Total del mes</td><td class="r num"><?= number_format($tot['monto'], 2) ?></td><td class="r num"><?= number_format($tot['retencion'], 2) ?></td><td class="r num"><?= number_format($tot['neto'], 2) ?></td><td colspan="2"><?= $tot['pendiente'] > 0 ? 'Por pagar: S/ ' . number_format($tot['pendiente'], 2) : '' ?></td></tr></tfoot>
</table>
</section>
<?php endif; ?>

<h3 class="gc-h3" style="margin:8px 0 0;">Honorarios de enero a diciembre <?= $anio ?></h3>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table">
    <thead><tr><th>Mes</th><th class="r">Recibos</th><th class="r">Monto</th><th class="r">Retención</th><th class="r">Neto</th></tr></thead>
    <tbody>
    <?php $ta = ['n' => 0, 'm' => 0.0, 'r' => 0.0];
    for ($m = 1; $m <= 12; $m++):
        $p = sprintf('%s%02d', $anio, $m); $x = $resumenAnual[$p] ?? null;
        if ($x) { $ta['n'] += $x['n']; $ta['m'] += $x['monto']; $ta['r'] += $x['retencion']; } ?>
        <tr style="<?= $p === $periodo ? 'background:var(--gc-brand-soft);' : '' ?>">
            <td><a href="<?= $base ?>?periodo=<?= $p ?>" style="color:var(--gc-ink);text-decoration:none;font-weight:600;"><?= Periodo::etiqueta($p, true) ?></a></td>
            <td class="r num"><?= $x ? (int)$x['n'] : '<span style="color:var(--gc-muted);">—</span>' ?></td>
            <td class="r num"><?= $x ? $S($x['monto']) : $S(0) ?></td>
            <td class="r num"><?= $x ? $S($x['retencion']) : $S(0) ?></td>
            <td class="r num" style="font-weight:700;"><?= $x ? $S($x['monto'] - $x['retencion']) : $S(0) ?></td>
        </tr>
    <?php endfor; ?>
    </tbody>
    <tfoot><tr><td>Total del año</td><td class="r num"><?= $ta['n'] ?></td><td class="r num"><?= number_format($ta['m'], 2) ?></td><td class="r num"><?= number_format($ta['r'], 2) ?></td><td class="r num"><?= number_format($ta['m'] - $ta['r'], 2) ?></td></tr></tfoot>
</table>
</section>
