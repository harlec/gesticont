<?php
$anios = range((int)date('Y'), (int)date('Y') - 4);
?>

<div style="max-width:1100px;margin:0 auto;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:var(--gc-ink);">📋 Inventario Inicial (Saldos de Apertura)</div>
            <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['apertura_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['apertura_error']) ?>
    </div>
    <?php unset($_SESSION['apertura_error']); endif; ?>

    <?php if (isset($_GET['ok'])): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Inventario inicial guardado.
    </div>
    <?php endif; ?>

    <?php if ($periodoContable && $periodoContable['estado'] === 'cerrado'): ?>
    <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        🔒 El período <?= $anio ?> está cerrado — solo lectura.
    </div>
    <?php endif; ?>

    <!-- Selector de año -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Año de apertura:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($anios as $a): $activo = $a === $anio; ?>
            <a href="?anio=<?= $a ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $a ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/apertura/guardar">
        <input type="hidden" name="anio" value="<?= $anio ?>">

        <div id="ap-status" style="border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:700;"></div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
            <!-- ACTIVO -->
            <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
                <div style="padding:12px 20px;background:var(--gc-brand-soft);color:var(--gc-brand);font-weight:700;font-size:13px;">ACTIVO (Debe)</div>
                <div style="max-height:480px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tbody>
                    <?php foreach ($cuentasActivo as $c): ?>
                        <tr style="border-top:1px solid var(--gc-surface-2);">
                            <td style="padding:6px 10px 6px 20px;white-space:nowrap;">
                                <span style="font-family:monospace;font-weight:700;color:var(--gc-label);font-size:12px;"><?= $c['codigo'] ?></span>
                                <div style="font-size:12px;color:var(--gc-ink);"><?= htmlspecialchars($c['nombre']) ?></div>
                            </td>
                            <td style="padding:6px 20px 6px 6px;width:130px;">
                                <input type="text" inputmode="decimal" class="ap-input ap-activo" data-cuenta="<?= $c['id'] ?>"
                                       name="monto[<?= $c['id'] ?>]"
                                       value="<?= isset($saldosExistentes[$c['id']]) ? number_format($saldosExistentes[$c['id']], 2, '.', '') : '' ?>"
                                       placeholder="0.00"
                                       style="width:100%;padding:5px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:13px;text-align:right;font-family:monospace;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="padding:10px 20px;background:var(--gc-bg);border-top:2px solid var(--gc-line);display:flex;justify-content:space-between;font-weight:700;font-size:13px;">
                    <span>Total Activo</span>
                    <span id="ap-total-activo" style="font-family:monospace;">0.00</span>
                </div>
            </div>

            <!-- PASIVO Y PATRIMONIO -->
            <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
                <div style="padding:12px 20px;background:var(--gc-warn-soft);color:var(--gc-warn);font-weight:700;font-size:13px;">PASIVO Y PATRIMONIO (Haber)</div>
                <div style="max-height:480px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <tbody>
                    <?php foreach ($cuentasPasivo as $c): ?>
                        <tr style="border-top:1px solid var(--gc-surface-2);">
                            <td style="padding:6px 10px 6px 20px;white-space:nowrap;">
                                <span style="font-family:monospace;font-weight:700;color:var(--gc-label);font-size:12px;"><?= $c['codigo'] ?></span>
                                <?php if ($c['tipo'] === 'patrimonio'): ?><span style="font-size:10px;color:var(--gc-muted);">· Patrimonio</span><?php endif; ?>
                                <div style="font-size:12px;color:var(--gc-ink);"><?= htmlspecialchars($c['nombre']) ?></div>
                            </td>
                            <td style="padding:6px 20px 6px 6px;width:130px;">
                                <input type="text" inputmode="decimal" class="ap-input ap-pasivo" data-cuenta="<?= $c['id'] ?>"
                                       name="monto[<?= $c['id'] ?>]"
                                       value="<?= isset($saldosExistentes[$c['id']]) ? number_format($saldosExistentes[$c['id']], 2, '.', '') : '' ?>"
                                       placeholder="0.00"
                                       style="width:100%;padding:5px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:13px;text-align:right;font-family:monospace;">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="padding:10px 20px;background:var(--gc-bg);border-top:2px solid var(--gc-line);display:flex;justify-content:space-between;font-weight:700;font-size:13px;">
                    <span>Total Pasivo + Patrimonio</span>
                    <span id="ap-total-pasivo" style="font-family:monospace;">0.00</span>
                </div>
            </div>
        </div>

        <?php if (!$periodoContable || $periodoContable['estado'] !== 'cerrado'): ?>
        <button type="submit" id="ap-submit"
                style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
            💾 Guardar Inventario Inicial
        </button>
        <?php endif; ?>
    </form>
</div>

<script>
(function () {
    function num(v) { return parseFloat((v || '0').toString().replace(/,/g, '')) || 0; }

    function recalcular() {
        let totalActivo = 0, totalPasivo = 0;
        document.querySelectorAll('.ap-activo').forEach(i => totalActivo += num(i.value));
        document.querySelectorAll('.ap-pasivo').forEach(i => totalPasivo += num(i.value));

        document.getElementById('ap-total-activo').textContent = totalActivo.toFixed(2);
        document.getElementById('ap-total-pasivo').textContent = totalPasivo.toFixed(2);

        const status = document.getElementById('ap-status');
        const diff = Math.round((totalActivo - totalPasivo) * 100) / 100;
        if (totalActivo === 0 && totalPasivo === 0) {
            status.style.display = 'none';
        } else if (Math.abs(diff) < 0.01) {
            status.style.display = 'block';
            status.style.background = 'var(--gc-pos-soft-2)'; status.style.color = 'var(--gc-pos)';
            status.textContent = '✓ Cuadra: Activo = Pasivo + Patrimonio = ' + totalActivo.toFixed(2);
        } else {
            status.style.display = 'block';
            status.style.background = 'var(--gc-neg-soft)'; status.style.color = 'var(--gc-neg)';
            status.textContent = '✗ No cuadra — diferencia ' + diff.toFixed(2) + '. No se podrá guardar hasta que Activo = Pasivo + Patrimonio.';
        }
    }

    document.querySelectorAll('.ap-input').forEach(i => i.addEventListener('input', recalcular));
    recalcular();
})();
</script>
