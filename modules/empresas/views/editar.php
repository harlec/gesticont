<div style="max-width:680px;">
<form method="POST" action="/empresas/<?= $empresa['id'] ?>/editar">
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div style="font-weight:700;font-size:16px;color:#1e293b;">Editar empresa</div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Razón social *</label>
                <input type="text" name="razon_social" required value="<?= htmlspecialchars($empresa['razon_social']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">RUC</label>
                <input type="text" value="<?= htmlspecialchars($empresa['ruc']) ?>" disabled style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;background:#f8fafc;font-family:monospace;color:#94a3b8;"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Régimen tributario</label>
                <select name="regimen" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:white;">
                    <option value="mype" <?= $empresa['regimen']==='mype'?'selected':'' ?>>MYPE Tributario</option>
                    <option value="general" <?= $empresa['regimen']==='general'?'selected':'' ?>>Régimen General</option>
                    <option value="especial" <?= $empresa['regimen']==='especial'?'selected':'' ?>>Régimen Especial</option>
                    <option value="rus" <?= $empresa['regimen']==='rus'?'selected':'' ?>>Nuevo RUS</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico</label>
                <input type="email" name="email" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Teléfono</label>
                <input type="text" name="telefono" value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
        <a href="/empresas/<?= $empresa['id'] ?>" style="background:#f1f5f9;color:#475569;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:#1e3a8a;color:white;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Guardar cambios</button>
    </div>
</form>
</div>
