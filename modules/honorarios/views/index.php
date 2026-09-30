<?php
$fmt = fn($v) => number_format((float)$v, 2);
$base = "/empresas/{$empresa['id']}/honorarios";
$periodos = [];
for ($i = 0; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("first day of -{$i} month"));
if (!in_array($periodo, $periodos, true)) array_unshift($periodos, $periodo);
$inp = 'padding:8px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);width:100%;';
$lbl = 'display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;';
?>
<div class="gc-content gc-w-content">

    <?php foreach (['honorario_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', 'var(--gc-pos-border)', '✓'], 'honorario_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', 'var(--gc-neg-border)', '⚠']] as $k => [$bg, $fg, $bd, $ic]): ?>
    <?php if (!empty($_SESSION[$k])): ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border:1px solid <?= $bd ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= $ic ?> <?= htmlspecialchars($_SESSION[$k]) ?></div>
    <?php unset($_SESSION[$k]); endif; endforeach; ?>

    <div style="margin-bottom:14px;">
        <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Honorarios (recibos de 4ta categoría)</div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            Cada recibo se provisiona Debe 632 / Haber 424 (neto por pagar) + Haber 4017 (retención) al generar los asientos del mes.
            Se asume <strong>pagado</strong>: el pago del neto entra a Caja. La retención se entrega a SUNAT aparte, como egreso de Caja contra 4017.
        </div>
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:14px 20px;margin-bottom:16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <label style="font-size:12px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Período</label>
        <?php foreach ($periodos as $p): $act = $p === $periodo; ?>
        <a href="<?= $base ?>?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= Periodo::etiqueta($p) ?></a>
        <?php endforeach; ?>
    </div>

    <!-- Nuevo recibo -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:16px;overflow:hidden;">
        <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">+ Registrar recibo por honorarios</div>
        <form method="POST" action="<?= $base ?>/crear" style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end;">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <div><label style="<?= $lbl ?>">Fecha</label><input type="date" name="fecha" required value="<?= date('Y-m-d') ?>" style="<?= $inp ?>"></div>
            <div style="grid-column:span 2;"><label style="<?= $lbl ?>">Prestador *</label><input type="text" name="prestador_nombre" required placeholder="Nombre o razón social" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">RUC / DNI</label><input type="text" name="prestador_doc" maxlength="15" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">Recibo (E001-123)</label><input type="text" name="comprobante" maxlength="30" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">Monto bruto *</label><input type="number" name="monto" id="hon-monto" step="0.01" min="0.01" required style="<?= $inp ?>text-align:right;font-family:monospace;"></div>
            <div><label style="<?= $lbl ?>">Retención 8 %</label><input type="number" name="retencion" id="hon-ret" step="0.01" min="0" placeholder="auto" style="<?= $inp ?>text-align:right;font-family:monospace;"></div>
            <div><label style="<?= $lbl ?>">Concepto</label><input type="text" name="descripcion" maxlength="200" style="<?= $inp ?>"></div>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--gc-label);padding-bottom:8px;">
                <input type="checkbox" name="pagado" checked style="width:15px;height:15px;"> Ya se pagó
            </label>
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Registrar</button>
        </form>
        <div style="padding:0 20px 14px;font-size:11px;color:var(--gc-muted);">
            La retención se calcula sola (8 %) cuando el recibo supera S/ 1,500; déjala vacía para eso, o escríbela (0 si el prestador tiene suspensión de retenciones).
        </div>
    </div>

    <!-- Recibos del mes -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;margin-bottom:16px;">
        <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">Recibos de <?= Periodo::etiqueta($periodo) ?></div>
        <?php if (empty($recibos)): ?>
        <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:13px;">No hay recibos en este período.</div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="border-bottom:1px solid var(--gc-line);color:var(--gc-label-2);">
                <th style="padding:8px 14px;text-align:left;">Fecha</th><th style="padding:8px 14px;text-align:left;">Prestador</th><th style="padding:8px 14px;text-align:left;">Recibo</th>
                <th style="padding:8px 14px;text-align:right;">Monto</th><th style="padding:8px 14px;text-align:right;">Retención</th><th style="padding:8px 14px;text-align:right;">Neto</th>
                <th style="padding:8px 14px;text-align:center;">Estado</th><th style="padding:8px 14px;"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($recibos as $r): ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 14px;white-space:nowrap;"><?= $r['fecha'] ?></td>
                    <td style="padding:8px 14px;"><?= htmlspecialchars($r['prestador_nombre']) ?>
                        <?php if ($r['prestador_doc']): ?><div style="font-family:monospace;font-size:11px;color:var(--gc-muted);"><?= htmlspecialchars($r['prestador_doc']) ?></div><?php endif; ?></td>
                    <td style="padding:8px 14px;font-family:monospace;font-size:12px;"><?= htmlspecialchars((string)$r['comprobante']) ?></td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($r['monto']) ?></td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;color:var(--gc-neg);"><?= $fmt($r['retencion']) ?></td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt(HonorarioService::neto($r)) ?></td>
                    <td style="padding:8px 14px;text-align:center;">
                        <span style="padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;background:<?= $r['pagado'] ? 'var(--gc-pos-soft)' : 'var(--gc-warn-soft)' ?>;color:<?= $r['pagado'] ? 'var(--gc-pos)' : 'var(--gc-warn)' ?>;">
                            <?= $r['pagado'] ? 'Pagado' : 'Pendiente' ?></span>
                    </td>
                    <td style="padding:8px 14px;white-space:nowrap;">
                        <?php if ($r['pagado']): ?>
                        <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/pendiente" style="display:inline;"><input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <button type="submit" style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">Pasar a pendiente</button></form>
                        <?php else: ?>
                        <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/pagar" style="display:inline;"><input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" style="padding:4px 6px;border:1px solid var(--gc-line);border-radius:6px;font-size:12px;">
                            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:5px 10px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">Registrar pago</button></form>
                        <?php endif; ?>
                        <form method="POST" action="<?= $base ?>/<?= $r['id'] ?>/eliminar" style="display:inline;" onsubmit="return confirm('¿Eliminar este recibo?');"><input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <button type="submit" style="background:none;border:none;color:var(--gc-neg);cursor:pointer;font-size:13px;">✕</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);">
                <td colspan="3" style="padding:8px 14px;">Total del mes</td>
                <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($tot['monto']) ?></td>
                <td style="padding:8px 14px;text-align:right;font-family:monospace;color:var(--gc-neg);"><?= $fmt($tot['retencion']) ?></td>
                <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($tot['neto']) ?></td>
                <td colspan="2" style="padding:8px 14px;font-size:12px;color:var(--gc-warn);"><?= $tot['pendiente'] > 0 ? 'Por pagar: S/ ' . $fmt($tot['pendiente']) : '' ?></td>
            </tr></tfoot>
        </table>
        <?php endif; ?>
    </div>

    <!-- Resumen del año (hoja HONORARIO) -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">Honorarios de enero a diciembre <?= $anio ?></div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead><tr style="border-bottom:1px solid var(--gc-line);color:var(--gc-label-2);">
                <th style="padding:8px 14px;text-align:left;">Mes</th><th style="padding:8px 14px;text-align:right;">Recibos</th>
                <th style="padding:8px 14px;text-align:right;">Monto</th><th style="padding:8px 14px;text-align:right;">Retención</th><th style="padding:8px 14px;text-align:right;">Total (neto)</th>
            </tr></thead>
            <tbody>
            <?php $ta = ['n' => 0, 'm' => 0.0, 'r' => 0.0];
            for ($m = 1; $m <= 12; $m++):
                $p = sprintf('%s%02d', $anio, $m); $x = $resumenAnual[$p] ?? null;
                if ($x) { $ta['n'] += $x['n']; $ta['m'] += $x['monto']; $ta['r'] += $x['retencion']; } ?>
                <tr style="border-top:1px solid var(--gc-surface-2);<?= $p === $periodo ? 'background:var(--gc-brand-soft);' : '' ?>">
                    <td style="padding:6px 14px;"><a href="<?= $base ?>?periodo=<?= $p ?>" style="color:var(--gc-ink);text-decoration:none;"><?= Periodo::etiqueta($p, true) ?></a></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= $x ? (int)$x['n'] : '—' ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= $x ? $fmt($x['monto']) : '—' ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;"><?= $x ? $fmt($x['retencion']) : '—' ?></td>
                    <td style="padding:6px 14px;text-align:right;font-family:monospace;font-weight:700;"><?= $x ? $fmt($x['monto'] - $x['retencion']) : '—' ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot><tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);">
                <td style="padding:8px 14px;">Total año</td><td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $ta['n'] ?></td>
                <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($ta['m']) ?></td><td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($ta['r']) ?></td>
                <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($ta['m'] - $ta['r']) ?></td>
            </tr></tfoot>
        </table>
    </div>
</div>
