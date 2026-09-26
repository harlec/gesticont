<?php
$fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '—';
$anios = range((int)date('Y'), (int)date('Y') - 4);
$colsI = $columnas['ingreso']; $colsE = $columnas['egreso'];
$th  = 'padding:7px 10px;text-align:right;font-weight:700;color:var(--gc-muted);white-space:nowrap;';
$td  = 'padding:6px 10px;text-align:right;font-family:monospace;white-space:nowrap;';
$sep = 'border-left:2px solid var(--gc-line);';
$totIng = array_fill_keys(array_keys($colsI), 0.0); $totEgr = array_fill_keys(array_keys($colsE), 0.0);
?>
<div class="gc-content gc-w-content">
    <?php $cajaTab = 'libro'; require ROOT . '/views/layout/caja_subtabs.php'; ?>

    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
        <div style="font-size:16px;font-weight:700;color:var(--gc-ink);margin-right:8px;">Libro Caja y Bancos <?= $anio ?></div>
        <?php foreach ($anios as $a): $act = $a === $anio; ?>
        <a href="/empresas/<?= $empresa['id'] ?>/caja/libro?anio=<?= $a ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $a ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($colsI) && empty($colsE)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:50px;text-align:center;color:var(--gc-muted);font-size:14px;">
        No hay movimientos de Caja en <?= $anio ?>. Saldo inicial de Caja y Bancos: <strong>S/ <?= number_format($saldoInicial, 2) ?></strong>.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
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
    <div style="font-size:12px;color:var(--gc-muted);margin-top:8px;">
        Saldo inicial = cuentas 10x del Inventario Inicial de <?= $anio ?>. Pasa el cursor sobre el código de cada columna para ver el nombre de la cuenta.
        Solo cuentan los movimientos con cuenta contable, los mismos que generan el asiento de Caja.
    </div>
    <?php endif; ?>
</div>
