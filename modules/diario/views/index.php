<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
?>

<div class="gc-content gc-w-content">

    <?php foreach (['diario_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', '⚠'], 'diario_aviso' => ['var(--gc-warn-soft)', 'var(--gc-warn)', 'ℹ'], 'diario_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', '✓']] as $key => [$bg, $fg, $icon]):
        if (empty($_SESSION[$key])) continue; ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        <?= $icon ?> <?= htmlspecialchars($_SESSION[$key]) ?>
    </div>
    <?php unset($_SESSION[$key]); endforeach; ?>

    <!-- Selector de período + generar -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = Periodo::etiqueta($p);
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/diario?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/diario/generar">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                ⚙️ Generar / Regenerar asientos de <?= Periodo::etiqueta($periodo) ?>
            </button>
        </form>
    </div>

    <?php if ((int)$pendientes['ventas'] > 0 || (int)$pendientes['compras'] > 0): ?>
    <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ Quedan <?= $pendientes['ventas'] ?> venta(s) y <?= $pendientes['compras'] ?> compra(s) sin clasificar en este período —
        el asiento generado no las incluirá hasta que se
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="color:var(--gc-warn);text-decoration:underline;">clasifiquen aquí</a>.
    </div>
    <?php endif; ?>

    <?php if (!empty($asientos)): ?>
        <?php foreach ($asientos as $asiento):
            $sumaDebe  = array_sum(array_column($asiento['lineas'], 'debe'));
            $sumaHaber = array_sum(array_column($asiento['lineas'], 'haber'));
        ?>
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:16px;overflow:hidden;">
            <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <span style="font-family:monospace;font-weight:700;color:var(--gc-brand);">Asiento #<?= $asiento['correlativo'] ?></span>
                    <span style="color:var(--gc-label);margin-left:10px;"><?= htmlspecialchars($asiento['glosa']) ?></span>
                </div>
                <span style="font-size:12px;color:var(--gc-muted);"><?= $asiento['fecha'] ?></span>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid var(--gc-surface-2);">
                        <th style="padding:8px 20px;text-align:left;color:var(--gc-label-2);font-weight:700;">Cuenta</th>
                        <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Debe</th>
                        <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Haber</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($asiento['lineas'] as $l): ?>
                    <tr style="border-top:1px solid var(--gc-bg);">
                        <td style="padding:7px 20px;">
                            <span style="font-family:monospace;font-weight:700;color:var(--gc-label);"><?= $l['codigo'] ?></span>
                            <?= htmlspecialchars($l['nombre']) ?>
                        </td>
                        <td style="padding:7px 20px;text-align:right;font-family:monospace;">
                            <?= $l['debe'] != 0 ? number_format((float)$l['debe'], 2) : '—' ?>
                        </td>
                        <td style="padding:7px 20px;text-align:right;font-family:monospace;">
                            <?= $l['haber'] != 0 ? number_format((float)$l['haber'], 2) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--gc-line);font-weight:700;">
                        <td style="padding:8px 20px;color:var(--gc-label);">Totales</td>
                        <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= number_format($sumaDebe, 2) ?></td>
                        <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= number_format($sumaHaber, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endforeach; ?>
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
