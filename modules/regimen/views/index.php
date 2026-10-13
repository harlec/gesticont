<?php
$mesActual = date('Y-m');
$minMes    = substr(Periodo::minimo(), 0, 4) . '-' . substr(Periodo::minimo(), 4, 2);
$etqMes    = fn(?string $f) => $f ? Periodo::etiqueta(date('Ym', strtotime($f)), true) : null;
$tramosJs  = ['mype' => RegimenService::tramos('mype'), 'general' => RegimenService::tramos('general')];
$inp = 'width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-surface);color:var(--gc-ink);font-family:inherit;';
$lbl = 'display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;';
?>
<?php $empTab = 'regimen'; require ROOT . '/views/layout/empresa_subtabs.php'; ?>

<div class="gc-content gc-w-content">
<div style="max-width:820px;margin:0 auto;">

    <?php foreach (['regimen_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', '✓'], 'regimen_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', '⚠']] as $k => [$bg, $fg, $ic]):
        if (empty($_SESSION[$k])) continue; ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= $ic ?> <?= htmlspecialchars($_SESSION[$k]) ?></div>
    <?php unset($_SESSION[$k]); endforeach; ?>

    <div style="margin-bottom:14px;">
        <div style="font-size:18px;font-weight:700;color:var(--gc-ink);">Régimen tributario</div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC <?= $empresa['ruc'] ?></div>
    </div>

    <!-- Vigente en el período de trabajo -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Vigente en <?= Periodo::etiqueta($periodo, true) ?></div>
        <div style="font-size:20px;font-weight:700;color:var(--gc-brand);margin:4px 0;"><?= RegimenService::NOMBRES[$vigente['regimen']] ?? $vigente['regimen'] ?></div>
        <div style="font-size:13px;color:var(--gc-label);">
            <?php if ($vigente['desde']): ?>Desde <?= $etqMes($vigente['desde']) ?><?= $vigente['hasta'] ? ' hasta ' . $etqMes($vigente['hasta']) : '' ?>.<?php else: ?>Sin historial registrado: se muestra el régimen guardado en la empresa.<?php endif; ?>
            <?php if ($vigente['tramo']): ?> Ingresos brutos anuales: <strong><?= RegimenService::tramos($vigente['regimen'])[$vigente['tramo']] ?? $vigente['tramo'] ?></strong>.<?php endif; ?>
        </div>
        <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--gc-surface-2);">
            <div style="font-size:13px;font-weight:700;color:var(--gc-ink);margin-bottom:6px;"><?= htmlspecialchars($libros['etiqueta']) ?></div>
            <?php if ($libros['libros']): ?>
            <ul style="margin:0 0 0 18px;padding:0;font-size:13px;color:var(--gc-label);line-height:1.7;">
                <?php foreach ($libros['libros'] as $l): ?><li><?= htmlspecialchars($l) ?></li><?php endforeach; ?>
            </ul>
            <?php else: ?>
            <div style="font-size:13px;color:var(--gc-label);">No está obligado a llevar libros contables; solo conserva sus comprobantes.</div>
            <?php endif; ?>
            <div style="font-size:12px;color:var(--gc-muted);margin-top:8px;">Libros obligatorios según la RS 234-2006/SUNAT. Es solo informativo: el sistema no oculta ni bloquea pantallas por el régimen.</div>
        </div>
    </div>

    <!-- Cambiar -->
    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/regimen/cambiar" style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:14px;font-weight:700;color:var(--gc-ink);margin-bottom:12px;">Cambiar régimen</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label style="<?= $lbl ?>">Nuevo régimen</label>
                <select name="regimen" id="rgRegimen" style="<?= $inp ?>" onchange="rgTramos()">
                    <?php foreach (RegimenService::NOMBRES as $k => $n): ?>
                    <option value="<?= $k ?>" <?= $k === $vigente['regimen'] ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="<?= $lbl ?>">Rige desde (mes)</label>
                <input type="month" name="mes" value="<?= $mesActual ?>" min="<?= $minMes ?>" required style="<?= $inp ?>">
            </div>
            <div id="rgTramoCaja" style="grid-column:1/-1;">
                <label style="<?= $lbl ?>">Ingresos brutos anuales (define los libros)</label>
                <select name="tramo" id="rgTramo" style="<?= $inp ?>"></select>
            </div>
            <div style="grid-column:1/-1;">
                <label style="<?= $lbl ?>">Nota (opcional)</label>
                <input type="text" name="nota" maxlength="300" placeholder="Ej.: cambio por superar el tope del RER" style="<?= $inp ?>">
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">Guardar cambio</button>
        </div>
    </form>

    <!-- Historial -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:12px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;color:var(--gc-ink);">Historial</div>
        <?php if (!$historial): ?>
        <div style="padding:16px 20px;font-size:13px;color:var(--gc-muted);">Aún no hay historial (falta aplicar la migración <code>fase1k_regimen_vigencia.sql</code>).</div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="background:var(--gc-bg);color:var(--gc-muted);">
                <th style="padding:8px 20px;text-align:left;">Régimen</th><th style="padding:8px 12px;text-align:left;">Ingresos</th>
                <th style="padding:8px 12px;text-align:left;">Desde</th><th style="padding:8px 12px;text-align:left;">Hasta</th><th style="padding:8px 20px;text-align:left;">Nota</th>
            </tr></thead>
            <?php foreach ($historial as $h): ?>
            <tr style="border-top:1px solid var(--gc-surface-2);">
                <td style="padding:8px 20px;font-weight:600;"><?= RegimenService::CORTOS[$h['regimen']] ?? $h['regimen'] ?></td>
                <td style="padding:8px 12px;color:var(--gc-label);"><?= $h['tramo'] ? (RegimenService::tramos($h['regimen'])[$h['tramo']] ?? $h['tramo']) : '—' ?></td>
                <td style="padding:8px 12px;"><?= $etqMes($h['vigente_desde']) ?></td>
                <td style="padding:8px 12px;"><?= $h['vigente_hasta'] ? $etqMes($h['vigente_hasta']) : '<span style="color:var(--gc-pos);font-weight:600;">vigente</span>' ?></td>
                <td style="padding:8px 20px;color:var(--gc-muted);"><?= htmlspecialchars((string)($h['nota'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

</div>
</div>
<script>
const RG_TRAMOS = <?= json_encode($tramosJs, JSON_UNESCAPED_UNICODE) ?>;
const RG_TRAMO_ACTUAL = <?= json_encode($vigente['tramo']) ?>;
function rgTramos() {
    const r = document.getElementById('rgRegimen').value, sel = document.getElementById('rgTramo'), caja = document.getElementById('rgTramoCaja');
    const t = RG_TRAMOS[r];
    caja.style.display = t ? 'block' : 'none';
    sel.innerHTML = '<option value="">Por definir</option>' + (t ? Object.entries(t).map(([k, v]) => `<option value="${k}"${k === RG_TRAMO_ACTUAL ? ' selected' : ''}>${v}</option>`).join('') : '');
}
rgTramos();
</script>
