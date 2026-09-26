<div class="gc-content gc-w-content">
<div class="gc-content gc-w-form">
<form method="POST" action="/empresas/<?= $empresa['id'] ?>/editar">
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-bg);">
            <div style="font-weight:700;font-size:16px;color:var(--gc-ink);">Editar empresa</div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Razón social *</label>
                <input type="text" name="razon_social" required value="<?= htmlspecialchars($empresa['razon_social']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">RUC</label>
                <input type="text" value="<?= htmlspecialchars($empresa['ruc']) ?>" disabled style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-bg);font-family:monospace;color:var(--gc-muted);"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Régimen tributario</label>
                <select name="regimen" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:var(--gc-surface);">
                    <option value="mype" <?= $empresa['regimen']==='mype'?'selected':'' ?>>MYPE Tributario</option>
                    <option value="general" <?= $empresa['regimen']==='general'?'selected':'' ?>>Régimen General</option>
                    <option value="especial" <?= $empresa['regimen']==='especial'?'selected':'' ?>>Régimen Especial</option>
                    <option value="rus" <?= $empresa['regimen']==='rus'?'selected':'' ?>>Nuevo RUS</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico</label>
                <input type="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Teléfono</label>
                <input type="text" name="telefono" value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
            </div>
        </div>
    </div>

    <?php
        $lbl = fn($t) => '<label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">' . $t . '</label>';
        $selectStyle = 'width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:var(--gc-surface);';
    ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-top:20px;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-bg);">
            <div style="font-weight:700;font-size:16px;color:var(--gc-ink);">Perfil comercial</div>
            <div style="font-size:12px;color:var(--gc-muted);margin-top:2px;">
                Se usa como propuesta automática al clasificar comprobantes que no tienen una
                <a href="/empresas/<?= $empresa['id'] ?>/imputacion/reglas" style="color:var(--gc-brand);">regla propia por RUC</a>.
                Si vendes/compras "ambos", no se puede adivinar cuál aplica a cada comprobante — queda para elegir a mano.
            </div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
            <div>
                <?= $lbl('¿Qué vende?') ?>
                <select name="vende_tipo" style="<?= $selectStyle ?>">
                    <option value="">Sin definir</option>
                    <option value="productos" <?= ($empresa['vende_tipo'] ?? '') === 'productos' ? 'selected' : '' ?>>Solo productos</option>
                    <option value="servicios" <?= ($empresa['vende_tipo'] ?? '') === 'servicios' ? 'selected' : '' ?>>Solo servicios</option>
                    <option value="ambos" <?= ($empresa['vende_tipo'] ?? '') === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                </select>
            </div>
            <div>
                <?= $lbl('Cuenta — venta de producto') ?>
                <select name="venta_tipo_gasto_producto_id" style="<?= $selectStyle ?>">
                    <option value="">—</option>
                    <?php foreach ($tiposVenta as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)($empresa['venta_tipo_gasto_producto_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre_visible']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?= $lbl('Cuenta — venta de servicio') ?>
                <select name="venta_tipo_gasto_servicio_id" style="<?= $selectStyle ?>">
                    <option value="">—</option>
                    <?php foreach ($tiposVenta as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)($empresa['venta_tipo_gasto_servicio_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre_visible']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?= $lbl('¿Qué compra principalmente?') ?>
                <select name="compra_tipo" style="<?= $selectStyle ?>">
                    <option value="">Sin definir</option>
                    <option value="productos" <?= ($empresa['compra_tipo'] ?? '') === 'productos' ? 'selected' : '' ?>>Solo productos</option>
                    <option value="servicios" <?= ($empresa['compra_tipo'] ?? '') === 'servicios' ? 'selected' : '' ?>>Solo servicios</option>
                    <option value="ambos" <?= ($empresa['compra_tipo'] ?? '') === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                </select>
            </div>
            <div>
                <?= $lbl('Cuenta — compra de producto') ?>
                <select name="compra_tipo_gasto_producto_id" style="<?= $selectStyle ?>">
                    <option value="">—</option>
                    <?php foreach ($tiposCompra as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)($empresa['compra_tipo_gasto_producto_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre_visible']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <?= $lbl('Cuenta — compra de servicio') ?>
                <select name="compra_tipo_gasto_servicio_id" style="<?= $selectStyle ?>">
                    <option value="">—</option>
                    <?php foreach ($tiposCompra as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= (int)($empresa['compra_tipo_gasto_servicio_id'] ?? 0) === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre_visible']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
        <a href="/empresas/<?= $empresa['id'] ?>" style="background:var(--gc-surface-2);color:var(--gc-label);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Guardar cambios</button>
    </div>
</form>
</div>
</div>
