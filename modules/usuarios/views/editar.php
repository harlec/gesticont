<div style="max-width:540px;">
<?php if ($error): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#991b1b;">
    <?= $error === 'email' ? '⚠ Ese correo ya está registrado.' : '⚠ Completa nombre y correo.' ?>
</div>
<?php endif; ?>
<form method="POST" action="/usuarios/<?= $usuario['id'] ?>/update">
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:16px;">
        <div style="padding:18px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div style="font-weight:700;font-size:16px;color:#1e293b;">Editar usuario</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($usuario['email']) ?></div>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Nombre completo *</label>
                <input type="text" name="nombre" required value="<?= htmlspecialchars($usuario['nombre']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico *</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($usuario['email']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Nueva contraseña</label>
                <input type="password" name="password" placeholder="Dejar vacío para no cambiar" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Solo completa si deseas cambiar la contraseña actual.</div>
            </div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;">
        <a href="/usuarios/<?= $usuario['id'] ?>" style="background:#f1f5f9;color:#475569;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:#1e3a8a;color:white;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Guardar cambios</button>
    </div>
</form>
</div>
