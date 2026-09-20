<?php
$anios = range((int)date('Y'), (int)date('Y') - 4);
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:750px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">🔒 Cierre de Período</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?></div>
        </div>
    </div>

    <?php if (!empty($_SESSION['cierre_error'])): ?>
    <div style="background:#fef2f2;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">⚠ <?= htmlspecialchars($_SESSION['cierre_error']) ?></div>
    <?php unset($_SESSION['cierre_error']); endif; ?>
    <?php if (!empty($_SESSION['cierre_ok'])): ?>
    <div style="background:#f0fdf4;color:#166534;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">✓ <?= htmlspecialchars($_SESSION['cierre_ok']) ?></div>
    <?php unset($_SESSION['cierre_ok']); endif; ?>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Año:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($anios as $a): $activo = $a === $anio; ?>
            <a href="?anio=<?= $a ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;"><?= $a ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:20px;">
        <?php if (!$periodoContable): ?>
            <div style="color:#94a3b8;text-align:center;padding:20px;">No existe todavía el período contable <?= $anio ?> para esta empresa — genera al menos un asiento primero.</div>
        <?php elseif ($yaCerrado): ?>
            <div style="background:#f0fdf4;color:#166534;border-radius:10px;padding:14px 18px;font-weight:700;">🔒 El período <?= $anio ?> ya está cerrado. Sus asientos son inmutables.</div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= (int)$pendientes['ventas'] === 0 ? '#f0fdf4' : '#fef2f2' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:#475569;">Ventas sin clasificar en <?= $anio ?></span>
                    <span style="font-weight:700;color:<?= (int)$pendientes['ventas'] === 0 ? '#166534' : '#991b1b' ?>;"><?= $pendientes['ventas'] ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= (int)$pendientes['compras'] === 0 ? '#f0fdf4' : '#fef2f2' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:#475569;">Compras sin clasificar en <?= $anio ?></span>
                    <span style="font-weight:700;color:<?= (int)$pendientes['compras'] === 0 ? '#166534' : '#991b1b' ?>;"><?= $pendientes['compras'] ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= abs($pendienteInventariable) < 0.01 ? '#f0fdf4' : '#fef2f2' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:#475569;">Mercadería/insumos sin reclasificar (sin Kardex)</span>
                    <span style="font-weight:700;color:<?= abs($pendienteInventariable) < 0.01 ? '#166534' : '#991b1b' ?>;">S/ <?= $fmt($pendienteInventariable) ?></span>
                </div>
            </div>

            <?php if ($puedeCerrar): ?>
            <form method="POST" action="/empresas/<?= $empresa['id'] ?>/cierre/cerrar"
                  onsubmit="return confirm('¿Cerrar el período <?= $anio ?>? Los asientos quedarán inmutables y se generarán los saldos de apertura de <?= $anio + 1 ?>. Esta acción no se puede deshacer desde la pantalla.');">
                <input type="hidden" name="anio" value="<?= $anio ?>">
                <button type="submit" style="background:#991b1b;color:white;border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                    🔒 Cerrar Período <?= $anio ?>
                </button>
            </form>
            <div style="font-size:11px;color:#94a3b8;margin-top:10px;">
                Al cerrar: se registra la Utilidad/Pérdida Neta en 5911/5912, se provisiona el Impuesto a la Renta (4017), se generan los saldos de apertura de <?= $anio + 1 ?>, y los asientos de <?= $anio ?> quedan inmutables.
            </div>
            <?php else: ?>
            <div style="background:#fef3c7;color:#92400e;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:600;">
                ⚠ No se puede cerrar todavía — resuelve lo marcado en rojo arriba primero.
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
