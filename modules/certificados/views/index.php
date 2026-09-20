<?php
$regimenes = ['general'=>'Régimen General','mype'=>'MYPE Tributario','especial'=>'Régimen Especial','rus'=>'Nuevo RUS'];
?>
<div style="max-width:700px;margin:0 auto;">

    <?php if ($cert): ?>
    <div style="background:var(--gc-pos-soft);border:1px solid var(--gc-pos-border);border-radius:12px;padding:14px 20px;margin-bottom:20px;font-size:13px;color:var(--gc-pos);">
        <strong>✓ Credenciales configuradas</strong> —
        Ambiente: <?= ucfirst($cert['ambiente'] ?? 'beta') ?> |
        API SUNAT: <?= !empty($cert['api_client_id']) ? '✓ Configurada' : '⚠ Pendiente' ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/certificado">

        <!-- BLOQUE 1: Credenciales SOL -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-brand-soft);">
                <div style="font-weight:700;font-size:15px;color:var(--gc-brand);">🔑 Credenciales SOL</div>
                <div style="font-size:12px;color:var(--gc-label);margin-top:3px;">
                    <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
                </div>
            </div>
            <div style="padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Usuario SOL *</label>
                    <input type="text" name="sol_usuario" required placeholder="Ej: GESTICONT"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:monospace;"
                        onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                    <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Solo el usuario, sin el RUC</div>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Clave SOL *</label>
                    <input type="password" name="sol_clave" required placeholder="••••••••"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;"
                        onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Ambiente SUNAT *</label>
                    <select name="ambiente"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:var(--gc-surface);">
                        <option value="beta">Beta (pruebas)</option>
                        <option value="produccion">Producción</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Contraseña certificado .pfx</label>
                    <input type="password" name="cert_password" placeholder="Solo si tiene .pfx"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;"
                        onfocus="this.style.borderColor='var(--gc-brand)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                </div>
            </div>
        </div>

        <!-- BLOQUE 2: Credenciales API SUNAT (para SIRE) -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-pos-soft-2);">
                <div style="font-weight:700;font-size:15px;color:var(--gc-pos);">🔐 Credenciales API SUNAT</div>
                <div style="font-size:12px;color:var(--gc-label);margin-top:3px;">
                    Para sincronización automática con el SIRE — se generan en el portal SOL
                </div>
            </div>
            <div style="padding:20px 24px;display:grid;grid-template-columns:1fr;gap:14px;">

                <!-- Instrucciones -->
                <div style="background:var(--gc-bg);border-radius:8px;padding:14px 16px;font-size:13px;color:var(--gc-label);line-height:1.7;border:1px solid var(--gc-line);">
                    <strong style="color:var(--gc-ink);">¿Cómo obtener estas credenciales?</strong><br>
                    1. Entra al portal SOL con el RUC <strong><?= $empresa['ruc'] ?></strong> y usuario principal<br>
                    2. Ve a <strong>Empresas → Credenciales de API SUNAT → Gestión Credenciales</strong><br>
                    3. Edita la aplicación y marca <strong>MIGE RCE y RVIE - SIRE</strong> + Alcance <strong>Desktop</strong><br>
                    4. Copia el <strong>ID</strong> y la <strong>CLAVE</strong> que aparecen
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">
                            Client ID (ID de la aplicación)
                        </label>
                        <input type="text" name="api_client_id"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                            style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:13px;outline:none;font-family:monospace;"
                            onfocus="this.style.borderColor='var(--gc-pos)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                        <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Formato UUID con guiones</div>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">
                            Client Secret (Clave de la aplicación)
                        </label>
                        <input type="password" name="api_client_secret"
                            placeholder="••••••••••••••••••••"
                            style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;"
                            onfocus="this.style.borderColor='var(--gc-pos)'" onblur="this.style.borderColor='var(--gc-line)'"/>
                        <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Clave generada por SUNAT</div>
                    </div>
                </div>

                <?php if (!empty($cert['api_client_id'])): ?>
                <div style="background:var(--gc-pos-soft);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--gc-pos);">
                    ✓ API SUNAT configurada — sincronización con SIRE activa
                </div>
                <?php else: ?>
                <div style="background:var(--gc-warn-soft);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--gc-warn);">
                    ⚠ Sin credenciales API — la sincronización automática no funcionará hasta configurarlas
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:12px;">
            <a href="/empresas/<?= $empresa['id'] ?>"
               style="background:var(--gc-surface-2);color:var(--gc-label);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
                Cancelar
            </a>
            <button type="submit"
                style="background:var(--gc-brand);color:var(--gc-on-brand);padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                Guardar y encriptar
            </button>
        </div>
    </form>
</div>
