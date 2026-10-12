<?php
$fmt = fn($v) => number_format((float)$v, 2);
$corteTxt = date('d/m/Y', strtotime($r['corte']));
?>
<div class="gc-content gc-w-content">
<div style="max-width:960px;margin:0 auto;">

    <div class="no-print" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <div>
            <div style="font-size:18px;font-weight:700;color:var(--gc-ink);"><?= $titulo ?></div>
            <div style="font-size:13px;color:var(--gc-muted);">Saldo pendiente al <?= $corteTxt ?> · <?= count($r['terceros']) ?> <?= strtolower($tercero) ?>(es)</div>
        </div>
        <a href="<?= $rutaBase ?>/pdf?periodo=<?= $periodo ?>" target="_blank" rel="noopener"
           style="background:var(--gc-surface-2);color:var(--gc-label);padding:9px 18px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">📄 Descargar PDF</a>
    </div>

    <!-- Resumen y antigüedad -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px;">
        <div style="background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:10px;padding:12px 14px;">
            <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Total pendiente</div>
            <div style="font-size:20px;font-weight:700;color:var(--gc-ink);font-family:monospace;">S/ <?= $fmt($r['total']) ?></div>
        </div>
        <?php foreach ($r['tramos'] as $nom => $v): ?>
        <div style="background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:10px;padding:12px 14px;">
            <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;"><?= $nom ?> días</div>
            <div style="font-size:16px;font-weight:700;color:<?= $nom === 'Más de 90' && $v > 0 ? 'var(--gc-neg)' : 'var(--gc-ink)' ?>;font-family:monospace;">S/ <?= $fmt($v) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($r['libro'] !== null): $ok = abs($r['sin_detalle']) < 0.02; ?>
    <div style="background:<?= $ok ? 'var(--gc-pos-soft-2)' : 'var(--gc-warn-soft)' ?>;color:<?= $ok ? 'var(--gc-pos)' : 'var(--gc-warn)' ?>;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;font-weight:600;">
        <?php if ($ok): ?>✓ Cuadra con el Mayor: la cuenta <?= $r['cuenta'] ?> tiene S/ <?= $fmt($r['libro']) ?>, igual al detalle.
        <?php else: ?>La cuenta <?= $r['cuenta'] ?> del Mayor tiene S/ <?= $fmt($r['libro']) ?>; el detalle por comprobante suma S/ <?= $fmt($r['total']) ?>.
            Diferencia de <strong>S/ <?= $fmt($r['sin_detalle']) ?></strong> sin comprobante detrás (saldo de apertura u otros asientos).<?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($r['terceros'])): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:50px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:36px;margin-bottom:10px;">✅</div>
        Sin saldos pendientes a esta fecha.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);color:var(--gc-muted);">
                    <th style="padding:8px 14px;text-align:left;"><?= $tercero ?> / Documento</th>
                    <th style="padding:8px 14px;text-align:left;width:96px;">Fecha</th>
                    <th style="padding:8px 14px;text-align:right;width:60px;">Días</th>
                    <th style="padding:8px 14px;text-align:right;width:110px;">Total</th>
                    <th style="padding:8px 14px;text-align:right;width:110px;"><?= $esVenta ? 'Cobrado' : 'Pagado' ?></th>
                    <th style="padding:8px 14px;text-align:right;width:110px;">Saldo</th>
                </tr>
            </thead>
            <?php foreach ($r['terceros'] as $t): ?>
            <tbody style="border-top:1px solid var(--gc-line);">
                <tr style="background:var(--gc-brand-soft);">
                    <td colspan="5" style="padding:7px 14px;">
                        <span style="font-family:monospace;color:var(--gc-brand);font-weight:700;"><?= htmlspecialchars($t['doc']) ?></span>
                        <span style="font-weight:600;color:var(--gc-ink);margin-left:6px;"><?= htmlspecialchars($t['nombre']) ?></span>
                    </td>
                    <td style="padding:7px 14px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($t['saldo']) ?></td>
                </tr>
                <?php foreach ($t['docs'] as $d): ?>
                <tr>
                    <td style="padding:5px 14px 5px 28px;font-family:monospace;color:var(--gc-label);"><?= $d['tipo'] === '07' ? '↩ NC ' : '' ?><?= htmlspecialchars($d['numero']) ?></td>
                    <td style="padding:5px 14px;font-family:monospace;color:var(--gc-label);white-space:nowrap;"><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;color:<?= $d['dias'] > 90 ? 'var(--gc-neg)' : 'var(--gc-label)' ?>;"><?= $d['dias'] ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;"><?= $fmt($d['total']) ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;color:var(--gc-pos);"><?= $d['pagado'] > 0 ? $fmt($d['pagado']) : '—' ?></td>
                    <td style="padding:5px 14px;text-align:right;font-family:monospace;font-weight:600;"><?= $fmt($d['saldo']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php endforeach; ?>
            <tfoot>
                <tr style="border-top:2px solid var(--gc-line);background:var(--gc-bg);font-weight:700;">
                    <td colspan="5" style="padding:9px 14px;">Total de comprobantes con saldo</td>
                    <td style="padding:9px 14px;text-align:right;font-family:monospace;"><?= $fmt($r['total']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <div style="font-size:12px;color:var(--gc-muted);margin-top:10px;">
        Solo comprobantes clasificados. Saldo = total − lo <?= $esVenta ? 'cobrado' : 'pagado' ?> hasta el cierre del período. Las notas de crédito (↩) restan del saldo.
        Para registrar un <?= $esVenta ? 'cobro' : 'pago' ?> parcial usa <a href="/empresas/<?= $empresa['id'] ?>/cobranzas?origen=<?= $origen ?>" style="color:var(--gc-brand);text-decoration:underline;">Cobros y pagos</a>.
    </div>
</div>
</div>
