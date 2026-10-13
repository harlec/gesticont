<?php
/** Estado de resultados por función (rediseño). Variables: $empresa, $periodo, $resultado. */
$id = (int)$empresa['id']; $mes = Periodo::etiqueta($periodo, true);
$contActiva = 'resultados'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Estado de resultados';
$contDesc = 'Por función · acumulado de enero a ' . Periodo::etiqueta($periodo, true);
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/resultados/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>';
require ROOT . '/views/layout/contabilidad_head.php';
// Las cifras van en positivo y el signo en la etiqueta: "(−) Costo de ventas". Cero = "—" en gris.
$S = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : 'S/ ' . number_format(abs((float)$v), 2);
$linea = function (string $etq, float $v, string $cuenta = '', bool $fuerte = false, string $signo = '') use ($S) {
    $neg = $v < -0.004; $col = $fuerte ? ($neg ? 'var(--gc-neg)' : 'var(--gc-ink)') : 'var(--gc-ink)';
    return '<div class="gc-row between" style="padding:' . ($fuerte ? '14px' : '10px') . ' 24px;flex-wrap:nowrap;border-top:1px solid var(--gc-surface-2);' . ($fuerte ? 'background:var(--gc-bg);font-weight:700;font-size:18px;' : '') . '">'
         . '<div><span>' . ($signo !== '' ? '<span style="color:var(--gc-label-2);">(' . $signo . ')</span> ' : '') . htmlspecialchars($etq) . '</span>' . ($cuenta !== '' ? ' <span class="gc-sub" style="font-size:14px;">· ' . $cuenta . '</span>' : '') . '</div>'
         . '<div class="num" style="color:' . $col . ';white-space:nowrap;">' . ($neg ? '(−) ' : '') . $S($v) . '</div></div>';
};
?>
<?php if (!$resultado): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay datos para este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else: $r = $resultado; ?>

<?php if ($r['costo_ventas'] == 0 && $r['ingresos_ventas'] > 0): ?>
<div class="gc-alert warn"><span class="msg">El <b>costo de ventas está en cero</b>, así que la utilidad bruta coincide con las ventas. Durante el año las compras quedan en existencias; al cierre se reconoce el costo con el inventario final (inventario inicial + compras − inventario final).</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/asientos?plantilla=costo">Registrar costo de ventas</a></div>
<?php endif; ?>
<?php if (!$r['ir_registrado'] && $r['impuesto_renta'] > 0): ?>
<div class="gc-alert info"><span class="msg">El impuesto a la renta que ves es <b>estimado</b> (<?= number_format($r['tasa_ir'], 2) ?> % de la utilidad) y aún no está registrado en los libros.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/asientos?plantilla=ir">Registrar impuesto a la renta</a></div>
<?php endif; ?>

<section class="gc-card clip">
    <?= $linea('Ventas netas', $r['ingresos_ventas'], 'cuenta 70') ?>
    <?= $linea('Costo de ventas', $r['costo_ventas'], 'cuenta 69', false, '−') ?>
    <?= $linea('Utilidad bruta', $r['utilidad_bruta'], '', true) ?>
    <?= $linea('Gastos de administración', $r['gastos_admin'], 'cuenta 94', false, '−') ?>
    <?= $linea('Gastos de venta', $r['gastos_venta'], 'cuenta 95', false, '−') ?>
    <?php if (abs($r['costo_produccion']) > 0.004): ?><?= $linea('Costos de producción', $r['costo_produccion'], 'cuenta 92', false, '−') ?><?php endif; ?>
    <?= $linea('Utilidad operativa', $r['utilidad_operativa'], '', true) ?>
    <?= $linea('Ingresos financieros', $r['ingresos_financieros'], 'cuenta 77', false, '+') ?>
    <?= $linea('Gastos financieros', $r['gastos_financieros'], 'cuenta 96', false, '−') ?>
    <?= $linea('Utilidad antes de impuestos', $r['utilidad_antes_impuestos'], '', true) ?>
    <?= $linea($r['ir_registrado'] ? 'Impuesto a la renta (registrado en 881)' : 'Impuesto a la renta (estimado ' . number_format($r['tasa_ir'], 1) . ' %, aún no registrado)', $r['impuesto_renta'], '', false, '−') ?>
    <?= $linea('Utilidad neta', $r['utilidad_ejercicio'], '', true) ?>
    <?= $linea('Reserva legal ' . number_format($r['pct_reserva_legal'], 0) . ' %  (se registra al cerrar el año)', $r['reserva_legal'], '', false, '−') ?>
    <?= $linea('Utilidad antes de repartición', $r['utilidad_antes_reparticion'], '', true) ?>
</section>
<?php endif; ?>
