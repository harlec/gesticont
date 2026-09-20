<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
$labelTipo = ['activo' => 'Cuentas de Ingreso/Cobro', 'pasivo' => 'Cuentas por Pagar / Obligaciones', 'gasto' => 'Pago Directo de Gasto'];
?>

<div style="max-width:1100px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">💰 Caja y Bancos</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>" style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">← Empresa</a>
    </div>

    <?php if (!empty($_SESSION['caja_error'])): ?>
    <div style="background:#fef2f2;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">⚠ <?= htmlspecialchars($_SESSION['caja_error']) ?></div>
    <?php unset($_SESSION['caja_error']); endif; ?>
    <?php if (isset($_GET['ok'])): ?>
    <div style="background:#f0fdf4;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">✓ Movimiento registrado.</div>
    <?php endif; ?>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Período:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Formulario nuevo movimiento -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:700;color:#475569;margin-bottom:12px;">+ Registrar movimiento</div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/caja" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">TIPO</label>
                <select name="tipo" id="cm-tipo" style="width:100%;padding:7px 6px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                    <option value="ingreso">Ingreso</option>
                    <option value="egreso">Egreso</option>
                </select>
            </div>
            <div style="width:150px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">FECHA</label>
                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required
                       style="width:100%;padding:7px 8px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
            </div>
            <div style="flex:2;min-width:180px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">DESCRIPCIÓN</label>
                <input type="text" name="descripcion" required placeholder="Ej. Cobro factura E001-1"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
            </div>
            <div style="width:130px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">MONTO</label>
                <input type="text" inputmode="decimal" name="monto" required placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="flex:2;min-width:220px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">CUENTA CONTRAPARTIDA</label>
                <select name="cuenta_id" required style="width:100%;padding:7px 6px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
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
            <button type="submit" style="background:#1e3a8a;color:white;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;height:34px;">+ Agregar</button>
        </form>
        <div style="font-size:11px;color:#94a3b8;margin-top:8px;">
            Ingreso: cuenta contrapartida típica 121 (cobro a cliente) o 451 (préstamo recibido).
            Egreso: 421 (pago a proveedor), 4011/4017/4031/4032/417 (pago de tributos/aportes), o cualquier gasto pagado directo (632, 651, etc.).
        </div>
    </div>

    <?php if (!empty($registros)): ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Fecha</th>
                    <th style="padding:10px 16px;text-align:center;color:#64748b;font-weight:700;">Tipo</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Descripción</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Cuenta</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Monto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r): $esIngreso = $r['tipo'] === 'ingreso'; ?>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px 16px;white-space:nowrap;"><?= $r['fecha'] ?></td>
                    <td style="padding:8px 16px;text-align:center;">
                        <span style="background:<?= $esIngreso ? '#dcfce7' : '#fef3c7' ?>;color:<?= $esIngreso ? '#166534' : '#92400e' ?>;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;"><?= strtoupper($r['tipo']) ?></span>
                    </td>
                    <td style="padding:8px 16px;"><?= htmlspecialchars($r['descripcion']) ?></td>
                    <td style="padding:8px 16px;font-family:monospace;font-size:12px;color:#475569;">
                        <?= $r['cuenta_codigo'] ? $r['cuenta_codigo'] . ' - ' . htmlspecialchars($r['cuenta_nombre']) : '—' ?>
                    </td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;color:<?= $esIngreso ? '#166534' : '#991b1b' ?>;"><?= $fmt($r['monto']) ?></td>
                    <td style="padding:8px 16px;text-align:center;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/caja/<?= $r['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar?')">
                            <input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <button type="submit" style="background:none;border:none;color:#991b1b;cursor:pointer;">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;font-weight:700;">
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
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;">
        Sin movimientos registrados en este período.
    </div>
    <?php endif; ?>
</div>
