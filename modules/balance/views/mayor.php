<?php
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
$fmtFecha = fn($f) => date('d/m/Y', strtotime($f));
?>

<div class="gc-content gc-w-content">
<div style="max-width:960px;margin:0 auto;">

    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <div style="font-size:18px;font-weight:700;color:var(--gc-ink);">Libro Mayor</div>
            <div style="font-size:13px;color:var(--gc-muted);">Acumulado hasta <?= Periodo::etiqueta($periodo, true) ?></div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>/mayor/pdf?periodo=<?= $periodo ?>" target="_blank" rel="noopener"
           style="background:var(--gc-surface-2);color:var(--gc-label);padding:9px 18px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">📄 Descargar PDF</a>
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
            <a href="#cta-<?= $c['codigo'] ?>" style="font-family:monospace;font-size:12px;font-weight:700;background:var(--gc-surface-2);color:var(--gc-label);padding:3px 8px;border-radius:6px;text-decoration:none;">
                <?= $c['codigo'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);color:var(--gc-muted);">
                    <th style="padding:8px 14px;text-align:left;width:96px;">Fecha</th>
                    <th style="padding:8px 14px;text-align:left;">Glosa</th>
                    <th style="padding:8px 14px;text-align:right;width:110px;">Debe</th>
                    <th style="padding:8px 14px;text-align:right;width:110px;">Haber</th>
                    <th style="padding:8px 14px;text-align:right;width:110px;">Saldo</th>
                </tr>
            </thead>
            <?php foreach ($cuentas as $c):
                $acum = $c['saldo_apertura'] ? $c['saldo_apertura']['debe'] - $c['saldo_apertura']['haber'] : 0;
            ?>
            <tbody id="cta-<?= $c['codigo'] ?>" style="border-top:1px solid var(--gc-line);scroll-margin-top:70px;">
                <tr style="background:var(--gc-brand-soft);">
                    <td colspan="5" style="padding:7px 14px;">
                        <span style="font-family:monospace;font-weight:700;color:var(--gc-brand);"><?= $c['codigo'] ?></span>
                        <span style="font-weight:600;color:var(--gc-ink);margin-left:6px;"><?= htmlspecialchars($c['nombre']) ?></span>
                    </td>
                </tr>
                <?php if ($c['saldo_apertura']): ?>
                <tr style="color:var(--gc-muted);font-style:italic;">
                    <td></td>
                    <td style="padding:5px 14px;">Saldo de apertura</td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= $fmt($c['saldo_apertura']['debe']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= $fmt($c['saldo_apertura']['haber']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= number_format($acum, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($c['movimientos'] as $m): $acum += $m['debe'] - $m['haber']; ?>
                <tr>
                    <td style="padding:5px 14px;white-space:nowrap;font-family:monospace;color:var(--gc-label);"><?= $fmtFecha($m['fecha']) ?></td>
                    <td style="padding:5px 14px;"><?= htmlspecialchars($m['glosa']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= $fmt($m['debe']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= $fmt($m['haber']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;font-weight:600;"><?= number_format($acum, 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="font-weight:700;border-top:1px solid var(--gc-line);">
                    <td></td>
                    <td style="padding:6px 14px;color:var(--gc-label);">Totales · saldo <?= $c['saldo'] >= 0 ? 'deudor' : 'acreedor' ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= $fmt($c['total_debe']) ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= $fmt($c['total_haber']) ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= number_format(abs($c['saldo']), 2) ?></td>
                </tr>
            </tbody>
            <?php endforeach; ?>
        </table>
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
</div>
