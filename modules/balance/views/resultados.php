<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
$fila = function (string $label, float $val, bool $bold = false, bool $resta = false, ?string $sub = null) use ($fmt) {
    $signo = $resta ? '(−) ' : '';
    echo '<tr style="' . ($bold ? 'border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);' : 'border-top:1px solid var(--gc-surface-2);') . '">';
    echo '<td style="padding:9px 20px;color:' . ($bold ? 'var(--gc-ink)' : 'var(--gc-label)') . ';">' . $signo . htmlspecialchars($label);
    if ($sub) echo '<div style="font-size:11px;color:var(--gc-muted);font-weight:400;margin-top:2px;">' . htmlspecialchars($sub) . '</div>';
    echo '</td>';
    echo '<td style="padding:9px 20px;text-align:right;font-family:monospace;' . ($val < 0 ? 'color:var(--gc-neg);' : '') . '">S/ ' . $fmt($val) . '</td>';
    echo '</tr>';
};
?>

<div class="gc-content gc-w-content">

    <!-- Selector de período -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
            ?>
            <a href="/empresas/<?= $empresa['id'] ?>/resultados?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>

    <?php if ($resultado['costo_ventas'] == 0): ?>
    <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ℹ El Costo de Ventas aparece en S/ 0.00 — todavía no existe el módulo de Inventario Final/Kardex (spec 2.6), así que la Utilidad Bruta por ahora coincide con los Ingresos.
    </div>
    <?php endif; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <tbody>
                <?php
                $fila('Ingresos por Ventas', $resultado['ingresos_ventas'], false, false, 'Cuentas 701-704');
                $fila('Costo de Ventas', $resultado['costo_ventas'], false, true, 'Cuenta 691');
                $fila('Utilidad Bruta', $resultado['utilidad_bruta'], true);

                $fila('Costo de Producción', $resultado['costo_produccion'], false, true, 'Cuenta 92');
                $fila('Gastos de Administración', $resultado['gastos_admin'], false, true, 'Cuenta 94');
                $fila('Gastos de Venta', $resultado['gastos_venta'], false, true, 'Cuenta 95');
                $fila('Gastos Financieros', $resultado['gastos_financieros'], false, true, 'Cuenta 96');
                $fila('Utilidad Operativa', $resultado['utilidad_operativa'], true);

                $fila('Ingresos Financieros', $resultado['ingresos_financieros'], false, false, 'Cuenta 779');
                $fila('Utilidad Antes de Impuestos', $resultado['utilidad_antes_impuestos'], true);

                $fila('Impuesto a la Renta (' . number_format($resultado['tasa_ir'], 2) . '%)', $resultado['impuesto_renta'], false, true);
                $fila('Utilidad del Ejercicio', $resultado['utilidad_ejercicio'], true);

                $fila('Reserva Legal (' . number_format($resultado['pct_reserva_legal'], 2) . '%)', $resultado['reserva_legal'], false, true);
                $fila('Utilidad Antes de Repartición', $resultado['utilidad_antes_reparticion'], true);
                ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">📊</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">
            Sin datos para este período
        </div>
        <div style="font-size:13px;">
            Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-brand);text-decoration:underline;">Libro Diario</a> primero.
        </div>
    </div>
    <?php endif; ?>
</div>
