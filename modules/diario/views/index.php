<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
?>

<div style="max-width:1100px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">📖 Libro Diario</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <?php foreach (['diario_error' => ['#fef2f2', '#991b1b', '⚠'], 'diario_aviso' => ['#fef3c7', '#92400e', 'ℹ'], 'diario_ok' => ['#f0fdf4', '#166534', '✓']] as $key => [$bg, $fg, $icon]):
        if (empty($_SESSION[$key])) continue; ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        <?= $icon ?> <?= htmlspecialchars($_SESSION[$key]) ?>
    </div>
    <?php unset($_SESSION[$key]); endforeach; ?>

    <!-- Selector de período + generar -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/diario?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/diario/generar">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <button type="submit" style="background:#1e3a8a;color:white;border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                ⚙️ Generar / Regenerar asientos de <?= date('M Y', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01')) ?>
            </button>
        </form>
    </div>

    <?php if ((int)$pendientes['ventas'] > 0 || (int)$pendientes['compras'] > 0): ?>
    <div style="background:#fef3c7;color:#92400e;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ Quedan <?= $pendientes['ventas'] ?> venta(s) y <?= $pendientes['compras'] ?> compra(s) sin clasificar en este período —
        el asiento generado no las incluirá hasta que se
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="color:#92400e;text-decoration:underline;">clasifiquen aquí</a>.
    </div>
    <?php endif; ?>

    <?php if (!empty($asientos)): ?>
        <?php foreach ($asientos as $asiento):
            $sumaDebe  = array_sum(array_column($asiento['lineas'], 'debe'));
            $sumaHaber = array_sum(array_column($asiento['lineas'], 'haber'));
        ?>
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;margin-bottom:16px;overflow:hidden;">
            <div style="padding:12px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <span style="font-family:monospace;font-weight:700;color:#1e3a8a;">Asiento #<?= $asiento['correlativo'] ?></span>
                    <span style="color:#475569;margin-left:10px;"><?= htmlspecialchars($asiento['glosa']) ?></span>
                </div>
                <span style="font-size:12px;color:#94a3b8;"><?= $asiento['fecha'] ?></span>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <th style="padding:8px 20px;text-align:left;color:#64748b;font-weight:700;">Cuenta</th>
                        <th style="padding:8px 20px;text-align:right;color:#64748b;font-weight:700;">Debe</th>
                        <th style="padding:8px 20px;text-align:right;color:#64748b;font-weight:700;">Haber</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($asiento['lineas'] as $l): ?>
                    <tr style="border-top:1px solid #f8fafc;">
                        <td style="padding:7px 20px;">
                            <span style="font-family:monospace;font-weight:700;color:#475569;"><?= $l['codigo'] ?></span>
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
                    <tr style="border-top:2px solid #e2e8f0;font-weight:700;">
                        <td style="padding:8px 20px;color:#475569;">Totales</td>
                        <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= number_format($sumaDebe, 2) ?></td>
                        <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= number_format($sumaHaber, 2) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">📖</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
            Sin asientos generados para este período
        </div>
        <div style="font-size:13px;">
            Usa el botón "Generar" arriba una vez que las compras y ventas estén clasificadas.
        </div>
    </div>
    <?php endif; ?>
</div>
