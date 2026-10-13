<?php
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
$anios = Periodo::anios();
$colsI = $columnas['ingreso']; $colsE = $columnas['egreso'];
$th  = 'padding:7px 10px;text-align:right;font-weight:700;color:var(--gc-muted);white-space:nowrap;';
$td  = 'padding:6px 10px;text-align:right;font-family:monospace;white-space:nowrap;';
$sep = 'border-left:2px solid var(--gc-line);';
$totIng = array_fill_keys(array_keys($colsI), 0.0); $totEgr = array_fill_keys(array_keys($colsE), 0.0);
?>
<?php $cajaTab = 'libro'; require ROOT . '/views/layout/caja_subtabs.php'; ?>
<div class="gc-row between no-print">
    <div class="gc-stack" style="gap:4px;"><h2 style="margin:0;font-size:24px;font-weight:700;">Libro Caja y Bancos <?= $anio ?></h2><div class="gc-sub">Ingresos y egresos por cuenta, mes a mes. Saldo inicial: S/ <?= number_format($saldoInicial, 2) ?></div></div>
    <div class="gc-row" style="gap:10px;">
        <div class="gc-seg"><?php foreach ($anios as $a): ?><a href="/empresas/<?= $empresa['id'] ?>/caja/libro?anio=<?= $a ?>" class="<?= $a === $anio ? 'is-active' : '' ?>"><?= $a ?></a><?php endforeach; ?></div>
        <a href="/empresas/<?= $empresa['id'] ?>/caja/libro/pdf?anio=<?= $anio ?>" target="_blank" rel="noopener" class="gc-btn">Descargar PDF</a>
    </div>
</div>

    <?php if (empty($colsI) && empty($colsE)): ?>
    <div class="gc-card"><div class="gc-empty">
        No hay movimientos de Caja en <?= $anio ?>. Saldo inicial de Caja y Bancos: <b>S/ <?= number_format($saldoInicial, 2) ?></b>.
    </div></div>
    <?php else: ?>
    <div class="gc-card clip" style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);">
                    <th rowspan="2" style="padding:8px 12px;text-align:left;color:var(--gc-label-2);">Mes</th>
                    <th colspan="<?= count($colsI) + 1 ?>" style="padding:8px 10px;text-align:center;color:var(--gc-pos);border-left:2px solid var(--gc-line);">INGRESOS</th>
                    <th colspan="<?= count($colsE) + 1 ?>" style="padding:8px 10px;text-align:center;color:var(--gc-neg);border-left:2px solid var(--gc-line);">EGRESOS</th>
                    <th rowspan="2" style="<?= $th ?><?= $sep ?>color:var(--gc-label-2);">SALDO</th>
                </tr>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <?php $i = 0; foreach ($colsI as $cod => $nom): ?><th title="<?= htmlspecialchars($nom) ?>" style="<?= $th ?><?= $i++ === 0 ? $sep : '' ?>"><?= $cod ?></th><?php endforeach; ?>
                    <th style="<?= $th ?><?= empty($colsI) ? $sep : '' ?>color:var(--gc-label-2);">TOTAL</th>
                    <?php $i = 0; foreach ($colsE as $cod => $nom): ?><th title="<?= htmlspecialchars($nom) ?>" style="<?= $th ?><?= $i++ === 0 ? $sep : '' ?>"><?= $cod ?></th><?php endforeach; ?>
                    <th style="<?= $th ?><?= empty($colsE) ? $sep : '' ?>color:var(--gc-label-2);">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background:var(--gc-bg);font-weight:700;">
                    <td style="padding:6px 12px;">SALDO INICIAL</td>
                    <td colspan="<?= count($colsI) + count($colsE) + 2 ?>"></td>
                    <td style="<?= $td ?><?= $sep ?>"><?= number_format($saldoInicial, 2) ?></td>
                </tr>
            <?php $saldo = $saldoInicial; $sumI = 0.0; $sumE = 0.0;
            for ($m = 1; $m <= 12; $m++):
                $ti = array_sum($mat[$m]['ingreso'] ?? []); $te = array_sum($mat[$m]['egreso'] ?? []);
                $saldo = round($saldo + $ti - $te, 2); $sumI += $ti; $sumE += $te; ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:6px 12px;font-weight:600;white-space:nowrap;"><?= Periodo::etiqueta(sprintf('%04d%02d', $anio, $m), true) ?></td>
                    <?php $i = 0; foreach ($colsI as $cod => $nom): $v = $mat[$m]['ingreso'][$cod] ?? 0; $totIng[$cod] += $v; ?>
                    <td style="<?= $td ?><?= $i++ === 0 ? $sep : '' ?>color:var(--gc-pos);"><?= $fmt($v) ?></td><?php endforeach; ?>
                    <td style="<?= $td ?><?= empty($colsI) ? $sep : '' ?>font-weight:700;"><?= $fmt($ti) ?></td>
                    <?php $i = 0; foreach ($colsE as $cod => $nom): $v = $mat[$m]['egreso'][$cod] ?? 0; $totEgr[$cod] += $v; ?>
                    <td style="<?= $td ?><?= $i++ === 0 ? $sep : '' ?>color:var(--gc-neg);"><?= $fmt($v) ?></td><?php endforeach; ?>
                    <td style="<?= $td ?><?= empty($colsE) ? $sep : '' ?>font-weight:700;"><?= $fmt($te) ?></td>
                    <td style="<?= $td ?><?= $sep ?>font-weight:700;color:<?= $saldo < 0 ? 'var(--gc-neg)' : 'var(--gc-ink)' ?>;"><?= number_format($saldo, 2) ?></td>
                </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot>
                <tr style="border-top:2px solid var(--gc-line);background:var(--gc-bg);font-weight:700;">
                    <td style="padding:8px 12px;">TOTAL AÑO</td>
                    <?php $i = 0; foreach ($colsI as $cod => $nom): ?><td style="<?= $td ?><?= $i++ === 0 ? $sep : '' ?>"><?= $fmt($totIng[$cod]) ?></td><?php endforeach; ?>
                    <td style="<?= $td ?><?= empty($colsI) ? $sep : '' ?>"><?= $fmt($sumI) ?></td>
                    <?php $i = 0; foreach ($colsE as $cod => $nom): ?><td style="<?= $td ?><?= $i++ === 0 ? $sep : '' ?>"><?= $fmt($totEgr[$cod]) ?></td><?php endforeach; ?>
                    <td style="<?= $td ?><?= empty($colsE) ? $sep : '' ?>"><?= $fmt($sumE) ?></td>
                    <td style="<?= $td ?><?= $sep ?>"><?= number_format($saldo, 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="gc-sub">
        Saldo inicial = cuentas 10x del Inventario Inicial de <?= $anio ?>. Pasa el cursor sobre el código de cada columna para ver el nombre de la cuenta.
        Solo cuentan los movimientos con cuenta contable, los mismos que generan el asiento de Caja.
    </div>
    <?php endif; ?>
