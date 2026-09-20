<?php
$tipoLabel = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC ', '08' => 'ND ', '00' => 'OTR'];
?>

<div style="max-width:1100px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">🏷️ Clasificación de comprobantes</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>"
           style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            ← Empresa
        </a>
    </div>

    <?php if (!empty($_SESSION['imputacion_error'])): ?>
    <div style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['imputacion_error']) ?>
    </div>
    <?php unset($_SESSION['imputacion_error']); endif; ?>

    <?php if (isset($_GET['ok'])): ?>
    <div style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Comprobante clasificado.
    </div>
    <?php endif; ?>

    <?php if (!empty($pendientes)): ?>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Origen</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Tipo</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Serie-Número</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;white-space:nowrap;">Fecha</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Contraparte</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;white-space:nowrap;">Monto neto</th>
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Clasificar como</th>
                    <th style="padding:10px 16px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendientes as $doc):
                $esVenta   = $doc['origen'] === 'venta';
                $tipos     = $esVenta ? $tiposVenta : $tiposCompra;
                $sugerida  = $sugerencias[$doc['origen'] . ':' . $doc['contraparte_doc']] ?? null;
            ?>
            <tr style="border-top:1px solid #f1f5f9;">
                <td style="padding:9px 16px;">
                    <span style="background:<?= $esVenta ? '#dcfce7' : '#fef3c7' ?>;color:<?= $esVenta ? '#166534' : '#92400e' ?>;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                        <?= $esVenta ? 'Venta' : 'Compra' ?>
                    </span>
                </td>
                <td style="padding:9px 16px;font-family:monospace;font-weight:600;color:#475569;">
                    <?= $tipoLabel[$doc['tipo_comp']] ?? $doc['tipo_comp'] ?>
                </td>
                <td style="padding:9px 16px;font-family:monospace;font-weight:600;">
                    <?= htmlspecialchars($doc['serie']) ?>-<?= htmlspecialchars($doc['correlativo']) ?>
                </td>
                <td style="padding:9px 16px;white-space:nowrap;"><?= $doc['fecha_emision'] ?></td>
                <td style="padding:9px 16px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                    title="<?= htmlspecialchars($doc['contraparte_nombre']) ?>">
                    <?= htmlspecialchars(substr($doc['contraparte_nombre'], 0, 35)) ?>
                </td>
                <td style="padding:9px 16px;text-align:right;font-family:monospace;font-weight:700;">
                    S/ <?= number_format((float)$doc['monto_neto'], 2) ?>
                </td>
                <td colspan="2" style="padding:6px 16px;">
                    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/clasificar" style="display:flex;gap:8px;align-items:center;">
                        <input type="hidden" name="origen" value="<?= $doc['origen'] ?>">
                        <input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
                        <select name="tipo_gasto_id" required
                                style="flex:1;padding:6px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1e293b;min-width:180px;">
                            <option value="">Seleccionar…</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] === $sugerida ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre_visible']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit"
                                style="background:#1e3a8a;color:white;border:none;padding:7px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                            ✓ Confirmar
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">✅</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
            No hay comprobantes pendientes de clasificar
        </div>
        <div style="font-size:13px;">
            Todo lo sincronizado desde SIRE ya tiene una cuenta contable asignada.
        </div>
    </div>
    <?php endif; ?>
</div>
