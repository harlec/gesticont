<div class="gc-content gc-w-content">
<div class="gc-content gc-w-form">
<?php if ($error): ?>
<div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:var(--gc-neg);">
    <?= $error === 'email' ? '⚠ Ese correo ya está registrado.' : '⚠ Completa nombre y correo.' ?>
</div>
<?php endif; ?>
<form method="POST" action="/usuarios/<?= $usuario['id'] ?>/update">
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
        <div style="padding:18px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-bg);">
            <div style="font-weight:700;font-size:16px;color:var(--gc-ink);">Editar usuario</div>
            <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($usuario['email']) ?></div>
        </div>
        <div style="padding:24px;display:flex;flex-direction:column;gap:16px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Nombre completo *</label>
                <input type="text" name="nombre" required value="<?= htmlspecialchars($usuario['nombre']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico *</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($usuario['email']) ?>" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Nueva contraseña</label>
                <input type="password" name="password" placeholder="Dejar vacío para no cambiar" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Solo completa si deseas cambiar la contraseña actual.</div>
            </div>
        </div>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:12px;">
        <a href="/usuarios/<?= $usuario['id'] ?>" style="background:var(--gc-surface-2);color:var(--gc-label);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Guardar cambios</button>
    </div>
</form>
</div>
</div>
