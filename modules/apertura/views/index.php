<?php
$anios = Periodo::anios();
?>

<?php
    $id = (int)$empresa['id'];
    $cerrado = $periodoContable && $periodoContable['estado'] === 'cerrado';
    $tituloReporte = 'Inventario Inicial (Apertura)';
    $subtituloReporte = 'Año ' . $anio;
?>
<div class="print-only" style="margin-bottom:12px;"><b style="font-size:18px;">Inventario Inicial (Apertura) · <?= $anio ?></b><br><?= htmlspecialchars($empresa['razon_social']) ?> · RUC <?= htmlspecialchars($empresa['ruc']) ?></div>
<div class="gc-row between no-print">
    <div class="gc-stack" style="gap:4px;"><h2 style="margin:0;font-size:24px;font-weight:700;">Apertura <?= $anio ?></h2>
        <div class="gc-sub">Lo que tenía la empresa al 1 de enero: es el punto de partida de todos los libros.</div></div>
    <div class="gc-row" style="gap:10px;">
        <div class="gc-seg"><?php foreach ($anios as $a): ?><a href="?anio=<?= $a ?>" class="<?= $a === $anio ? 'is-active' : '' ?>"><?= $a ?></a><?php endforeach; ?></div>
        <a class="gc-btn" href="/empresas/<?= $id ?>/apertura/pdf?anio=<?= $anio ?>" target="_blank" rel="noopener">Descargar PDF</a>
    </div>
</div>
<?php if (!empty($_SESSION['apertura_error'])): ?><div class="gc-alert neg no-print" role="alert"><span class="msg"><?= htmlspecialchars($_SESSION['apertura_error']) ?></span></div><?php unset($_SESSION['apertura_error']); endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="gc-alert ok no-print" role="status"><span class="msg">Inventario inicial guardado.</span></div><?php endif; ?>
<?php if ($cerrado): ?><div class="gc-alert warn no-print"><span class="msg">El año <?= $anio ?> está cerrado: solo lectura.</span></div><?php endif; ?>

<div>
    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/apertura/guardar">
        <input type="hidden" name="anio" value="<?= $anio ?>">

        <div id="ap-status" class="gc-alert no-print" style="display:none;font-weight:700;"></div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(420px,1fr));gap:16px;margin:16px 0;">
            <!-- ACTIVO -->
            <div class="gc-card clip">
                <div style="padding:14px 20px;background:var(--gc-brand-soft);color:var(--gc-brand);font-weight:700;font-size:16px;">Activo · lo que tiene la empresa</div>
                <div style="max-height:480px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:16px;">
                    <tbody>
                    <?php foreach ($cuentasActivo as $c):
                        $tieneValor = isset($saldosExistentes[$c['id']]);
                        $valorFmt = $tieneValor ? number_format($saldosExistentes[$c['id']], 2, '.', '') : '';
                    ?>
                        <tr class="<?= $tieneValor ? '' : 'no-print' ?>" style="border-top:1px solid var(--gc-surface-2);">
                            <td style="padding:6px 10px 6px 20px;white-space:nowrap;">
                                <span style="font-family:monospace;font-weight:700;color:var(--gc-label);font-size:15px;"><?= $c['codigo'] ?></span>
                                <div style="font-size:15px;color:var(--gc-ink);"><?= htmlspecialchars($c['nombre']) ?></div>
                            </td>
                            <td style="padding:6px 20px 6px 6px;width:170px;">
                                <input type="text" inputmode="decimal" class="gc-input ap-input ap-activo no-print" data-cuenta="<?= $c['id'] ?>" data-naturaleza="<?= $c['naturaleza'] ?>"
                                       name="monto[<?= $c['id'] ?>]"
                                       value="<?= $valorFmt ?>"
                                       placeholder="0.00"
                                       style="text-align:right;">
                                <!-- El navegador no siempre pinta el VALOR de un <input> al exportar a
                                     PDF (es una propiedad, no texto real) — este span solo se ve al
                                     imprimir y garantiza que el monto sí quede en el papel. -->
                                <span class="print-only" style="text-align:right;font-family:monospace;font-size:13px;"><?= $valorFmt ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="padding:10px 20px;background:var(--gc-bg);border-top:2px solid var(--gc-line);display:flex;justify-content:space-between;font-weight:700;font-size:17px;">
                    <span>Total Activo</span>
                    <span id="ap-total-activo" style="font-family:monospace;">0.00</span>
                </div>
            </div>

            <!-- PASIVO Y PATRIMONIO -->
            <div class="gc-card clip">
                <div style="padding:14px 20px;background:var(--gc-warn-soft);color:var(--gc-warn);font-weight:700;font-size:16px;">Pasivo y patrimonio · lo que debe y el capital</div>
                <div style="max-height:480px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:16px;">
                    <tbody>
                    <?php foreach ($cuentasPasivo as $c):
                        $tieneValor = isset($saldosExistentes[$c['id']]);
                        $valorFmt = $tieneValor ? number_format($saldosExistentes[$c['id']], 2, '.', '') : '';
                    ?>
                        <tr class="<?= $tieneValor ? '' : 'no-print' ?>" style="border-top:1px solid var(--gc-surface-2);">
                            <td style="padding:6px 10px 6px 20px;white-space:nowrap;">
                                <span style="font-family:monospace;font-weight:700;color:var(--gc-label);font-size:15px;"><?= $c['codigo'] ?></span>
                                <?php if ($c['tipo'] === 'patrimonio'): ?><span style="font-size:10px;color:var(--gc-muted);">· Patrimonio</span><?php endif; ?>
                                <div style="font-size:15px;color:var(--gc-ink);"><?= htmlspecialchars($c['nombre']) ?></div>
                            </td>
                            <td style="padding:6px 20px 6px 6px;width:170px;">
                                <input type="text" inputmode="decimal" class="gc-input ap-input ap-pasivo no-print" data-cuenta="<?= $c['id'] ?>" data-naturaleza="<?= $c['naturaleza'] ?>"
                                       name="monto[<?= $c['id'] ?>]"
                                       value="<?= $valorFmt ?>"
                                       placeholder="0.00"
                                       style="text-align:right;">
                                <span class="print-only" style="text-align:right;font-family:monospace;font-size:13px;"><?= $valorFmt ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div style="padding:10px 20px;background:var(--gc-bg);border-top:2px solid var(--gc-line);display:flex;justify-content:space-between;font-weight:700;font-size:17px;">
                    <span>Total Pasivo + Patrimonio</span>
                    <span id="ap-total-pasivo" style="font-family:monospace;">0.00</span>
                </div>
            </div>
        </div>

        <?php if (!$periodoContable || $periodoContable['estado'] !== 'cerrado'): ?>
        <button type="submit" id="ap-submit" class="gc-btn gc-btn-primary gc-btn-lg no-print">Guardar la apertura</button>
        <?php endif; ?>
    </form>
</div>

<script>
(function () {
    function num(v) { return parseFloat((v || '0').toString().replace(/,/g, '')) || 0; }

    // El monto siempre se escribe en positivo (o negativo, da igual — se
    // usa su valor absoluto); el signo con el que entra al total lo decide
    // la naturaleza real de la cuenta, igual que en el backend
    // (AperturaController::guardar). Sin esto, una cuenta contra-activo
    // como 395 Depreciación Acumulada se sumaba al Activo en vez de
    // restarse, y la alerta de "no cuadra" salía aunque el guardado real
    // (que sí calcula bien) hubiera funcionado.
    function neto(input) {
        const v = Math.abs(num(input.value));
        const esDeudora = input.dataset.naturaleza === 'deudora';
        return esDeudora ? v : -v;
    }

    function recalcular() {
        let totalActivo = 0, totalPasivo = 0;
        document.querySelectorAll('.ap-activo').forEach(i => totalActivo += neto(i));
        document.querySelectorAll('.ap-pasivo').forEach(i => totalPasivo += -neto(i));

        document.getElementById('ap-total-activo').textContent = totalActivo.toFixed(2);
        document.getElementById('ap-total-pasivo').textContent = totalPasivo.toFixed(2);

        const status = document.getElementById('ap-status');
        const diff = Math.round((totalActivo - totalPasivo) * 100) / 100;
        if (totalActivo === 0 && totalPasivo === 0) {
            status.style.display = 'none';
        } else if (Math.abs(diff) < 0.01) {
            status.style.display = 'flex'; status.className = 'gc-alert ok no-print';
            status.textContent = 'Cuadra: Activo = Pasivo + Patrimonio = S/ ' + totalActivo.toFixed(2);
        } else {
            status.style.display = 'flex'; status.className = 'gc-alert neg no-print';
            status.textContent = 'No cuadra: diferencia de S/ ' + diff.toFixed(2) + '. No se podrá guardar hasta que Activo = Pasivo + Patrimonio.';
        }
    }

    document.querySelectorAll('.ap-input').forEach(i => i.addEventListener('input', recalcular));
    recalcular();
})();
</script>
