<?php
$roles = ['superadmin'=>'Super Admin','contador'=>'Contador','operador'=>'Operador','cliente'=>'Cliente'];
$rc    = ['superadmin'=>['var(--gc-warn-soft)','var(--gc-warn)'],'contador'=>['var(--gc-link-bg)','var(--gc-brand)'],'operador'=>['var(--gc-pos-soft)','var(--gc-pos)'],'cliente'=>['var(--gc-neutral-bg)','var(--gc-neutral-text)']][$usuario['rol']] ?? ['var(--gc-neutral-bg)','var(--gc-neutral-text)'];
?>
<?php if (isset($_GET['ok'])): ?>
<div style="background:var(--gc-pos-soft);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:var(--gc-pos);font-weight:600;">
    ✓ <?= ['empresas'=>'Empresas actualizadas','editado'=>'Datos actualizados'][$_GET['ok']] ?? 'Guardado' ?>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:28px;">
    <div style="width:52px;height:52px;border-radius:50%;background:var(--gc-brand);display:flex;align-items:center;justify-content:center;color:var(--gc-on-brand);font-size:18px;font-weight:700;flex-shrink:0;">
        <?= strtoupper(substr($usuario['nombre'],0,2)) ?>
    </div>
    <div style="flex:1;">
        <div style="font-size:20px;font-weight:700;color:var(--gc-ink);"><?= htmlspecialchars($usuario['nombre']) ?></div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($usuario['email']) ?></div>
    </div>
    <span style="background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;">
        <?= $roles[$usuario['rol']] ?? $usuario['rol'] ?>
    </span>
    <a href="/usuarios/<?= $usuario['id'] ?>/editar" style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Editar datos</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Empresas asignadas actualmente -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;font-size:15px;color:var(--gc-ink);">
            Empresas asignadas (<?= count($empresasUsuario) ?>)
        </div>
        <?php if (empty($empresasUsuario)): ?>
        <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:13px;">Sin empresas asignadas aún.</div>
        <?php else: ?>
        <?php foreach ($empresasUsuario as $eu): ?>
        <div style="padding:13px 20px;border-top:1px solid var(--gc-surface-2);display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($eu['razon_social']) ?></div>
                <div style="font-size:11px;color:var(--gc-muted);font-family:monospace;"><?= $eu['ruc'] ?></div>
            </div>
            <span style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;"><?= ucfirst($eu['rol_empresa']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Actualizar empresas -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;font-size:15px;color:var(--gc-ink);">
            Actualizar asignación
        </div>
        <form method="POST" action="/usuarios/<?= $usuario['id'] ?>/empresas">
            <div style="padding:16px 20px;max-height:300px;overflow-y:auto;">
                <?php if (empty($todasEmpresas)): ?>
                <div style="text-align:center;color:var(--gc-muted);font-size:13px;">Sin empresas disponibles.</div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($todasEmpresas as $emp):
                        $sel = in_array($emp['id'], $asignadasIds);
                    ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid <?= $sel?'var(--gc-brand)':'var(--gc-line)' ?>;border-radius:8px;cursor:pointer;background:<?= $sel?'var(--gc-brand-soft)':'var(--gc-surface)' ?>;" onmouseover="this.style.borderColor='var(--gc-brand)'" onmouseout="this.style.borderColor=this.querySelector('input').checked?'var(--gc-brand)':'var(--gc-line)'">
                        <input type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" <?= $sel?'checked':'' ?> style="width:16px;height:16px;accent-color:var(--gc-brand);" onchange="this.closest('label').style.borderColor=this.checked?'var(--gc-brand)':'var(--gc-line)';this.closest('label').style.background=this.checked?'var(--gc-brand-soft)':'var(--gc-surface)'"/>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($emp['razon_social']) ?></div>
                            <div style="font-size:11px;color:var(--gc-muted);font-family:monospace;"><?= $emp['ruc'] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding:14px 20px;border-top:1px solid var(--gc-line);background:var(--gc-bg);">
                <button type="submit" style="width:100%;background:var(--gc-brand);color:var(--gc-on-brand);padding:10px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                    Guardar asignación
                </button>
            </div>
        </form>
    </div>
</div>
