<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
?>

<div style="max-width:1300px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">⚖️ Balance de Comprobación</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <!-- Selector de período -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/balance?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($balance['filas'])): ?>

    <?php $descuadre = round($balance['totales']['debe'] - $balance['totales']['haber'], 2); ?>
    <?php if ($descuadre != 0): ?>
    <div style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:700;">
        ⚠ El Diario no cuadra: Debe − Haber = S/ <?= number_format($descuadre, 2) ?>. Revisar los asientos del período — no debería pasar esto.
    </div>
    <?php else: ?>
    <div style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ El Diario cuadra: Σ Debe = Σ Haber = S/ <?= number_format($balance['totales']['debe'], 2) ?>
    </div>
    <?php endif; ?>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th rowspan="2" style="padding:8px 12px;text-align:left;color:#64748b;font-weight:700;border-right:1px solid #e2e8f0;">Cuenta</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:#64748b;font-weight:700;border-right:1px solid #e2e8f0;">Sumas del Mayor</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:#64748b;font-weight:700;border-right:1px solid #e2e8f0;">Saldos</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:#64748b;font-weight:700;border-right:1px solid #e2e8f0;">Inventario</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:#64748b;font-weight:700;">Resultados</th>
                </tr>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;">Debe</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;border-right:1px solid #e2e8f0;">Haber</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;">Deudor</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;border-right:1px solid #e2e8f0;">Acreedor</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;">Activo</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;border-right:1px solid #e2e8f0;">Pasivo</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;">Pérdidas</th>
                    <th style="padding:6px 12px;text-align:right;color:#94a3b8;font-weight:700;">Ganancias</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($balance['filas'] as $f): ?>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:6px 12px;border-right:1px solid #f1f5f9;white-space:nowrap;">
                        <span style="font-family:monospace;font-weight:700;color:#475569;"><?= $f['codigo'] ?></span>
                        <?= htmlspecialchars($f['nombre']) ?>
                    </td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;"><?= $fmt($f['debe']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;border-right:1px solid #f1f5f9;"><?= $fmt($f['haber']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;"><?= $fmt($f['deudor']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;border-right:1px solid #f1f5f9;"><?= $fmt($f['acreedor']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:#166534;"><?= $fmt($f['activo']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:#92400e;border-right:1px solid #f1f5f9;"><?= $fmt($f['pasivo']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:#991b1b;"><?= $fmt($f['perdidas']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:#1e3a8a;"><?= $fmt($f['ganancias']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;">
                    <td style="padding:8px 12px;color:#475569;border-right:1px solid #e2e8f0;">Totales</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= $fmt($balance['totales']['debe']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;border-right:1px solid #e2e8f0;"><?= $fmt($balance['totales']['haber']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= $fmt($balance['totales']['deudor']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;border-right:1px solid #e2e8f0;"><?= $fmt($balance['totales']['acreedor']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#166534;"><?= $fmt($balance['totales']['activo']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#92400e;border-right:1px solid #e2e8f0;"><?= $fmt($balance['totales']['pasivo']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#991b1b;"><?= $fmt($balance['totales']['perdidas']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:#1e3a8a;"><?= $fmt($balance['totales']['ganancias']) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">⚖️</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
            Sin movimientos acumulados hasta este período
        </div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:#1e3a8a;text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
