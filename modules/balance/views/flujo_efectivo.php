<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:750px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:var(--gc-ink);">💵 Estado de Flujo de Efectivo</div>
            <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?></div>
        </div>
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="/empresas/<?= $empresa['id'] ?>/flujo-efectivo?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>

    <?php if (abs($resultado['descuadre']) > 0.01): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ El Saldo Final calculado (S/ <?= $fmt($resultado['saldo_final_calculado']) ?>) no coincide con el saldo real de Caja y Bancos en el Balance (S/ <?= $fmt($resultado['saldo_real_caja']) ?>) —
        diferencia S/ <?= $fmt($resultado['descuadre']) ?>. Regenera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-neg);text-decoration:underline;">Libro Diario</a> o revisa movimientos de Caja sin cuenta asignada.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Saldo Final de Efectivo = saldo real de Caja y Bancos = S/ <?= $fmt($resultado['saldo_final_calculado']) ?>
    </div>
    <?php endif; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <tbody>
                <tr style="background:var(--gc-bg);"><td colspan="2" style="padding:8px 20px;font-weight:700;color:var(--gc-brand);">ACTIVIDAD DE OPERACIÓN</td></tr>
                <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Cobranza a Clientes</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['cobranza_clientes']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:var(--gc-label);">(−) Pago a Proveedores</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pago_proveedores']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:var(--gc-label);">(−) Pago a Trabajadores</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pago_trabajadores']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:var(--gc-label);">Otros cobros/pagos operativos (tributos, ESSALUD, ONP, AFP, etc.)</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['otros_operacion']) ?></td></tr>
                <tr style="border-top:1px solid var(--gc-line);font-weight:700;"><td style="padding:8px 20px;">Flujo de Operación</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_operacion']) ?></td></tr>

                <tr style="background:var(--gc-bg);"><td colspan="2" style="padding:8px 20px;font-weight:700;color:var(--gc-brand);">ACTIVIDAD DE INVERSIÓN</td></tr>
                <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">(−) Compra de Inmueble, Maquinaria y Equipo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['compra_activo_fijo']) ?></td></tr>
                <tr style="border-top:1px solid var(--gc-line);font-weight:700;"><td style="padding:8px 20px;">Flujo de Inversión</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_inversion']) ?></td></tr>

                <tr style="background:var(--gc-bg);"><td colspan="2" style="padding:8px 20px;font-weight:700;color:var(--gc-brand);">ACTIVIDAD DE FINANCIAMIENTO</td></tr>
                <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Préstamos Recibidos</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['prestamos_recibidos']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:var(--gc-label);">(−) Amortización de Obligaciones</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['amortizacion']) ?></td></tr>
                <tr style="border-top:1px solid var(--gc-line);font-weight:700;"><td style="padding:8px 20px;">Flujo de Financiamiento</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_financiamiento']) ?></td></tr>

                <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);"><td style="padding:10px 20px;">Aumento/Disminución Neta de Efectivo</td><td style="padding:10px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['aumento_neto']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:var(--gc-label);">Saldo Inicial de Efectivo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['saldo_inicial']) ?></td></tr>
                <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);"><td style="padding:10px 20px;">Saldo Final de Efectivo</td><td style="padding:10px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['saldo_final_calculado']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">💵</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">Sin datos para este período</div>
    </div>
    <?php endif; ?>
</div>
