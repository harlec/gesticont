<?php
/** Balance general (rediseño). Variables: $empresa, $periodo, $resultado (BalanceGeneralService o null). */
$id = (int)$empresa['id']; $mes = Periodo::etiqueta($periodo, true);
$S = fn($v) => number_format(abs((float)$v), 2);
$contActiva = 'balance-general'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Balance general';
$contDesc = 'Al cierre de ' . $mes . ' · acumulado del año';
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/balance-general/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>';
require ROOT . '/views/layout/contabilidad_head.php';

$fila = function (array $it) use ($S) {
    $cls = !empty($it['resta']) ? 'color:var(--gc-neg);' : '';
    $nom = !empty($it['resta']) ? '(−) ' . $it['nombre'] : $it['nombre'];
    return '<div class="gc-row between" style="padding:8px 0;border-top:1px solid var(--gc-surface-2);flex-wrap:nowrap;gap:12px;"><div><div>' . htmlspecialchars($nom) . '</div><div class="gc-sub" style="font-size:14px;">Cuenta ' . htmlspecialchars($it['codigo']) . '</div></div><div class="num" style="font-weight:600;white-space:nowrap;' . $cls . '">S/ ' . $S($it['monto']) . '</div></div>';
};
$bloque = function (string $titulo, array $items, float $total) use ($fila, $S) {
    $h = '<div style="margin-bottom:6px;"><div class="gc-row between" style="font-weight:700;font-size:17px;padding:6px 0;"><span>' . $titulo . '</span><span class="num">S/ ' . $S($total) . '</span></div>';
    foreach ($items as $it) $h .= $fila($it);
    return $h . ($items ? '' : '<div class="gc-sub" style="padding:6px 0;border-top:1px solid var(--gc-surface-2);">Sin saldos</div>') . '</div>';
};
?>
<?php if (!$resultado): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay datos para este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else: $d = $resultado['detalle']; $cuadra = abs($resultado['descuadre']) <= 0.01; ?>

<section class="gc-card pad">
    <div class="gc-row between" style="gap:24px;">
        <div class="gc-row" style="gap:28px;font-size:18px;">
            <div><div class="gc-sub" style="font-size:15px;">Activo</div><b class="num" style="font-size:26px;">S/ <?= $S($resultado['total_activo']) ?></b></div>
            <b style="font-size:26px;color:var(--gc-label-2);">=</b>
            <div><div class="gc-sub" style="font-size:15px;">Pasivo + Patrimonio</div><b class="num" style="font-size:26px;">S/ <?= $S($resultado['total_pasivo_patrimonio']) ?></b></div>
        </div>
        <?php if ($cuadra): ?><span class="gc-chip ok" style="font-size:16px;padding:6px 16px;">Cuadra</span>
        <?php else: ?><span class="gc-chip neg" style="font-size:16px;padding:6px 16px;">No cuadra: S/ <?= $S($resultado['descuadre']) ?></span><?php endif; ?>
    </div>
</section>

<?php if (!$resultado['ir_registrado'] && $resultado['impuesto_renta_estimado'] > 0): ?>
<div class="gc-alert warn"><span class="msg">El <b>impuesto a la renta</b> (estimado S/ <?= $S($resultado['impuesto_renta_estimado']) ?>) aún no está registrado: el resultado de abajo es <b>antes de impuestos</b>. Al registrarlo pasa a la cuenta 4017 y el resultado queda después de impuestos.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/asientos?plantilla=ir">Registrar impuesto a la renta</a></div>
<?php endif; ?>
<?php if ($resultado['depreciacion_mayor_al_costo']): ?>
<div class="gc-alert warn"><span class="msg">La depreciación acumulada (S/ <?= $S($resultado['depreciacion_acumulada']) ?>) es mayor que el costo de los activos fijos (S/ <?= $S($resultado['costo_activos_fijos']) ?>). Revisa los asientos de la cuenta 395.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/mayor?cuenta=395">Ver la cuenta 395 en el Mayor</a></div>
<?php endif; ?>
<?php if (!$cuadra): ?>
<div class="gc-alert neg"><span class="msg">El balance no cuadra por S/ <?= $S($resultado['descuadre']) ?>. Revisa los asientos del período: algo está registrado de un solo lado.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/diario">Ir al Libro Diario</a></div>
<?php endif; ?>

<div class="gc-row" style="align-items:flex-start;gap:24px;">
    <section class="gc-card pad" style="flex:1 1 440px;min-width:0;">
        <h3 class="gc-h2" style="margin-bottom:10px;">Activo</h3>
        <?= $bloque('Activo corriente', $d['activo_corriente'], $resultado['activo_corriente']) ?>
        <?= $bloque('Activo no corriente', $d['activo_no_corriente'], $resultado['activo_no_corriente']) ?>
        <div class="gc-row between" style="border-top:2px solid var(--gc-line);padding-top:12px;font-size:19px;font-weight:700;"><span>Total activo</span><span class="num">S/ <?= $S($resultado['total_activo']) ?></span></div>
    </section>
    <section class="gc-card pad" style="flex:1 1 440px;min-width:0;">
        <h3 class="gc-h2" style="margin-bottom:10px;">Pasivo y patrimonio</h3>
        <?= $bloque('Pasivo corriente', $d['pasivo_corriente'], $resultado['pasivo_corriente']) ?>
        <?= $bloque('Pasivo no corriente', $d['pasivo_no_corriente'], $resultado['pasivo_no_corriente']) ?>
        <div class="gc-row between" style="font-weight:700;padding:8px 0;border-top:1px solid var(--gc-line);"><span>Total pasivo</span><span class="num">S/ <?= $S($resultado['total_pasivo']) ?></span></div>
        <div style="margin-top:12px;">
            <div class="gc-row between" style="font-weight:700;font-size:17px;padding:6px 0;"><span>Patrimonio</span><span class="num">S/ <?= $S($resultado['patrimonio']) ?></span></div>
            <?php
            $pat = [['codigo' => '501', 'nombre' => 'Capital social', 'monto' => $resultado['capital_social']], ['codigo' => '591', 'nombre' => 'Resultados acumulados', 'monto' => $resultado['resultados_acumulados']]];
            foreach ($d['otros_patrimonio'] as $o) $pat[] = $o;
            $pat[] = ['codigo' => '5911', 'nombre' => 'Resultado del ejercicio (' . $resultado['resultado_rotulo'] . ')', 'monto' => $resultado['resultado_ejercicio'], 'resta' => $resultado['resultado_ejercicio'] < 0];
            foreach ($pat as $it) if (abs($it['monto']) > 0.004 || in_array($it['codigo'], ['501', '5911'], true)) echo $fila($it);
            ?>
        </div>
        <div class="gc-row between" style="border-top:2px solid var(--gc-line);padding-top:12px;margin-top:8px;font-size:19px;font-weight:700;"><span>Total pasivo y patrimonio</span><span class="num">S/ <?= $S($resultado['total_pasivo_patrimonio']) ?></span></div>
    </section>
</div>
<?php endif; ?>
