<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:700px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">💵 Estado de Flujo de Efectivo</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?></div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>" style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">← Empresa</a>
    </div>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="/empresas/<?= $empresa['id'] ?>/flujo-efectivo?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>

    <?php if (abs($resultado['descuadre']) > 0.01): ?>
    <div style="background:#fef2f2;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ El Saldo Final calculado (S/ <?= $fmt($resultado['saldo_final_calculado']) ?>) no coincide con el saldo real de Caja y Bancos en el Balance (S/ <?= $fmt($resultado['saldo_real_caja']) ?>) —
        diferencia S/ <?= $fmt($resultado['descuadre']) ?>. Regenera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:#991b1b;text-decoration:underline;">Libro Diario</a> o revisa movimientos de Caja sin cuenta asignada.
    </div>
    <?php else: ?>
    <div style="background:#f0fdf4;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Saldo Final de Efectivo = saldo real de Caja y Bancos = S/ <?= $fmt($resultado['saldo_final_calculado']) ?>
    </div>
    <?php endif; ?>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <tbody>
                <tr style="background:#f8fafc;"><td colspan="2" style="padding:8px 20px;font-weight:700;color:#1e3a8a;">ACTIVIDAD DE OPERACIÓN</td></tr>
                <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Cobranza a Clientes</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['cobranza_clientes']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:#475569;">(−) Pago a Proveedores</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pago_proveedores']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:#475569;">(−) Pago a Trabajadores</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pago_trabajadores']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:#475569;">Otros cobros/pagos operativos (tributos, ESSALUD, ONP, AFP, etc.)</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['otros_operacion']) ?></td></tr>
                <tr style="border-top:1px solid #e2e8f0;font-weight:700;"><td style="padding:8px 20px;">Flujo de Operación</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_operacion']) ?></td></tr>

                <tr style="background:#f8fafc;"><td colspan="2" style="padding:8px 20px;font-weight:700;color:#1e3a8a;">ACTIVIDAD DE INVERSIÓN</td></tr>
                <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">(−) Compra de Inmueble, Maquinaria y Equipo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['compra_activo_fijo']) ?></td></tr>
                <tr style="border-top:1px solid #e2e8f0;font-weight:700;"><td style="padding:8px 20px;">Flujo de Inversión</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_inversion']) ?></td></tr>

                <tr style="background:#f8fafc;"><td colspan="2" style="padding:8px 20px;font-weight:700;color:#1e3a8a;">ACTIVIDAD DE FINANCIAMIENTO</td></tr>
                <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Préstamos Recibidos</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['prestamos_recibidos']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:#475569;">(−) Amortización de Obligaciones</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['amortizacion']) ?></td></tr>
                <tr style="border-top:1px solid #e2e8f0;font-weight:700;"><td style="padding:8px 20px;">Flujo de Financiamiento</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['flujo_financiamiento']) ?></td></tr>

                <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;"><td style="padding:10px 20px;">Aumento/Disminución Neta de Efectivo</td><td style="padding:10px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['aumento_neto']) ?></td></tr>
                <tr><td style="padding:8px 20px;color:#475569;">Saldo Inicial de Efectivo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['saldo_inicial']) ?></td></tr>
                <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;"><td style="padding:10px 20px;">Saldo Final de Efectivo</td><td style="padding:10px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['saldo_final_calculado']) ?></td></tr>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">💵</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">Sin datos para este período</div>
    </div>
    <?php endif; ?>
</div>
