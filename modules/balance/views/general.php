<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:1600px;margin:0 auto;">

    <!-- Selector de período -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Al cierre de:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/balance-general?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
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
    <?php if (abs($resultado['descuadre']) > 0.01):
        $falertTipo = abs($diferenciaSinExplicar) > 0.01 ? 'neg' : 'warn';
        $falertId = 'falert-balance-general';
        $falertResumen = 'Activo ≠ Pasivo + Patrimonio — diferencia S/ ' . $fmt($resultado['descuadre']);
        ob_start(); ?>
        <div style="margin-bottom:6px;">Explicado por dos cosas que todavía faltan construir, no por un error de datos:</div>
        <ul style="margin:0 0 6px 18px;padding:0;">
            <li>Compras pendientes de reclasificar a existencias/costo de venta (sin Kardex todavía): <strong>S/ <?= $fmt($resultado['pendiente_inventariable']) ?></strong></li>
            <li>Impuesto a la Renta ya restado de la utilidad pero sin su asiento de provisión (falta el Cierre de Período, 2.16): <strong>S/ <?= $fmt($resultado['impuesto_renta_pendiente']) ?></strong></li>
        </ul>
        <div>
            Diferencia esperada por estas dos causas: S/ <?= $fmt($descuadreEsperado) ?>.
            <?php if (abs($diferenciaSinExplicar) > 0.01): ?>
            <strong>Pero la diferencia real no coincide</strong> — hay S/ <?= $fmt($diferenciaSinExplicar) ?> sin explicar, eso sí es un asiento con error que revisar en el
            <a href="/empresas/<?= $empresa['id'] ?>/diario?periodo=<?= $periodo ?>" style="color:var(--gc-neg);text-decoration:underline;">Libro Diario</a>.
            <?php else: ?>
            Coincide exactamente — no hay ningún error, solo falta construir esas dos piezas.
            <?php endif; ?>
        </div>
        <?php
        $falertDetalleHtml = ob_get_clean();
        require ROOT . '/views/layout/floating_alert.php';
    else: ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Activo = Pasivo + Patrimonio = S/ <?= $fmt($resultado['total_activo']) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <!-- ACTIVO -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
            <div style="padding:12px 20px;background:var(--gc-brand-soft);color:var(--gc-brand);font-weight:700;font-size:13px;">ACTIVO</div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tbody>
                    <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Activo Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['activo_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Activo No Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['activo_no_corriente']) ?></td></tr>
                    <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);"><td style="padding:8px 20px;">TOTAL ACTIVO</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_activo']) ?></td></tr>
                </tbody>
            </table>
        </div>

        <!-- PASIVO + PATRIMONIO -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
            <div style="padding:12px 20px;background:var(--gc-warn-soft);color:var(--gc-warn);font-weight:700;font-size:13px;">PASIVO Y PATRIMONIO</div>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tbody>
                    <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Pasivo Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pasivo_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px;color:var(--gc-label);">Pasivo No Corriente</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['pasivo_no_corriente']) ?></td></tr>
                    <tr style="border-top:1px solid var(--gc-line);font-weight:700;"><td style="padding:8px 20px;color:var(--gc-label);">Total Pasivo</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_pasivo']) ?></td></tr>

                    <tr style="border-top:1px solid var(--gc-surface-2);"><td style="padding:8px 20px 2px;color:var(--gc-muted);font-size:11px;text-transform:uppercase;letter-spacing:1px;">Patrimonio</td><td></td></tr>
                    <tr><td style="padding:2px 20px;color:var(--gc-label);">Capital Social</td><td style="padding:2px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['capital_social']) ?></td></tr>
                    <tr><td style="padding:2px 20px;color:var(--gc-label);">Resultados Acumulados</td><td style="padding:2px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['resultados_acumulados']) ?></td></tr>
                    <tr><td style="padding:2px 20px 8px;color:var(--gc-label);">Resultado del Ejercicio</td><td style="padding:2px 20px 8px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['resultado_ejercicio']) ?></td></tr>
                    <tr style="border-top:1px solid var(--gc-line);font-weight:700;"><td style="padding:8px 20px;color:var(--gc-label);">Total Patrimonio</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['patrimonio']) ?></td></tr>

                    <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);"><td style="padding:8px 20px;">TOTAL PASIVO Y PATRIMONIO</td><td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= $fmt($resultado['total_pasivo_patrimonio']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">🏛️</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">Sin datos para este período</div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-brand);text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
