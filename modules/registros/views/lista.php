<?php
$esVentas  = $tipo === 'ventas';
$titulo    = $esVentas ? '📄 Registro de Ventas' : '🧾 Registro de Compras';
$urlBase   = "/empresas/{$empresa['id']}/" . ($esVentas ? 'ventas' : 'compras');
$urlOtro   = "/empresas/{$empresa['id']}/" . ($esVentas ? 'compras' : 'ventas');
$tipoLabel = ['01'=>'FAC','03'=>'BOL','07'=>'NC ','08'=>'ND ','00'=>'OTR'];
?>

<div style="max-width:1100px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;"><?= $titulo ?></div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?= $urlOtro ?>"
               style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                <?= $esVentas ? '🧾 Ver Compras' : '📄 Ver Ventas' ?>
            </a>
            <a href="/empresas/<?= $empresa['id'] ?>/sync"
               style="background:#1e3a8a;color:white;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                🔄 Sincronizar
            </a>
            <a href="/empresas/<?= $empresa['id'] ?>"
               style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                ← Empresa
            </a>
        </div>
    </div>

    <!-- Selector de período -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Período:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p):
                $activo = $p === $periodo;
                $label  = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01'));
            ?>
            <a href="<?= $urlBase ?>?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;
                      color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($periodos)): ?>
            <span style="font-size:13px;color:#94a3b8;">Sin datos — sincroniza primero</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($registros)): ?>

    <!-- Cards resumen -->
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px;">
        <?php
        $cards = [
            ['Comprobantes', number_format($resumen['cant']), '#1e3a8a', '#eff6ff'],
            ['Base imponible', 'S/ ' . number_format((float)$resumen['base'], 2), '#166534', '#f0fdf4'],
            ['IGV', 'S/ ' . number_format((float)$resumen['igv'], 2), '#92400e', '#fef3c7'],
            ['Total', 'S/ ' . number_format((float)$resumen['total'], 2), '#1e293b', '#f8fafc'],
        ];
        foreach ($cards as [$label, $valor, $color, $bg]):
        ?>
        <div style="background:<?= $bg ?>;border-radius:12px;padding:16px 18px;border:1px solid #e2e8f0;">
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?= $label ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $color ?>;"><?= $valor ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabla -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">#</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Tipo</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Serie-Número</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Fecha</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;"><?= $esVentas ? 'Cliente' : 'Proveedor' ?></th>
                    <?php if (!$esVentas): ?>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">RUC</th>
                    <?php endif; ?>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;white-space:nowrap;">Base</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;white-space:nowrap;">IGV</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;white-space:nowrap;">Total</th>
                    <th style="padding:10px 16px;text-align:center;color:#64748b;font-weight:700;white-space:nowrap;">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $num = ($page - 1) * $perPage + 1;
            foreach ($registros as $r):
                $tipo_label = $tipoLabel[$r['tipo_comp']] ?? $r['tipo_comp'];
                $esNC  = in_array($r['tipo_comp'], ['07','08']);
                $color = $esNC ? '#991b1b' : '#1e293b';
                $nombre = $esVentas
                    ? ($r['cliente_nombre'] ?? '-')
                    : ($r['proveedor_nombre'] ?? '-');
                $rucProv = $esVentas ? null : ($r['proveedor_ruc'] ?? '');
                $estado = $r['estado_sunat'] === '1' ? ['#dcfce7','#166534','Activo'] : ['#fef2f2','#991b1b','Anulado'];
            ?>
            <tr style="border-top:1px solid #f1f5f9;color:<?= $color ?>;"
                onmouseover="this.style.background='#f8fafc'"
                onmouseout="this.style.background='white'">
                <td style="padding:9px 16px;color:#94a3b8;"><?= $num++ ?></td>
                <td style="padding:9px 16px;">
                    <span style="background:#f1f5f9;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;color:#475569;font-family:monospace;">
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
                <td style="padding:9px 16px;font-family:monospace;color:#64748b;"><?= $rucProv ?></td>
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
                <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;font-weight:700;">
                    <td colspan="<?= $esVentas ? 6 : 7 ?>" style="padding:10px 16px;color:#475569;">
                        Total <?= $resumen['cant'] ?> comprobantes
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:#166534;">
                        S/ <?= number_format((float)$resumen['base'], 2) ?>
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:#92400e;">
                        S/ <?= number_format((float)$resumen['igv'], 2) ?>
                    </td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;color:#1e3a8a;font-size:15px;">
                        S/ <?= number_format((float)$resumen['total'], 2) ?>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div style="padding:14px 16px;border-top:1px solid #e2e8f0;display:flex;justify-content:center;gap:8px;">
            <?php for ($p = 1; $p <= $totalPages; $p++):
                $activo = $p === $page;
            ?>
            <a href="<?= $urlBase ?>?periodo=<?= $periodo ?>&page=<?= $p ?>"
               style="padding:6px 12px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;
                      color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $p ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;"><?= $esVentas ? '📄' : '🧾' ?></div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
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
           style="background:#1e3a8a;color:white;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
            🔄 Sincronizar SIRE
        </a>
    </div>
    <?php endif; ?>
</div>
