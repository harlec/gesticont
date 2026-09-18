<div style="max-width:680px;">
<form method="POST" action="/empresas/crear">
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc;">
            <div style="font-weight:700;font-size:16px;color:#1e293b;">Datos de la empresa</div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Razón social *</label>
                <input type="text" name="razon_social" required placeholder="Ej: AVIMAS JM E.I.R.L." style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">RUC *</label>
                <input type="text" name="ruc" required maxlength="11" placeholder="20XXXXXXXXX" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:monospace;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Régimen tributario *</label>
                <select name="regimen" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:white;">
                    <option value="mype">MYPE Tributario</option>
                    <option value="general">Régimen General</option>
                    <option value="especial">Régimen Especial</option>
                    <option value="rus">Nuevo RUS</option>
                </select>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico</label>
                <input type="email" name="email" placeholder="empresa@correo.com" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Teléfono</label>
                <input type="text" name="telefono" placeholder="01-XXXXXXX" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div style="grid-column:1/-1;">
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Dirección</label>
                <input type="text" name="direccion" placeholder="Av. Principal 123, Lima" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
        </div>
    </div>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;margin-top:16px;">
        <div style="padding:20px 24px;border-bottom:1px solid #e2e8f0;background:#eff6ff;">
            <div style="font-weight:700;font-size:16px;color:#1e3a8a;">🔐 Credenciales SUNAT</div>
            <div style="font-size:12px;color:#475569;margin-top:4px;">Se guardan encriptadas — solo para sincronización automática</div>
        </div>
        <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Usuario SOL</label>
                <input type="text" name="sol_usuario" placeholder="USUARIO_SOL" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:monospace;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Clave SOL</label>
                <input type="password" name="sol_clave" placeholder="••••••••" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;" onfocus="this.style.borderColor='#1e3a8a'" onblur="this.style.borderColor='#e2e8f0'"/>
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Ambiente SUNAT</label>
                <select name="ambiente" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:white;">
                    <option value="beta">Beta (pruebas)</option>
                    <option value="produccion">Producción</option>
                </select>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;">
        <a href="/empresas" style="background:#f1f5f9;color:#475569;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
        <button type="submit" style="background:#1e3a8a;color:white;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">Guardar empresa</button>
    </div>
</form>
</div>
