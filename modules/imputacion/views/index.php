<?php
$tipoLabel = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC ', '08' => 'ND ', '00' => 'OTR'];
$labelMes  = fn($p) => date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
?>

<div style="max-width:900px;">

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

    <!-- Filtros: período + tipo -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Período</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if (empty($periodosConPendientes)): ?>
                    <span style="font-size:13px;color:#94a3b8;">Sin pendientes en ningún período</span>
                <?php endif; ?>
                <?php foreach ($periodosConPendientes as $p):
                    $activo = $p === $periodo;
                ?>
                <a href="?periodo=<?= $p ?>&tipo=<?= $tipoFiltro ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                    <?= $labelMes($p) ?>
                </a>
                <?php endforeach; ?>
                <?php if (!in_array($periodo, $periodosConPendientes, true)): ?>
                <span style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;background:#1e3a8a;color:white;">
                    <?= $labelMes($periodo) ?> (sin pendientes)
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Tipo</label>
            <div style="display:flex;gap:8px;">
                <?php foreach (['todos' => 'Todos', 'ventas' => 'Solo ventas', 'compras' => 'Solo compras'] as $val => $lbl):
                    $activo = $tipoFiltro === $val;
                ?>
                <a href="?periodo=<?= $periodo ?>&tipo=<?= $val ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          background:<?= $activo ? '#5b21b6' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                    <?= $lbl ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($pendientes)): ?>

    <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($pendientes as $doc):
            $esVenta  = $doc['origen'] === 'venta';
            $tipos    = $esVenta ? $tiposVenta : $tiposCompra;
            $sugerida = $sugerencias[$doc['origen'] . ':' . $doc['contraparte_doc']] ?? null;
        ?>
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:14px 18px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="background:<?= $esVenta ? '#dcfce7' : '#fef3c7' ?>;color:<?= $esVenta ? '#166534' : '#92400e' ?>;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                        <?= $esVenta ? 'VENTA' : 'COMPRA' ?>
                    </span>
                    <span style="font-family:monospace;font-weight:700;color:#475569;font-size:12px;">
                        <?= $tipoLabel[$doc['tipo_comp']] ?? $doc['tipo_comp'] ?>
                    </span>
                    <span style="font-family:monospace;font-weight:600;">
                        <?= htmlspecialchars($doc['serie']) ?>-<?= htmlspecialchars($doc['correlativo']) ?>
                    </span>
                    <span style="color:#94a3b8;font-size:12px;">· <?= $doc['fecha_emision'] ?></span>
                </div>
                <div style="font-family:monospace;font-weight:700;font-size:15px;color:#1e293b;">
                    S/ <?= number_format((float)$doc['monto_neto'], 2) ?>
                </div>
            </div>
            <div style="font-size:13px;color:#475569;margin-bottom:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                 title="<?= htmlspecialchars($doc['contraparte_nombre']) ?>">
                <?= htmlspecialchars($doc['contraparte_nombre']) ?>
            </div>
            <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/clasificar"
                  style="display:flex;gap:8px;">
                <input type="hidden" name="origen" value="<?= $doc['origen'] ?>">
                <input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
                <select name="tipo_gasto_id" required
                        style="flex:1;min-width:0;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1e293b;">
                    <option value="">Seleccionar cuenta…</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id'] === $sugerida ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nombre_visible']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" style="flex-shrink:0;background:#1e3a8a;color:white;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                    ✓ Confirmar
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">✅</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
            No hay comprobantes pendientes en <?= $labelMes($periodo) ?><?= $tipoFiltro !== 'todos' ? ' (' . ($tipoFiltro === 'ventas' ? 'ventas' : 'compras') . ')' : '' ?>
        </div>
        <div style="font-size:13px;">
            Prueba otro período o revisa que ya se haya sincronizado desde SIRE.
        </div>
    </div>
    <?php endif; ?>
</div>
