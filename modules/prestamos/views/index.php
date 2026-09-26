<?php
$fmt = fn($v) => number_format((float)$v, 2);
$base = "/empresas/{$empresa['id']}/prestamos";
$inp = 'padding:8px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;color:var(--gc-ink);background:var(--gc-surface);width:100%;';
$lbl = 'display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;';
?>
<div class="gc-content gc-w-content">
    <?php $cajaTab = 'prestamos'; require ROOT . '/views/layout/caja_subtabs.php'; ?>

    <?php foreach (['prestamo_ok' => ['var(--gc-pos-soft-2)', 'var(--gc-pos)', 'var(--gc-pos-border)', '✓'], 'prestamo_error' => ['var(--gc-neg-soft)', 'var(--gc-neg)', 'var(--gc-neg-border)', '⚠']] as $k => [$bg, $fg, $bd, $ic]): ?>
    <?php if (!empty($_SESSION[$k])): ?>
    <div style="background:<?= $bg ?>;color:<?= $fg ?>;border:1px solid <?= $bd ?>;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;"><?= $ic ?> <?= htmlspecialchars($_SESSION[$k]) ?></div>
    <?php unset($_SESSION[$k]); endif; endforeach; ?>

    <div style="margin-bottom:14px;">
        <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Préstamos bancarios</div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            El desembolso entra a Caja contra 451 (Obligaciones financieras); cada pago de capital sale contra 451 y el interés contra 673 (que va a gastos financieros, cuenta 96).
            Recibido, pagado y saldo pendiente se calculan de esos movimientos.
        </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;">
        <?php foreach ([['Recibido', $tot['recibido'], 'var(--gc-pos)'], ['Capital pagado', $tot['capital'], 'var(--gc-ink)'], ['Intereses pagados', $tot['intereses'], 'var(--gc-warn)'], ['Saldo pendiente', $tot['saldo'], $tot['saldo'] > 0 ? 'var(--gc-neg)' : 'var(--gc-pos)']] as [$t, $v, $c]): ?>
        <div style="background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:12px;padding:14px 18px;">
            <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;"><?= $t ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $c ?>;font-family:monospace;margin-top:4px;">S/ <?= $fmt($v) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:16px;overflow:hidden;">
        <div style="padding:12px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">+ Registrar un préstamo recibido</div>
        <form method="POST" action="<?= $base ?>/crear" style="padding:16px 20px;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
            <div style="grid-column:span 2;"><label style="<?= $lbl ?>">Entidad *</label><input type="text" name="entidad" required placeholder="Banco / Caja / Financiera" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">Referencia</label><input type="text" name="referencia" placeholder="N° de contrato" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">Fecha de desembolso *</label><input type="date" name="fecha" required value="<?= date('Y-m-d') ?>" style="<?= $inp ?>"></div>
            <div><label style="<?= $lbl ?>">Monto recibido *</label><input type="number" name="monto" step="0.01" min="0.01" required style="<?= $inp ?>text-align:right;font-family:monospace;"></div>
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Registrar</button>
        </form>
    </div>

    <?php if (empty($prestamos)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:40px;text-align:center;color:var(--gc-muted);font-size:14px;">Aún no hay préstamos registrados.</div>
    <?php endif; ?>

    <?php foreach ($prestamos as $p): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);margin-bottom:14px;overflow:hidden;">
        <div style="padding:14px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
            <div>
                <span style="font-weight:700;color:var(--gc-ink);">🏦 <?= htmlspecialchars($p['entidad']) ?></span>
                <span style="font-size:12px;color:var(--gc-muted);"> · desembolso <?= $p['fecha_desembolso'] ?><?= $p['referencia'] ? ' · ' . htmlspecialchars($p['referencia']) : '' ?></span>
            </div>
            <div style="display:flex;gap:18px;font-family:monospace;font-size:13px;flex-wrap:wrap;">
                <span>Recibido <strong>S/ <?= $fmt($p['recibido']) ?></strong></span>
                <span>Capital pagado <strong>S/ <?= $fmt($p['capital_pagado']) ?></strong></span>
                <span>Intereses <strong>S/ <?= $fmt($p['intereses']) ?></strong></span>
                <span style="color:<?= $p['saldo'] > 0.01 ? 'var(--gc-neg)' : 'var(--gc-pos)' ?>;">Saldo <strong>S/ <?= $fmt($p['saldo']) ?></strong></span>
            </div>
        </div>
        <div style="padding:14px 20px;display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
            <form method="POST" action="<?= $base ?>/<?= $p['id'] ?>/pagar" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                <div><label style="<?= $lbl ?>">Fecha</label><input type="date" name="fecha" required value="<?= date('Y-m-d') ?>" style="<?= $inp ?>width:150px;"></div>
                <div><label style="<?= $lbl ?>">Capital</label><input type="number" name="capital" step="0.01" min="0" value="0" style="<?= $inp ?>width:120px;text-align:right;font-family:monospace;"></div>
                <div><label style="<?= $lbl ?>">Interés</label><input type="number" name="interes" step="0.01" min="0" value="0" style="<?= $inp ?>width:120px;text-align:right;font-family:monospace;"></div>
                <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">Registrar pago</button>
            </form>
            <form method="POST" action="<?= $base ?>/<?= $p['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar el préstamo y todos sus movimientos de Caja?');" style="margin-left:auto;">
                <button type="submit" style="background:none;border:none;color:var(--gc-neg);font-size:12px;font-weight:600;cursor:pointer;">Eliminar préstamo</button>
            </form>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;border-top:1px solid var(--gc-line);">
            <?php foreach ($p['movimientos'] as $m): ?>
            <tr style="border-top:1px solid var(--gc-surface-2);">
                <td style="padding:6px 20px;white-space:nowrap;color:var(--gc-muted);"><?= $m['fecha'] ?></td>
                <td style="padding:6px 10px;"><?= htmlspecialchars($m['descripcion']) ?> <span style="font-family:monospace;color:var(--gc-muted);">(<?= $m['cuenta'] ?>)</span></td>
                <td style="padding:6px 20px;text-align:right;font-family:monospace;color:<?= $m['tipo'] === 'ingreso' ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;"><?= $m['tipo'] === 'ingreso' ? '+' : '−' ?> <?= $fmt($m['monto']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
</div>
