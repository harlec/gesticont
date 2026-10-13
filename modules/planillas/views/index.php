<?php
/** Planillas (rediseño). Variables: $empresa, $periodo, $registros, $totales. */
$id = (int)$empresa['id'];
$S = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : number_format((float)$v, 2);
foreach (['planilla_error' => 'neg', 'planilla_ok' => 'ok'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach;
if (isset($_GET['ok']) && empty($_SESSION['planilla_ok'])): ?><div class="gc-alert ok"><span class="msg">Trabajador agregado.</span></div><?php endif; ?>

<div class="gc-stack" style="gap:4px;"><h2 style="margin:0;font-size:24px;font-weight:700;">Planillas</h2>
    <div class="gc-sub"><?= Periodo::etiqueta($periodo, true) ?> · <?= count($registros) ?> trabajador<?= count($registros) === 1 ? '' : 'es' ?></div></div>

<?php if (!empty($registros)): ?>
<section class="gc-kpis">
    <div class="gc-kpi"><div class="k">Remuneración bruta</div><div class="v num">S/ <?= number_format($totales['bruto'], 2) ?></div></div>
    <div class="gc-kpi"><div class="k">Retención ONP/AFP</div><div class="v num">S/ <?= number_format($totales['retencion'], 2) ?></div></div>
    <div class="gc-kpi"><div class="k">Neto a pagar</div><div class="v num">S/ <?= number_format($totales['neto'], 2) ?></div></div>
    <div class="gc-kpi"><div class="k">ESSALUD (empleador)</div><div class="v num">S/ <?= number_format($totales['essalud'], 2) ?></div></div>
</section>
<?php endif; ?>

<section class="gc-card pad gc-stack" style="gap:14px;">
    <h3 class="gc-h3" style="margin:0;">Agregar un trabajador</h3>
    <form method="POST" action="/empresas/<?= $id ?>/planillas" class="gc-row" style="gap:12px;align-items:flex-end;">
        <input type="hidden" name="periodo" value="<?= $periodo ?>">
        <div class="gc-field" style="flex:2;min-width:220px;"><label class="gc-label" for="p-trab">Trabajador</label><input type="text" id="p-trab" name="trabajador" required placeholder="Nombre completo" class="gc-input"></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label" for="p-sueldo">Sueldo</label><input type="text" inputmode="decimal" name="sueldo" id="p-sueldo" placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label">Gratificación</label><input type="text" inputmode="decimal" name="gratificacion" placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label">Asig. familiar</label><input type="text" inputmode="decimal" name="asignacion_familiar" placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label" for="p-essalud">ESSALUD (9%)</label><input type="text" inputmode="decimal" name="essalud" id="p-essalud" placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label">Pensión</label><select name="regimen_pension" class="gc-select"><option value="onp">ONP</option><option value="afp">AFP</option><option value="ninguno">Ninguna</option></select></div>
        <div class="gc-field" style="width:130px;"><label class="gc-label">Retención</label><input type="text" inputmode="decimal" name="retencion_pension" placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <button type="submit" class="gc-btn gc-btn-primary">Agregar</button>
    </form>
    <div class="gc-sub">El ESSALUD se calcula solo al 9 % del sueldo y puedes corregirlo. La retención de ONP o AFP la escribes tú: cada AFP cobra una comisión distinta.</div>
</section>

<section class="gc-card pad gc-stack" style="gap:10px;border-color:var(--gc-pos-border);">
    <h3 class="gc-h3" style="margin:0;color:var(--gc-pos);">Importar desde PLAME</h3>
    <div>En el PDT Planilla Electrónica exporta el reporte <b>R01: Trabajadores – Datos de ingresos, tributos y aportes</b> (archivo <span class="mono">RUC_AAAAMM_r01.xml</span>). Puedes subir <b>varios meses a la vez</b>: el mes y el RUC se leen del archivo, y volver a importar un mes reemplaza lo que ya vino de PLAME para ese mes.</div>
    <div class="gc-sub">El R01 trae el ingreso total (se carga como sueldo), la retención del trabajador y el aporte del empleador. Los descuentos como adelantos no se registran. El régimen ONP/AFP no viene en el archivo: se estima por el monto y lo corriges en la tabla.</div>
    <form method="POST" action="/empresas/<?= $id ?>/planillas/importar-plame" enctype="multipart/form-data" class="gc-row" style="gap:12px;">
        <input type="hidden" name="periodo" value="<?= $periodo ?>">
        <input type="file" name="archivos[]" accept=".xml" multiple required class="gc-input" style="max-width:420px;padding-top:9px;">
        <button type="submit" class="gc-btn gc-btn-primary">Importar archivos PLAME</button>
    </form>
</section>

<?php if (empty($registros)): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin trabajadores en <?= Periodo::etiqueta($periodo, true) ?></b>Agrégalos arriba o importa el reporte de PLAME.</div></div>
<?php else: ?>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:860px;">
    <thead><tr><th>Trabajador</th><th class="r">Sueldo</th><th class="r">Gratif.</th><th class="r">Asig. fam.</th><th class="r">ESSALUD</th><th>Pensión</th><th class="r">Retención</th><th class="r">Neto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($registros as $r):
        $neto = (float)$r['sueldo'] + (float)$r['gratificacion'] + (float)$r['asignacion_familiar'] - (float)$r['retencion_pension']; ?>
        <tr>
            <td><b><?= htmlspecialchars($r['trabajador']) ?></b></td>
            <td class="r num"><?= $S($r['sueldo']) ?></td><td class="r num"><?= $S($r['gratificacion']) ?></td><td class="r num"><?= $S($r['asignacion_familiar']) ?></td><td class="r num"><?= $S($r['essalud']) ?></td>
            <td><form method="POST" action="/empresas/<?= $id ?>/planillas/<?= $r['id'] ?>/regimen" style="margin:0;">
                <input type="hidden" name="periodo" value="<?= $periodo ?>">
                <select name="regimen_pension" onchange="this.form.submit()" title="Corregir régimen de pensión" class="gc-select" style="width:120px;">
                    <?php foreach (['onp' => 'ONP', 'afp' => 'AFP', 'ninguno' => 'Ninguna'] as $v => $l): ?><option value="<?= $v ?>" <?= $r['regimen_pension'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                </select></form></td>
            <td class="r num"><?= $S($r['retencion_pension']) ?></td>
            <td class="r num" style="font-weight:700;"><?= $S($neto) ?></td>
            <td class="r"><form method="POST" action="/empresas/<?= $id ?>/planillas/<?= $r['id'] ?>/eliminar" onsubmit="return confirm('¿Quitar este trabajador del período?')" style="margin:0;">
                <input type="hidden" name="periodo" value="<?= $periodo ?>"><button type="submit" class="gc-btn">Quitar</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td>Totales</td><td class="r num" colspan="3">Bruto <?= number_format($totales['bruto'], 2) ?></td><td class="r num"><?= number_format($totales['essalud'], 2) ?></td><td></td><td class="r num"><?= number_format($totales['retencion'], 2) ?></td><td class="r num"><?= number_format($totales['neto'], 2) ?></td><td></td></tr></tfoot>
</table>
</section>
<?php endif; ?>
<script>
document.getElementById('p-sueldo').addEventListener('input', function () {
    var s = parseFloat(this.value.replace(',', '.')) || 0;
    document.getElementById('p-essalud').value = s > 0 ? (s * 0.09).toFixed(2) : '';
});
</script>
