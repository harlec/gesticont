<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
$labelTipo = ['activo' => 'Cuentas de Ingreso/Cobro', 'pasivo' => 'Cuentas por Pagar / Obligaciones', 'gasto' => 'Pago Directo de Gasto'];
?>

<div class="gc-content gc-w-content">

    <?php if (!empty($_SESSION['caja_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">⚠ <?= htmlspecialchars($_SESSION['caja_error']) ?></div>
    <?php unset($_SESSION['caja_error']); endif; ?>
    <?php if (isset($_GET['ok'])): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">✓ Movimiento registrado.</div>
    <?php endif; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Período:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Formulario nuevo movimiento -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:700;color:var(--gc-label);margin-bottom:12px;">+ Registrar movimiento</div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/caja" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <div style="width:110px;">
                <label style="font-size:11px;color:var(--gc-muted);font-weight:700;">TIPO</label>
                <select name="tipo" id="cm-tipo" style="width:100%;padding:7px 6px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;">
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                </select>
            </div>
            <div style="width:150px;">
                <label style="font-size:11px;color:var(--gc-muted);font-weight:700;">FECHA</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required
                       style="width:100%;padding:7px 8px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;">
            </div>
            <div style="flex:2;min-width:180px;">
                <label style="font-size:11px;color:var(--gc-muted);font-weight:700;">DESCRIPCIÓN</label>
                <input type="text" name="descripcion" required placeholder="Ej. Cobro factura E001-1"
                       style="width:100%;padding:7px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;">
            </div>
            <div style="width:130px;">
                <label style="font-size:11px;color:var(--gc-muted);font-weight:700;">MONTO</label>
                <input type="text" inputmode="decimal" name="monto" required placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="flex:2;min-width:220px;">
                <label style="font-size:11px;color:var(--gc-muted);font-weight:700;">CUENTA CONTRAPARTIDA</label>
                <select name="cuenta_id" required style="width:100%;padding:7px 6px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;">
                    <option value="">Seleccionar…</option>
                    <?php foreach ($cuentasPorTipo as $tipo => $lista): if (empty($lista)) continue; ?>
                    <optgroup label="<?= $labelTipo[$tipo] ?>">
                        <?php foreach ($lista as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> - <?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;height:34px;">+ Agregar</button>
        </form>
        <div style="font-size:11px;color:var(--gc-muted);margin-top:8px;">
            Ingreso: cuenta contrapartida típica 121 (cobro a cliente) o 451 (préstamo recibido).
            Egreso: 421 (pago a proveedor), 4011/4017/4031/4032/417 (pago de tributos/aportes), o cualquier gasto pagado directo (632, 651, etc.).
        </div>
    </div>

    <?php if (!empty($registros)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;">Fecha</th>
                    <th style="padding:10px 16px;text-align:center;color:var(--gc-label-2);font-weight:700;">Tipo</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;">Descripción</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;">Cuenta</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;">Monto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): $esIngreso = $r['tipo'] === 'ingreso'; ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 16px;white-space:nowrap;"><?= $r['fecha'] ?></td>
                    <td style="padding:8px 16px;text-align:center;">
                        <span style="background:<?= $esIngreso ? 'var(--gc-pos-soft)' : 'var(--gc-warn-soft)' ?>;color:<?= $esIngreso ? 'var(--gc-pos)' : 'var(--gc-warn)' ?>;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;"><?= strtoupper($r['tipo']) ?></span>
                    </td>
                    <td style="padding:8px 16px;"><?= htmlspecialchars($r['descripcion']) ?></td>
                    <td style="padding:8px 16px;font-family:monospace;font-size:12px;color:var(--gc-label);">
                        <?= $r['cuenta_codigo'] ? $r['cuenta_codigo'] . ' - ' . htmlspecialchars($r['cuenta_nombre']) : '—' ?>
                    </td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;color:<?= $esIngreso ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;"><?= $fmt($r['monto']) ?></td>
                    <td style="padding:8px 16px;text-align:center;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/caja/<?= $r['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar?')">
                            <input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <button type="submit" style="background:none;border:none;color:var(--gc-neg);cursor:pointer;">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:var(--gc-bg);border-top:2px solid var(--gc-line);font-weight:700;">
                    <td colspan="4" style="padding:10px 16px;">Totales</td>
                    <td colspan="2" style="padding:10px 16px;text-align:right;font-family:monospace;">
                        Ingresos: <?= $fmt($totales['ingreso']) ?> · Egresos: <?= $fmt($totales['egreso']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:40px;text-align:center;color:var(--gc-muted);">
        Sin movimientos registrados en este período.
    </div>
    <?php endif; ?>
</div>
