<?php
/** Estado de cambios en el patrimonio (rediseño). Variables: $empresa, $periodo, $resultado. */
$id = (int)$empresa['id'];
$contActiva = 'cambios-patrimonio'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Cambios en el patrimonio';
$contDesc = 'Acumulado de enero a ' . Periodo::etiqueta($periodo, true);
$contAcciones = '<a class="gc-btn" href="/empresas/' . $id . '/cambios-patrimonio/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>';
require ROOT . '/views/layout/contabilidad_head.php';
$S = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : ((float)$v < 0 ? '(−) ' : '') . 'S/ ' . number_format(abs((float)$v), 2);
?>
<?php if (!$resultado): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay datos para este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else: $r = $resultado;
    // Columnas: Capital · Reserva legal · Resultados acumulados · Resultado del ejercicio · Total
    $filas = [
        ['Saldo al 1 de enero',                       [$r['capital_inicial'], $r['reservas_inicial'], $r['resultados_inicial'], 0], false],
        ['Resultado del ejercicio',                   [0, 0, 0, $r['utilidad_neta']], false],
        ['Reserva legal y dividendos (al cierre)',    [0, $r['reserva_legal'], -$r['reserva_legal'], 0], false],
        ['Saldo al ' . date('j', strtotime(date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01')))) . ' de ' . Periodo::etiqueta($periodo, true), [$r['capital_final'], $r['reservas_final'], $r['resultados_final'], $r['utilidad_neta'] - $r['utilidad_neta']], true],
    ];
    // En el saldo final el resultado del ejercicio ya está repartido (reserva + acumulados), salvo que aún no se cierre el año.
    $cab = ['Capital', 'Reserva legal', 'Resultados acumulados', 'Resultado del ejercicio', 'Total'];
?>
<section class="gc-card clip" style="overflow-x:auto;">
<table class="gc-table" style="min-width:820px;">
    <thead><tr><th></th><?php foreach ($cab as $c): ?><th class="r"><?= $c ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($filas as [$etq, $cols, $fuerte]):
        $tot = $fuerte ? $r['total_final'] : array_sum($cols); ?>
        <tr style="<?= $fuerte ? 'background:var(--gc-bg);font-weight:700;' : '' ?>">
            <td><?= htmlspecialchars($etq) ?></td>
            <?php foreach ($cols as $v): ?><td class="r num"><?= $S($v) ?></td><?php endforeach; ?>
            <td class="r num" style="font-weight:700;"><?= $S($tot) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</section>
<div class="gc-sub">El capital se mantiene fijo: todavía no hay un módulo de aportes ni retiros de capital. La reserva legal se calcula con el porcentaje de los parámetros contables.</div>
<?php endif; ?>
