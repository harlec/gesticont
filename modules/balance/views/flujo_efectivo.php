<?php
/** Estado de flujo de efectivo (rediseño). Variables: $empresa, $periodo, $resultado. */
$id = (int)$empresa['id'];
$contActiva = 'flujo-efectivo'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Flujo de efectivo';
$contDesc = 'Acumulado de enero a ' . Periodo::etiqueta($periodo, true);
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/flujo-efectivo/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>';
require ROOT . '/views/layout/contabilidad_head.php';
$S = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : ((float)$v < 0 ? '(−) ' : '') . 'S/ ' . number_format(abs((float)$v), 2);
$linea = fn(string $etq, $v, bool $fuerte = false) => '<div class="gc-row between" style="padding:' . ($fuerte ? '14px' : '10px') . ' 24px;flex-wrap:nowrap;border-top:1px solid var(--gc-surface-2);' . ($fuerte ? 'background:var(--gc-bg);font-weight:700;' : '') . '"><div>' . htmlspecialchars($etq) . '</div><div class="num" style="white-space:nowrap;">' . $S($v) . '</div></div>';
$seccion = fn(string $t) => '<div style="padding:12px 24px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--gc-brand);background:var(--gc-brand-soft);">' . $t . '</div>';
?>
<?php if (!$resultado): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay datos para este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else: $r = $resultado; $cuadra = abs($r['descuadre']) <= 0.01; ?>

<section class="gc-card pad">
    <div class="gc-row" style="gap:10px;align-items:stretch;">
        <?php foreach ([['Saldo inicial', $r['saldo_inicial'], '+'], ['Operación', $r['flujo_operacion'], '+'], ['Inversión', $r['flujo_inversion'], '+'], ['Financiamiento', $r['flujo_financiamiento'], '='], ['Saldo final', $r['saldo_final_calculado'], '']] as [$t, $v, $op]): ?>
        <div style="flex:1;min-width:130px;border:1px solid var(--gc-line);border-radius:12px;padding:12px 14px;<?= $t === 'Saldo final' ? 'background:var(--gc-brand-soft);border-color:var(--gc-brand-line);' : '' ?>">
            <div class="gc-sub" style="font-size:13px;"><?= $t ?></div>
            <div class="num" style="font-size:20px;font-weight:700;"><?= $S($v) ?></div>
        </div>
        <?php if ($op !== ''): ?><div style="align-self:center;font-size:22px;color:var(--gc-muted);"><?= $op ?></div><?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($cuadra): ?>
<div class="gc-alert ok"><span class="msg">El saldo final coincide con las cuentas de caja y bancos (cuenta 10): S/ <?= number_format($r['saldo_final_calculado'], 2) ?>.</span></div>
<?php else: ?>
<div class="gc-alert warn"><span class="msg">El saldo final calculado (S/ <?= number_format($r['saldo_final_calculado'], 2) ?>) no coincide con caja y bancos del balance (S/ <?= number_format($r['saldo_real_caja'], 2) ?>). Diferencia: S/ <?= number_format(abs($r['descuadre']), 2) ?>. Revisa movimientos de caja sin cuenta asignada.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/mayor?cuenta=101">Ver el Mayor de la 101</a></div>
<?php endif; ?>

<section class="gc-card clip">
    <?= $seccion('Actividades de operación') ?>
    <?= $linea('Cobranza a clientes', $r['cobranza_clientes']) ?>
    <?= $linea('(−) Pago a proveedores', abs($r['pago_proveedores'])) ?>
    <?= $linea('(−) Pago a trabajadores', abs($r['pago_trabajadores'])) ?>
    <?= $linea('Otros cobros y pagos operativos (tributos, ESSALUD, ONP, AFP)', $r['otros_operacion']) ?>
    <?= $linea('Flujo de operación', $r['flujo_operacion'], true) ?>
    <?= $seccion('Actividades de inversión') ?>
    <?= $linea('(−) Compra de inmuebles, maquinaria y equipo', abs($r['compra_activo_fijo'])) ?>
    <?= $linea('Flujo de inversión', $r['flujo_inversion'], true) ?>
    <?= $seccion('Actividades de financiamiento') ?>
    <?= $linea('Préstamos recibidos', $r['prestamos_recibidos']) ?>
    <?= $linea('(−) Amortización de obligaciones', abs($r['amortizacion'])) ?>
    <?= $linea('Flujo de financiamiento', $r['flujo_financiamiento'], true) ?>
    <?= $linea('Aumento o disminución neta de efectivo', $r['aumento_neto'], true) ?>
    <?= $linea('Saldo inicial de efectivo', $r['saldo_inicial']) ?>
    <?= $linea('Saldo final de efectivo', $r['saldo_final_calculado'], true) ?>
</section>
<?php endif; ?>
