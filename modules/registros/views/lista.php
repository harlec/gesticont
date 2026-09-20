<?php
$esVentas  = $tipo === 'ventas';
$titulo    = $esVentas ? '📄 Registro de Ventas' : '🧾 Registro de Compras';
$urlBase   = "/empresas/{$empresa['id']}/" . ($esVentas ? 'ventas' : 'compras');
$urlOtro   = "/empresas/{$empresa['id']}/" . ($esVentas ? 'compras' : 'ventas');
$tipoLabel = ['01'=>'FAC','03'=>'BOL','07'=>'NC ','08'=>'ND ','00'=>'OTR'];
?>

<div style="max-width:1100px;margin:0 auto;">
    <?php $subtabActiva = $esVentas ? 'ventas' : 'compras'; require ROOT . '/views/layout/comprobantes_subtabs.php'; ?>

    <!-- Selector de período -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Período:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01'));
            ?>
            <a href="<?= $urlBase ?>?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;
                      color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($periodos)): ?>
            <span style="font-size:13px;color:var(--gc-muted);">Sin datos — sincroniza primero</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($registros)): ?>

    <!-- Cards resumen -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
        <?php
        $cards = [
            ['Comprobantes', number_format($resumen['cant']), 'var(--gc-brand)', 'var(--gc-brand-soft)'],
            ['Base imponible', 'S/ ' . number_format((float)$resumen['base'], 2), 'var(--gc-pos)', 'var(--gc-pos-soft-2)'],
            ['IGV', 'S/ ' . number_format((float)$resumen['igv'], 2), 'var(--gc-warn)', 'var(--gc-warn-soft)'],
            ['Total', 'S/ ' . number_format((float)$resumen['total'], 2), 'var(--gc-ink)', 'var(--gc-bg)'],
        ];
        foreach ($cards as [$label, $valor, $color, $bg]):
        ?>
        <div style="background:<?= $bg ?>;border-radius:12px;padding:16px 18px;border:1px solid var(--gc-line);">
            <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?= $label ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;"><?= $valor ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabla -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">#</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Tipo</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Serie-Número</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Fecha</th>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;"><?= $esVentas ? 'Cliente' : 'Proveedor' ?></th>
                    <?php if (!$esVentas): ?>
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">RUC</th>
                    <?php endif; ?>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Base</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">IGV</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Total</th>
                    <th style="padding:10px 16px;text-align:center;color:var(--gc-label-2);font-weight:700;white-space:nowrap;">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $num = ($page - 1) * $perPage + 1;
            foreach ($registros as $r):
                $tipo_label = $tipoLabel[$r['tipo_comp']] ?? $r['tipo_comp'];
                $esNC  = in_array($r['tipo_comp'], ['07','08']);
                $color = $esNC ? 'var(--gc-neg)' : 'var(--gc-ink)';
                $nombre = $esVentas
                    ? ($r['cliente_nombre'] ?? '-')
                    : ($r['proveedor_nombre'] ?? '-');
                $rucProv = $esVentas ? null : ($r['proveedor_ruc'] ?? '');
                $estado = $r['estado_sunat'] === '1' ? ['var(--gc-pos-soft)','var(--gc-pos)','Activo'] : ['var(--gc-neg-soft)','var(--gc-neg)','Anulado'];
            ?>
            <tr style="border-top:1px solid var(--gc-surface-2);color:<?= $color ?>;"
                onmouseover="this.style.background='var(--gc-bg)'"
                onmouseout="this.style.background='var(--gc-surface)'">
                <td style="padding:9px 16px;color:var(--gc-muted);"><?= $num++ ?></td>
                <td style="padding:9px 16px;">
                    <span style="background:var(--gc-surface-2);padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;color:var(--gc-label);font-family:monospace;">
                        <?= $tipo_label ?>
                    </span>
                </td>
                <td style="padding:9px 16px;font-family:monospace;font-weight:600;">
                    <?= htmlspecialchars($r['serie']) ?>-<?= htmlspecialchars($r['correlativo']) ?>
                </td>
                <td style="padding:9px 16px;white-space:nowrap;"><?= $r['fecha_emision'] ?></td>
                <td style="padding:9px 16px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= htmlspecialchars($nombre) ?>">
                    <?= htmlspecialchars(substr($nombre, 0, 40)) ?>
                </td>
                <?php if (!$esVentas): ?>
                <td style="padding:9px 16px;font-family:monospace;color:var(--gc-label-2);"><?= $rucProv ?></td>
                <?php endif; ?>
                <td style="padding:9px 16px;text-align:right;font-family:monospace;">
                    <?= $r['base_imponible'] != 0 ? 'S/ ' . number_format((float)$r['base_imponible'], 2) : '—' ?>
                </td>
                <td style="padding:9px 16px;text-align:right;font-family:monospace;">
                    <?= $r['igv'] != 0 ? 'S/ ' . number_format((float)$r['igv'], 2) : '—' ?>
                </td>
                <td style="padding:9px 16px;text-align:right;font-family:monospace;font-weight:700;">
                    S/ <?= number_format((float)$r['total'], 2) ?>
                </td>
                <td style="padding:9px 16px;text-align:center;">
                    <span style="background:<?= $estado[0] ?>;color:<?= $estado[1] ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">
                        <?= $estado[2] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <!-- Totales -->
            <tfoot>
                <tr style="background:var(--gc-bg);border-top:2px solid var(--gc-line);font-weight:700;">
                    <td colspan="<?= $esVentas ? 6 : 7 ?>" style="padding:10px 16px;color:var(--gc-label);">
                        Total <?= $resumen['cant'] ?> comprobantes
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:var(--gc-pos);">
                        S/ <?= number_format((float)$resumen['base'], 2) ?>
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:var(--gc-warn);">
                        S/ <?= number_format((float)$resumen['igv'], 2) ?>
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:var(--gc-brand);font-size:15px;">
                        S/ <?= number_format((float)$resumen['total'], 2) ?>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid var(--gc-line);display:flex;justify-content:center;gap:8px;">
            <?php for ($p = 1; $p <= $totalPages; $p++):
                $activo = $p === $page;
            ?>
            <a href="<?= $urlBase ?>?periodo=<?= $periodo ?>&page=<?= $p ?>"
               style="padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;
                      color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;"><?= $esVentas ? '📄' : '🧾' ?></div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">
            Sin <?= $esVentas ? 'ventas' : 'compras' ?> para este período
        </div>
        <div style="font-size:13px;margin-bottom:20px;">
            <?php if (empty($periodos)): ?>
            Aún no se han sincronizado datos desde el SIRE.
            <?php else: ?>
            No hay datos para <?= date('F Y', strtotime(substr($periodo,0,4).'-'.substr($periodo,4,2).'-01')) ?>.
            <?php endif; ?>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>/sync"
           style="background:var(--gc-brand);color:var(--gc-on-brand);padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
            🔄 Sincronizar SIRE
        </a>
    </div>
    <?php endif; ?>
</div>
