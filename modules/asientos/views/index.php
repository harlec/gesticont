<?php
$fmt   = fn($v) => number_format((float)$v, 2);
$base  = "/empresas/{$empresa['id']}/asientos";
$anios = Periodo::anios();
if (!in_array($anio, $anios, true)) array_unshift($anios, $anio);

// Borrador tras un error de validación (no se pierde lo digitado) o asiento en edición.
$borrador = $_SESSION['asiento_borrador'] ?? null; unset($_SESSION['asiento_borrador']);
$form = $borrador ?: ($editando ? ['fecha' => $editando['fecha'], 'glosa' => $editando['glosa'], 'id' => $editando['id'],
        'lineas' => array_map(fn($l) => ['cuenta_id' => $l['cuenta_id'], 'debe' => $l['debe'], 'haber' => $l['haber']], $editando['lineas'])]
        : ['fecha' => date('Y-m-d'), 'glosa' => '', 'id' => 0, 'lineas' => []]);
$inp = 'padding:8px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);';
$lbl = 'display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;';
?>
<div class="gc-content gc-w-content">

    <?php foreach (['asiento_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', 'var(--gc-pos-border)', '✓'], 'asiento_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', 'var(--gc-neg-border)', '⚠']] as $k => [$bg, $fg, $bd, $ic]): ?>
    <?php if (!empty($_SESSION[$k])): ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border:1px solid <?= $bd ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= $ic ?> <?= htmlspecialchars($_SESSION[$k]) ?></div>
    <?php unset($_SESSION[$k]); endif; endforeach; ?>

    <div style="margin-bottom:14px;">
        <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Asientos manuales</div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            Para ajustes, provisiones (CTS, gratificaciones, vacaciones), depreciación y correcciones. Entran al Libro Diario, Mayor y Balances como cualquier asiento,
            y la regeneración automática nunca los borra. Solo se guardan si cuadran (Debe = Haber).
        </div>
    </div>

    <!-- Formulario -->
    <form method="POST" action="<?= $base ?>/guardar" id="as-form" style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:20px;overflow:hidden;">
        <input type="hidden" name="asiento_id" value="<?= (int)$form['id'] ?>">
        <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <span style="font-weight:700;font-size:13px;color:var(--gc-ink);"><?= $form['id'] ? '✏️ Editando asiento' : '+ Nuevo asiento' ?></span>
            <span style="display:flex;align-items:center;gap:8px;">
                <label style="font-size:12px;color:var(--gc-muted);">Plantilla:</label>
                <select id="as-plantilla" style="<?= $inp ?>font-size:12px;">
                    <option value="">— elegir —</option>
                    <option value="cts">Provisión de CTS / gratificaciones / vacaciones</option>
                    <option value="deprec">Depreciación del mes</option>
                    <option value="adelanto">Adelanto de sueldo al personal</option>
                </select>
            </span>
        </div>
        <div style="padding:16px 20px;display:grid;grid-template-columns:170px 1fr;gap:12px;">
            <div><label style="<?= $lbl ?>">Fecha</label><input type="date" name="fecha" required value="<?= htmlspecialchars($form['fecha']) ?>" style="<?= $inp ?>width:100%;"></div>
            <div><label style="<?= $lbl ?>">Glosa (descripción) *</label><input type="text" name="glosa" id="as-glosa" required maxlength="250" value="<?= htmlspecialchars($form['glosa']) ?>" placeholder="Ej: Provisión de CTS mayo 2026" style="<?= $inp ?>width:100%;"></div>
        </div>
        <div style="padding:0 20px 8px;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:640px;">
                <thead><tr style="color:var(--gc-label-2);border-bottom:1px solid var(--gc-line);">
                    <th style="padding:6px 6px;text-align:left;">Cuenta</th><th style="padding:6px 6px;text-align:right;width:150px;">Debe</th><th style="padding:6px 6px;text-align:right;width:150px;">Haber</th><th style="width:36px;"></th>
                </tr></thead>
                <tbody id="as-lineas"></tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--gc-line);font-weight:700;">
                        <td style="padding:8px 6px;"><button type="button" id="as-add" style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">+ Agregar línea</button></td>
                        <td id="as-tdebe" style="padding:8px 6px;text-align:right;font-family:monospace;">0.00</td>
                        <td id="as-thaber" style="padding:8px 6px;text-align:right;font-family:monospace;">0.00</td><td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div style="padding:12px 20px;border-top:1px solid var(--gc-line);display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <span id="as-estado" style="font-size:13px;font-weight:700;"></span>
            <span style="display:flex;gap:10px;">
                <?php if ($form['id']): ?><a href="<?= $base ?>?anio=<?= $anio ?>" style="background:var(--gc-surface-2);color:var(--gc-label);padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Cancelar edición</a><?php endif; ?>
                <button type="submit" id="as-guardar" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:9px 22px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                    💾 <?= $form['id'] ? 'Guardar cambios' : 'Registrar asiento' ?></button>
            </span>
        </div>
    </form>

    <!-- Lista -->
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
        <div style="font-size:14px;font-weight:700;color:var(--gc-ink);margin-right:8px;">Asientos manuales de <?= $anio ?></div>
        <?php foreach ($anios as $a): $act = $a === $anio; ?>
        <a href="<?= $base ?>?anio=<?= $a ?>" style="padding:5px 12px;border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $a ?></a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($manuales)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:36px;text-align:center;color:var(--gc-muted);font-size:13px;">Aún no hay asientos manuales en <?= $anio ?>.</div>
    <?php endif; ?>
    <?php foreach ($manuales as $m): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:10px;overflow:hidden;">
        <div style="padding:10px 18px;background:var(--gc-bg);display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">
            <span><strong style="font-family:monospace;">N.° <?= $m['correlativo'] ?></strong> · <?= $m['fecha'] ?> · <?= htmlspecialchars($m['glosa']) ?></span>
            <span style="display:flex;gap:8px;align-items:center;">
                <a href="<?= $base ?>?anio=<?= $anio ?>&editar=<?= $m['id'] ?>" style="font-size:12px;font-weight:600;color:var(--gc-brand);text-decoration:none;">Editar</a>
                <form method="POST" action="<?= $base ?>/<?= $m['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar este asiento?');" style="margin:0;">
                    <input type="hidden" name="anio" value="<?= $anio ?>">
                    <button type="submit" style="background:none;border:none;color:var(--gc-neg);font-size:12px;font-weight:600;cursor:pointer;">Eliminar</button></form>
            </span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <?php foreach ($m['lineas'] as $l): ?>
            <tr style="border-top:1px solid var(--gc-surface-2);">
                <td style="padding:5px 18px;<?= $l['haber'] > 0 ? 'padding-left:44px;' : '' ?>"><span style="font-family:monospace;font-weight:700;"><?= $l['codigo'] ?></span> <?= htmlspecialchars($l['nombre']) ?></td>
                <td style="padding:5px 18px;text-align:right;font-family:monospace;width:130px;"><?= $l['debe'] > 0 ? $fmt($l['debe']) : '' ?></td>
                <td style="padding:5px 18px;text-align:right;font-family:monospace;width:130px;"><?= $l['haber'] > 0 ? $fmt($l['haber']) : '' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const CUENTAS = <?= json_encode($cuentas, JSON_UNESCAPED_UNICODE) ?>;
    const POR_CODIGO = {}; CUENTAS.forEach(c => POR_CODIGO[c.codigo] = c.id);
    const INICIAL = <?= json_encode($form['lineas'], JSON_UNESCAPED_UNICODE) ?>;
    const PLANTILLAS = {
        cts:      { glosa: 'Provisión de CTS / gratificaciones / vacaciones ', lineas: [['629', 'debe'], ['415', 'haber']] },
        deprec:   { glosa: 'Depreciación del mes ',                            lineas: [['6841', 'debe'], ['395', 'haber']] },
        adelanto: { glosa: 'Adelanto de sueldo al personal ',                  lineas: [['141', 'debe'], ['101', 'haber']] }
    };
    const tbody = document.getElementById('as-lineas');
    const opciones = '<option value="">— cuenta —</option>' + CUENTAS.map(c => '<option value="' + c.id + '">' + c.codigo + ' — ' + c.nombre.replace(/</g, '&lt;') + '</option>').join('');

    function num(v) { return parseFloat(String(v || '0').replace(/,/g, '')) || 0; }

    function fila(cuentaId, debe, haber) {
        const tr = document.createElement('tr');
        tr.style.borderTop = '1px solid var(--gc-surface-2)';
        tr.innerHTML =
            '<td style="padding:5px 6px;"><select name="cuenta_id[]" style="width:100%;padding:7px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);">' + opciones + '</select></td>' +
            '<td style="padding:5px 6px;"><input type="number" step="0.01" min="0" name="debe[]" style="width:100%;text-align:right;font-family:monospace;padding:7px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);"></td>' +
            '<td style="padding:5px 6px;"><input type="number" step="0.01" min="0" name="haber[]" style="width:100%;text-align:right;font-family:monospace;padding:7px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);"></td>' +
            '<td style="padding:5px 6px;text-align:center;"><button type="button" class="as-del" style="background:none;border:none;color:var(--gc-neg);cursor:pointer;font-size:14px;">✕</button></td>';
        const [sel, d, h] = [tr.querySelector('select'), tr.querySelector('[name="debe[]"]'), tr.querySelector('[name="haber[]"]')];
        if (cuentaId) sel.value = String(cuentaId);
        if (num(debe) > 0) d.value = num(debe).toFixed(2);
        if (num(haber) > 0) h.value = num(haber).toFixed(2);
        // Una línea es de un solo lado: escribir en uno vacía el otro.
        d.addEventListener('input', () => { if (num(d.value) > 0) h.value = ''; recalcular(); });
        h.addEventListener('input', () => { if (num(h.value) > 0) d.value = ''; recalcular(); });
        tr.querySelector('.as-del').addEventListener('click', () => { tr.remove(); recalcular(); });
        tbody.appendChild(tr);
    }

    function recalcular() {
        let d = 0, h = 0, lineas = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            const a = num(tr.querySelector('[name="debe[]"]').value), b = num(tr.querySelector('[name="haber[]"]').value);
            d += a; h += b; if ((a > 0 || b > 0) && tr.querySelector('select').value) lineas++;
        });
        d = Math.round(d * 100) / 100; h = Math.round(h * 100) / 100;
        document.getElementById('as-tdebe').textContent = d.toFixed(2);
        document.getElementById('as-thaber').textContent = h.toFixed(2);
        const est = document.getElementById('as-estado'), btn = document.getElementById('as-guardar');
        const dif = Math.round((d - h) * 100) / 100;
        let ok = false;
        if (d === 0 && h === 0) { est.textContent = 'Agrega las líneas del asiento.'; est.style.color = 'var(--gc-muted)'; }
        else if (dif !== 0) { est.textContent = '✗ No cuadra — diferencia S/ ' + Math.abs(dif).toFixed(2) + (dif > 0 ? ' (falta Haber)' : ' (falta Debe)'); est.style.color = 'var(--gc-neg)'; }
        else if (lineas < 2) { est.textContent = 'Necesita al menos dos líneas con cuenta y monto.'; est.style.color = 'var(--gc-warn)'; }
        else { est.textContent = '✓ Cuadra: Debe = Haber = S/ ' + d.toFixed(2); est.style.color = 'var(--gc-pos)'; ok = true; }
        btn.disabled = !ok; btn.style.opacity = ok ? '1' : '.5'; btn.style.cursor = ok ? 'pointer' : 'not-allowed';
    }

    document.getElementById('as-add').addEventListener('click', () => { fila('', '', ''); recalcular(); });
    tbody.addEventListener('change', recalcular);

    document.getElementById('as-plantilla').addEventListener('change', function () {
        const p = PLANTILLAS[this.value]; if (!p) return;
        tbody.innerHTML = '';
        p.lineas.forEach(([cod, lado]) => fila(POR_CODIGO[cod] || '', '', ''));
        const g = document.getElementById('as-glosa'); if (!g.value.trim()) g.value = p.glosa;
        recalcular();
        this.value = '';
    });

    if (INICIAL.length) INICIAL.forEach(l => fila(l.cuenta_id, l.debe, l.haber)); else { fila('', '', ''); fila('', '', ''); }
    recalcular();
})();
</script>
