<?php
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
$fmtFecha = fn($f) => date('d/m/Y', strtotime($f));
$origenLabel = ['compra' => 'Compra', 'venta' => 'Venta', 'planilla' => 'Planilla', 'caja' => 'Caja', 'manual' => 'Manual', 'cierre' => 'Cierre'];
?>

<div class="gc-content gc-w-content">

    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Libro Mayor</div>
            <div style="font-size:12px;color:var(--gc-muted);">Acumulado hasta <?= Periodo::etiqueta($periodo, true) ?></div>
        </div>
        <?php if (!empty($cuentas)): ?>
        <a href="/empresas/<?= $empresa['id'] ?>/mayor/pdf?periodo=<?= $periodo ?>" target="_blank" rel="noopener"
           style="background:var(--gc-surface-2);color:var(--gc-label);padding:5px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">📄 Descargar PDF</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($cuentas)): ?>

    <!-- Índice de cuentas — con decenas de cuentas usadas, saltar
         directo a una es más práctico que hacer scroll por todas. -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:14px 18px;margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
            <?= count($cuentas) ?> cuentas con movimiento
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;">
            <?php foreach ($cuentas as $c): ?>
            <a href="#cta-<?= $c['codigo'] ?>" style="font-family:monospace;font-size:11px;font-weight:700;background:var(--gc-surface-2);color:var(--gc-label);padding:3px 8px;border-radius:6px;text-decoration:none;">
                <?= $c['codigo'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($cuentas as $c): ?>
        <div id="cta-<?= $c['codigo'] ?>" style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;scroll-margin-top:16px;">
            <div style="padding:7px 14px;background:var(--gc-brand-soft);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <div>
                    <span style="font-family:monospace;font-weight:700;color:var(--gc-brand);"><?= $c['codigo'] ?></span>
                    <span style="font-weight:700;color:var(--gc-brand);margin-left:6px;"><?= htmlspecialchars($c['nombre']) ?></span>
                </div>
                <div style="font-size:12px;color:var(--gc-label);font-family:monospace;">
                    Saldo: <strong><?= $fmt(abs($c['saldo'])) ?></strong> <?= $c['saldo'] >= 0 ? '(deudor)' : '(acreedor)' ?>
                </div>
            </div>
            <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead>
                    <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);">
                        <th style="padding:3px 12px;text-align:left;color:var(--gc-muted);font-weight:700;white-space:nowrap;">Fecha</th>
                        <th style="padding:3px 12px;text-align:left;color:var(--gc-muted);font-weight:700;">Glosa</th>
                        <th style="padding:3px 12px;text-align:left;color:var(--gc-muted);font-weight:700;white-space:nowrap;">Origen</th>
                        <th style="padding:3px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Debe</th>
                        <th style="padding:3px 12px;text-align:right;color:var(--gc-muted);font-weight:700;">Haber</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($c['saldo_apertura']): ?>
                    <tr style="border-top:1px solid var(--gc-surface-2);color:var(--gc-muted);font-style:italic;">
                        <td style="padding:3px 12px;" colspan="3">Saldo de apertura</td>
                        <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($c['saldo_apertura']['debe']) ?></td>
                        <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($c['saldo_apertura']['haber']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php foreach ($c['movimientos'] as $m): ?>
                    <tr style="border-top:1px solid var(--gc-surface-2);">
                        <td style="padding:3px 12px;white-space:nowrap;font-family:monospace;"><?= $fmtFecha($m['fecha']) ?></td>
                        <td style="padding:3px 12px;color:var(--gc-label);"><?= htmlspecialchars($m['glosa']) ?></td>
                        <td style="padding:3px 12px;white-space:nowrap;">
                            <span style="background:var(--gc-surface-2);color:var(--gc-label);padding:1px 7px;border-radius:6px;font-size:11px;font-weight:600;"><?= $origenLabel[$m['origen']] ?? $m['origen'] ?></span>
                        </td>
                        <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($m['debe']) ?></td>
                        <td style="padding:3px 12px;text-align:right;font-family:monospace;"><?= $fmt($m['haber']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);">
                        <td style="padding:5px 12px;" colspan="3">Totales</td>
                        <td style="padding:5px 12px;text-align:right;font-family:monospace;"><?= $fmt($c['total_debe']) ?></td>
                        <td style="padding:5px 12px;text-align:right;font-family:monospace;"><?= $fmt($c['total_haber']) ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">📒</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">
            Sin movimientos acumulados hasta este período
        </div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-brand);text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
