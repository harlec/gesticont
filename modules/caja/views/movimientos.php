<?php
/** Caja y Bancos · Movimientos (rediseño). Variables: $empresa, $periodo, $registros, $totales, $cuentasPorTipo. */
$id = (int)$empresa['id'];
$labelTipo = ['activo' => 'Cuentas de ingreso o cobro', 'pasivo' => 'Cuentas por pagar y obligaciones', 'gasto' => 'Pago directo de gasto'];
$cajaTab = 'movimientos'; require ROOT . '/views/layout/caja_subtabs.php';
$S = fn($v) => 'S/ ' . number_format((float)$v, 2);
?>
<?php if (!empty($_SESSION['caja_error'])): ?><div class="gc-alert neg" role="alert"><span class="msg"><?= htmlspecialchars($_SESSION['caja_error']) ?></span></div><?php unset($_SESSION['caja_error']); endif; ?>
<?php if (isset($_GET['ok'])): ?><div class="gc-alert ok" role="status"><span class="msg">Movimiento registrado.</span></div><?php endif; ?>

<section class="gc-kpis">
    <div class="gc-kpi ok"><div class="k">Ingresos de <?= Periodo::etiqueta($periodo, true) ?></div><div class="v num"><?= $S($totales['ingreso']) ?></div></div>
    <div class="gc-kpi neg"><div class="k">Egresos</div><div class="v num"><?= $S($totales['egreso']) ?></div></div>
    <div class="gc-kpi"><div class="k">Diferencia del mes</div><div class="v num"><?= $S(abs($totales['ingreso'] - $totales['egreso'])) ?></div><div class="s"><?= $totales['ingreso'] >= $totales['egreso'] ? 'entró más de lo que salió' : 'salió más de lo que entró' ?></div></div>
</section>

<section class="gc-card pad gc-stack" style="gap:14px;">
    <h2 class="gc-h2" style="margin:0;">Registrar un movimiento</h2>
    <form method="POST" action="/empresas/<?= $id ?>/caja" class="gc-row" style="gap:14px;align-items:flex-end;">
        <input type="hidden" name="periodo" value="<?= $periodo ?>">
        <div class="gc-field" style="width:140px;"><label class="gc-label" for="cm-tipo">Tipo</label>
            <select name="tipo" id="cm-tipo" class="gc-select"><option value="ingreso">Ingreso</option><option value="egreso">Egreso</option></select></div>
        <div class="gc-field" style="width:170px;"><label class="gc-label" for="cm-fecha">Fecha</label>
            <input type="date" id="cm-fecha" name="fecha" value="<?= date('Y-m-d') ?>" required class="gc-input"></div>
        <div class="gc-field" style="flex:2;min-width:220px;"><label class="gc-label" for="cm-desc">Descripción</label>
            <input type="text" id="cm-desc" name="descripcion" required placeholder="Ej. Cobro factura E001-1" class="gc-input"></div>
        <div class="gc-field" style="width:150px;"><label class="gc-label" for="cm-monto">Monto (S/)</label>
            <input type="text" id="cm-monto" inputmode="decimal" name="monto" required placeholder="0.00" class="gc-input" style="text-align:right;"></div>
        <div class="gc-field" style="flex:2;min-width:260px;"><label class="gc-label" for="cm-cuenta">Cuenta contrapartida</label>
            <select name="cuenta_id" id="cm-cuenta" required class="gc-select"><option value="">Elegir…</option>
            <?php foreach ($cuentasPorTipo as $tipo => $lista): if (empty($lista)) continue; ?>
                <optgroup label="<?= $labelTipo[$tipo] ?>"><?php foreach ($lista as $c): ?><option value="<?= $c['id'] ?>"><?= $c['codigo'] ?> · <?= htmlspecialchars($c['nombre']) ?></option><?php endforeach; ?></optgroup>
            <?php endforeach; ?></select></div>
        <button type="submit" class="gc-btn gc-btn-primary">Agregar</button>
    </form>
    <div class="gc-sub">Ingreso: normalmente la 121 (cobro a un cliente) o la 451 (préstamo recibido). Egreso: la 421 (pago a un proveedor), tributos y aportes (4011, 4017, 4031, 4032, 417) o un gasto pagado directo (632, 651…).</div>
</section>

<?php if (empty($registros)): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin movimientos en este período</b>Los cobros y pagos que registres en <a href="/empresas/<?= $id ?>/cobranzas">Cobros y pagos</a> también aparecen aquí.</div></div>
<?php else: ?>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:720px;">
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Cuenta</th><th class="r">Monto</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($registros as $r): $ing = $r['tipo'] === 'ingreso'; ?>
        <tr>
            <td class="mono" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td><span class="gc-chip <?= $ing ? 'ok' : 'warn' ?>"><?= $ing ? 'Ingreso' : 'Egreso' ?></span></td>
            <td><?= htmlspecialchars($r['descripcion']) ?></td>
            <td><?= $r['cuenta_codigo'] ? '<span class="mono">' . $r['cuenta_codigo'] . '</span> ' . htmlspecialchars($r['cuenta_nombre']) : '—' ?></td>
            <td class="r num" style="font-weight:700;color:<?= $ing ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;"><?= number_format((float)$r['monto'], 2) ?></td>
            <td class="r"><form method="POST" action="/empresas/<?= $id ?>/caja/<?= $r['id'] ?>/eliminar" onsubmit="return confirm('¿Quitar este movimiento?')" style="margin:0;">
                <input type="hidden" name="periodo" value="<?= $periodo ?>"><button type="submit" class="gc-btn">Quitar</button></form></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="4">Totales del mes</td><td class="r num" colspan="2">Ingresos <?= number_format($totales['ingreso'], 2) ?> · Egresos <?= number_format($totales['egreso'], 2) ?></td></tr></tfoot>
</table>
</section>
<?php endif; ?>
