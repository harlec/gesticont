<?php
$rolesDisponibles = Auth::isSuperadmin()
    ? ['superadmin'=>'Super Admin','contador'=>'Contador','operador'=>'Operador','cliente'=>'Cliente']
    : ['operador'=>'Operador','cliente'=>'Cliente'];
?>
<div style="max-width:700px;">
<?php if ($error): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#991b1b;">
    <?= $error === 'email' ? '⚠ Ese correo ya está registrado.' : '⚠ Completa nombre, correo y contraseña.' ?>
</div>
<?php endif; ?>
<form method="POST" action="/usuarios/crear">
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:16px;">
        <div style="padding:18px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div style="font-weight:700;font-size:16px;color:#1e293b;">Datos del usuario</div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Nombre completo *</label>
                <input type="text" name="nombre" required placeholder="Ej: Carlos Aquino" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico *</label>
                <input type="email" name="email" required placeholder="correo@ejemplo.com" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Contraseña *</label>
                <input type="password" name="password" required placeholder="Mínimo 8 caracteres" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Rol *</label>
                <select name="rol" id="rolSelect" onchange="showRolInfo(this.value)" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:white;">
                    <?php foreach ($rolesDisponibles as $v => $l): ?>
                    <option value="<?= $v ?>"><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="rolInfo" style="grid-column:1/-1;background:#eff6ff;border-radius:8px;padding:10px 14px;font-size:13px;color:#1e3a8a;display:none;"></div>
        </div>
    </div>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:16px;">
        <div style="padding:18px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:700;font-size:16px;color:#1e293b;">Empresas asignadas</div>
                <div style="font-size:12px;color:#94a3b8;margin-top:2px;">El usuario solo verá estas empresas al iniciar sesión</div>
            </div>
            <button type="button" onclick="toggleAll()" style="font-size:12px;color:#2563eb;background:none;border:none;cursor:pointer;font-family:inherit;font-weight:600;">Seleccionar todas</button>
        </div>
        <div style="padding:16px 24px;">
            <?php if (empty($empresas)): ?>
            <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px;">
                <?php if (Auth::isSuperadmin()): ?>
                No hay empresas. <a href="/empresas/crear" style="color:#2563eb;">Crear empresa →</a>
                <?php else: ?>
                No tienes empresas asignadas a tu cuenta. Pide al administrador que te asigne empresas primero.
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <?php foreach ($empresas as $emp): ?>
                <label class="emp-label" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .15s;">
                    <input type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" style="width:16px;height:16px;accent-color:#1e3a8a;flex-shrink:0;" onchange="updateLabel(this)"/>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($emp['razon_social']) ?></div>
                        <div style="font-size:11px;color:#94a3b8;font-family:monospace;"><?= $emp['ruc'] ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;">
        <a href="/usuarios" style="background:#f1f5f9;color:#475569;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:#1e3a8a;color:white;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Crear usuario</button>
    </div>
</form>
<script>
const info = {
    superadmin:'Ve y gestiona TODO el sistema — todos los contadores y empresas.',
    contador:'Ve solo sus empresas asignadas. Puede crear operadores.',
    operador:'Puede emitir comprobantes y ver datos. No puede cambiar configuración.',
    cliente:'Acceso solo a su empresa. Emite y consulta sus propios comprobantes.',
};
function showRolInfo(v) {
    const el = document.getElementById('rolInfo');
    if(info[v]){el.textContent='💡 '+info[v];el.style.display='block';}
    else el.style.display='none';
}
function updateLabel(chk) {
    const lbl = chk.closest('label');
    lbl.style.borderColor = chk.checked ? '#1e3a8a' : '#e2e8f0';
    lbl.style.background  = chk.checked ? '#eff6ff' : 'white';
}
function toggleAll() {
    const checks = document.querySelectorAll('input[name="empresas[]"]');
    const all    = [...checks].every(c => c.checked);
    checks.forEach(c => { c.checked = !all; updateLabel(c); });
}
window.onload = () => showRolInfo(document.getElementById('rolSelect')?.value);
</script>
