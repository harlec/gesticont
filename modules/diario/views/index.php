<?php
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '';
?>

<div class="gc-content gc-w-content">

    <?php foreach (['diario_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', '⚠'], 'diario_aviso' => ['var(--gc-warn-soft)', 'var(--gc-warn)', 'ℹ'], 'diario_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', '✓']] as $key => [$bg, $fg, $icon]):
        if (empty($_SESSION[$key])) continue; ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        <?= $icon ?> <?= htmlspecialchars($_SESSION[$key]) ?>
    </div>
    <?php unset($_SESSION[$key]); endforeach; ?>

    <!-- Barra de acciones: el período se cambia desde la barra superior -->
    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Libro Diario</div>
            <div style="font-size:12px;color:var(--gc-muted);"><?= Periodo::etiqueta($periodo, true) ?> · <?= count($asientos) ?> asiento(s)</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php if (!empty($asientos)): $totD = $totH = 0.0; ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);color:var(--gc-muted);">
                    <th style="padding:7px 12px;text-align:left;width:52px;">N°</th>
                    <th style="padding:7px 12px;text-align:left;width:88px;">Fecha</th>
                    <th style="padding:7px 12px;text-align:left;">Cuenta</th>
                    <th style="padding:7px 12px;text-align:right;width:110px;">Debe</th>
                    <th style="padding:7px 12px;text-align:right;width:110px;">Haber</th>
                </tr>
            </thead>
            <?php foreach ($asientos as $a):
                $sd = array_sum(array_column($a['lineas'], 'debe')); $sh = array_sum(array_column($a['lineas'], 'haber'));
                $totD += $sd; $totH += $sh;
            ?>
            <tbody style="border-top:1px solid var(--gc-line);">
                <tr style="background:var(--gc-brand-soft);">
                    <td style="padding:5px 12px;font-family:monospace;font-weight:700;color:var(--gc-brand);"><?= $a['correlativo'] ?></td>
                    <td style="padding:5px 12px;font-family:monospace;color:var(--gc-label);white-space:nowrap;"><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
                    <td colspan="3" style="padding:5px 12px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($a['glosa']) ?></td>
                </tr>
                <?php foreach ($a['lineas'] as $l): ?>
                <tr>
                    <td></td><td></td>
                    <td style="padding:3px 12px;">
                        <span style="font-family:monospace;font-weight:700;color:var(--gc-label);"><?= $l['codigo'] ?></span>
                        <?= htmlspecialchars($l['nombre']) ?>
                    </td>
                    <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($l['debe']) ?></td>
                    <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($l['haber']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
            <tfoot>
                <tr style="border-top:2px solid var(--gc-line);background:var(--gc-bg);font-weight:700;">
                    <td colspan="3" style="padding:8px 12px;">Total del período</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= number_format($totD, 2) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= number_format($totH, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">📖</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">
            Sin asientos generados para este período
        </div>
        <div style="font-size:13px;">
            Usa el botón "Generar" arriba una vez que las compras y ventas estén clasificadas.
        </div>
    </div>
    <?php endif; ?>
</div>
