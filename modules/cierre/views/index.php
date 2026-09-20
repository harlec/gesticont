<?php
$anios = range((int)date('Y'), (int)date('Y') - 4);
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:750px;margin:0 auto;">

    <?php if (!empty($_SESSION['cierre_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">⚠ <?= htmlspecialchars($_SESSION['cierre_error']) ?></div>
    <?php unset($_SESSION['cierre_error']); endif; ?>
    <?php if (!empty($_SESSION['cierre_ok'])): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">✓ <?= htmlspecialchars($_SESSION['cierre_ok']) ?></div>
    <?php unset($_SESSION['cierre_ok']); endif; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Año:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($anios as $a): $activo = $a === $anio; ?>
            <a href="?anio=<?= $a ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $a ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:20px;">
        <?php if (!$periodoContable): ?>
            <div style="color:var(--gc-muted);text-align:center;padding:20px;">No existe todavía el período contable <?= $anio ?> para esta empresa — genera al menos un asiento primero.</div>
        <?php elseif ($yaCerrado): ?>
            <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:14px 18px;font-weight:700;">🔒 El período <?= $anio ?> ya está cerrado. Sus asientos son inmutables.</div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= (int)$pendientes['ventas'] === 0 ? 'var(--gc-pos-soft-2)' : 'var(--gc-neg-soft)' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:var(--gc-label);">Ventas sin clasificar en <?= $anio ?></span>
                    <span style="font-weight:700;color:<?= (int)$pendientes['ventas'] === 0 ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;"><?= $pendientes['ventas'] ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= (int)$pendientes['compras'] === 0 ? 'var(--gc-pos-soft-2)' : 'var(--gc-neg-soft)' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:var(--gc-label);">Compras sin clasificar en <?= $anio ?></span>
                    <span style="font-weight:700;color:<?= (int)$pendientes['compras'] === 0 ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;"><?= $pendientes['compras'] ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:10px 14px;background:<?= abs($pendienteInventariable) < 0.01 ? 'var(--gc-pos-soft-2)' : 'var(--gc-neg-soft)' ?>;border-radius:8px;">
                    <span style="font-size:13px;color:var(--gc-label);">Mercadería/insumos sin reclasificar (sin Kardex)</span>
                    <span style="font-weight:700;color:<?= abs($pendienteInventariable) < 0.01 ? 'var(--gc-pos)' : 'var(--gc-neg)' ?>;">S/ <?= $fmt($pendienteInventariable) ?></span>
                </div>
            </div>

            <?php if ($puedeCerrar): ?>
            <form method="POST" action="/empresas/<?= $empresa['id'] ?>/cierre/cerrar"
                  onsubmit="return confirm('¿Cerrar el período <?= $anio ?>? Los asientos quedarán inmutables y se generarán los saldos de apertura de <?= $anio + 1 ?>. Esta acción no se puede deshacer desde la pantalla.');">
                <input type="hidden" name="anio" value="<?= $anio ?>">
                <button type="submit" style="background:var(--gc-neg-action);color:var(--gc-on-brand);border:none;padding:10px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                    🔒 Cerrar Período <?= $anio ?>
                </button>
            </form>
            <div style="font-size:11px;color:var(--gc-muted);margin-top:10px;">
                Al cerrar: se registra la Utilidad/Pérdida Neta en 5911/5912, se provisiona el Impuesto a la Renta (4017), se generan los saldos de apertura de <?= $anio + 1 ?>, y los asientos de <?= $anio ?> quedan inmutables.
            </div>
            <?php else: ?>
            <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:12px 16px;font-size:13px;font-weight:600;">
                ⚠ No se puede cerrar todavía — resuelve lo marcado en rojo arriba primero.
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
