<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
?>

<div style="max-width:1300px;margin:0 auto;">

    <!-- Selector de período -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/balance?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($balance['filas'])): ?>

    <?php $descuadre = round($balance['totales']['debe'] - $balance['totales']['haber'], 2); ?>
    <?php if ($descuadre != 0): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:700;">
        ⚠ El Diario no cuadra: Debe − Haber = S/ <?= number_format($descuadre, 2) ?>. Revisar los asientos del período — no debería pasar esto.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ El Diario cuadra: Σ Debe = Σ Haber = S/ <?= number_format($balance['totales']['debe'], 2) ?>
    </div>
    <?php endif; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <th rowspan="2" style="padding:8px 12px;text-align:left;color:var(--gc-label-2);font-weight:700;border-right:1px solid var(--gc-line);">Cuenta</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:var(--gc-label-2);font-weight:700;border-right:1px solid var(--gc-line);">Sumas del Mayor</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:var(--gc-label-2);font-weight:700;border-right:1px solid var(--gc-line);">Saldos</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:var(--gc-label-2);font-weight:700;border-right:1px solid var(--gc-line);">Inventario</th>
                    <th colspan="2" style="padding:6px 12px;text-align:center;color:var(--gc-label-2);font-weight:700;">Resultados</th>
                </tr>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Debe</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;border-right:1px solid var(--gc-line);">Haber</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Deudor</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;border-right:1px solid var(--gc-line);">Acreedor</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Activo</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;border-right:1px solid var(--gc-line);">Pasivo</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Pérdidas</th>
                    <th style="padding:6px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Ganancias</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($balance['filas'] as $f): ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:6px 12px;border-right:1px solid var(--gc-surface-2);white-space:nowrap;">
                        <span style="font-family:monospace;font-weight:700;color:var(--gc-label);"><?= $f['codigo'] ?></span>
                        <?= htmlspecialchars($f['nombre']) ?>
                    </td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;"><?= $fmt($f['debe']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;border-right:1px solid var(--gc-surface-2);"><?= $fmt($f['haber']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;"><?= $fmt($f['deudor']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;border-right:1px solid var(--gc-surface-2);"><?= $fmt($f['acreedor']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:var(--gc-pos);"><?= $fmt($f['activo']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:var(--gc-warn);border-right:1px solid var(--gc-surface-2);"><?= $fmt($f['pasivo']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:var(--gc-neg);"><?= $fmt($f['perdidas']) ?></td>
                    <td style="padding:6px 12px;text-align:right;font-family:monospace;color:var(--gc-brand);"><?= $fmt($f['ganancias']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);">
                    <td style="padding:8px 12px;color:var(--gc-label);border-right:1px solid var(--gc-line);">Totales</td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= $fmt($balance['totales']['debe']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;border-right:1px solid var(--gc-line);"><?= $fmt($balance['totales']['haber']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;"><?= $fmt($balance['totales']['deudor']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;border-right:1px solid var(--gc-line);"><?= $fmt($balance['totales']['acreedor']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:var(--gc-pos);"><?= $fmt($balance['totales']['activo']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:var(--gc-warn);border-right:1px solid var(--gc-line);"><?= $fmt($balance['totales']['pasivo']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:var(--gc-neg);"><?= $fmt($balance['totales']['perdidas']) ?></td>
                    <td style="padding:8px 12px;text-align:right;font-family:monospace;color:var(--gc-brand);"><?= $fmt($balance['totales']['ganancias']) ?></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">⚖️</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">
            Sin movimientos acumulados hasta este período
        </div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-brand);text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
