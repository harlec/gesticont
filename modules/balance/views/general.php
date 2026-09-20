<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>
<?php require ROOT . '/views/layout/empresa_tabs.php'; ?>

<div style="max-width:1000px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">🏛️ Balance General</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <!-- Selector de período -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Al cierre de:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/balance-general?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>

    <?php
        // Descuadre esperado mientras falten dos piezas (ninguna es un error):
        // el IR ya restado de la utilidad pero sin su asiento de provisión
        // (Cierre de Período, 2.16), y las compras inventariables (601-604)
        // que siguen "flotando" sin Kardex/Inventario Final que las
        // reclasifique a existencias o costo de venta.
        $descuadreEsperado = round($resultado['impuesto_renta_pendiente'] - $resultado['pendiente_inventariable'], 2);
        $diferenciaSinExplicar = round($resultado['descuadre'] - $descuadreEsperado, 2);
    ?>
    <?php if (abs($resultado['descuadre']) > 0.01): ?>
    <div style="background:<?= abs($diferenciaSinExplicar) > 0.01 ? '#fef2f2' : '#fef3c7' ?>;color:<?= abs($diferenciaSinExplicar) > 0.01 ? '#991b1b' : '#92400e' ?>;border-radius:10px;padding:14px 18px;margin-bottom:16px;font-size:13px;">
        <div style="font-weight:700;margin-bottom:6px;">⚠ Activo ≠ Pasivo + Patrimonio — diferencia S/ <?= $fmt($resultado['descuadre']) ?></div>
        <div style="margin-bottom:6px;">
            Explicado por dos cosas que todavía faltan construir, no por un error de datos:
        </div>
        <ul style="margin:0 0 6px 18px;padding:0;">
            <li>Compras pendientes de reclasificar a existencias/costo de venta (sin Kardex todavía): <strong>S/ <?= $fmt($resultado['pendiente_inventariable']) ?></strong></li>
            <li>Impuesto a la Renta ya restado de la utilidad pero sin su asiento de provisión (falta el Cierre de Período, 2.16): <strong>S/ <?= $fmt($resultado['impuesto_renta_pendiente']) ?></strong></li>
        </ul>
        <div>
            Diferencia esperada por estas dos causas: S/ <?= $fmt($descuadreEsperado) ?>.
            <?php if (abs($diferenciaSinExplicar) > 0.01): ?>
            <strong>Pero la diferencia real no coincide</strong> — hay S/ <?= $fmt($diferenciaSinExplicar) ?> sin explicar, eso sí es un asiento con error que revisar en el
            <a href="/empresas/<?= $empresa['id'] ?>/diario?periodo=<?= $periodo ?>" style="color:#991b1b;text-decoration:underline;">Libro Diario</a>.
            <?php else: ?>
            Coincide exactamente — no hay ningún error, solo falta construir esas dos piezas.
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div style="background:#f0fdf4;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Activo = Pasivo + Patrimonio = S/ <?= $fmt($resultado['total_activo']) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <!-- ACTIVO -->
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
            <div style="padding:12px 20px;background:#eff6ff;color:#1e3a8a;font-weight:700;font-size:13px;">ACTIVO</div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tbody>
                    <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Activo Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['activo_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Activo No Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['activo_no_corriente']) ?></td></tr>
                    <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;"><td style="padding:8px 20px;">TOTAL ACTIVO</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_activo']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- PASIVO + PATRIMONIO -->
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
            <div style="padding:12px 20px;background:#fef3c7;color:#92400e;font-weight:700;font-size:13px;">PASIVO Y PATRIMONIO</div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tbody>
                    <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Pasivo Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pasivo_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px;color:#475569;">Pasivo No Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pasivo_no_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid #e2e8f0;font-weight:700;"><td style="padding:8px 20px;color:#475569;">Total Pasivo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_pasivo']) ?></td></tr>

                    <tr style="border-top:1px solid #f1f5f9;"><td style="padding:8px 20px 2px;color:#94a3b8;font-size:11px;text-transform:uppercase;letter-spacing:1px;">Patrimonio</td><td></td></tr>
                    <tr><td style="padding:2px 20px;color:#475569;">Capital Social</td><td style="padding:2px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['capital_social']) ?></td></tr>
                    <tr><td style="padding:2px 20px;color:#475569;">Resultados Acumulados</td><td style="padding:2px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['resultados_acumulados']) ?></td></tr>
                    <tr><td style="padding:2px 20px 8px;color:#475569;">Resultado del Ejercicio</td><td style="padding:2px 20px 8px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['resultado_ejercicio']) ?></td></tr>
                    <tr style="border-top:1px solid #e2e8f0;font-weight:700;"><td style="padding:8px 20px;color:#475569;">Total Patrimonio</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['patrimonio']) ?></td></tr>

                    <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;"><td style="padding:8px 20px;">TOTAL PASIVO Y PATRIMONIO</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_pasivo_patrimonio']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">🏛️</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">Sin datos para este período</div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:#1e3a8a;text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
