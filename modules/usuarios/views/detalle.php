<?php
$roles = ['superadmin'=>'Super Admin','contador'=>'Contador','operador'=>'Operador','cliente'=>'Cliente'];
$rc    = ['superadmin'=>['#fef3c7','#92400e'],'contador'=>['#dbeafe','#1e3a8a'],'operador'=>['#dcfce7','#166534'],'cliente'=>['#f3f4f6','#374151']][$usuario['rol']] ?? ['#f3f4f6','#374151'];
?>
<?php if (isset($_GET['ok'])): ?>
<div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#166534;font-weight:600;">
    ✓ <?= ['empresas'=>'Empresas actualizadas','editado'=>'Datos actualizados'][$_GET['ok']] ?? 'Guardado' ?>
</div>
<?php endif; ?>

<div style="display:flex;align-items:center;gap:16px;margin-bottom:28px;">
    <div style="width:52px;height:52px;border-radius:50%;background:#1e3a8a;display:flex;align-items:center;justify-content:center;color:white;font-size:18px;font-weight:700;flex-shrink:0;">
        <?= strtoupper(substr($usuario['nombre'],0,2)) ?>
    </div>
    <div style="flex:1;">
        <div style="font-size:20px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($usuario['nombre']) ?></div>
        <div style="font-size:13px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($usuario['email']) ?></div>
    </div>
    <span style="background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;padding:5px 14px;border-radius:20px;font-size:13px;font-weight:700;">
        <?= $roles[$usuario['rol']] ?? $usuario['rol'] ?>
    </span>
    <a href="/usuarios/<?= $usuario['id'] ?>/editar" style="background:#eff6ff;color:#1e3a8a;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">Editar datos</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <!-- Empresas asignadas actualmente -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:15px;color:#1e293b;">
            Empresas asignadas (<?= count($empresasUsuario) ?>)
        </div>
        <?php if (empty($empresasUsuario)): ?>
        <div style="padding:30px;text-align:center;color:#94a3b8;font-size:13px;">Sin empresas asignadas aún.</div>
        <?php else: ?>
        <?php foreach ($empresasUsuario as $eu): ?>
        <div style="padding:13px 20px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
            <div>
                <div style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($eu['razon_social']) ?></div>
                <div style="font-size:11px;color:#94a3b8;font-family:monospace;"><?= $eu['ruc'] ?></div>
            </div>
            <span style="background:#eff6ff;color:#1e3a8a;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:600;"><?= ucfirst($eu['rol_empresa']) ?></span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Actualizar empresas -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:15px;color:#1e293b;">
            Actualizar asignación
        </div>
        <form method="POST" action="/usuarios/<?= $usuario['id'] ?>/empresas">
            <div style="padding:16px 20px;max-height:300px;overflow-y:auto;">
                <?php if (empty($todasEmpresas)): ?>
                <div style="text-align:center;color:#94a3b8;font-size:13px;">Sin empresas disponibles.</div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach ($todasEmpresas as $emp):
                        $sel = in_array($emp['id'], $asignadasIds);
                    ?>
                    <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1.5px solid <?= $sel?'#1e3a8a':'#e2e8f0' ?>;border-radius:8px;cursor:pointer;background:<?= $sel?'#eff6ff':'white' ?>;" onmouseover="this.style.borderColor='#1e3a8a'" onmouseout="this.style.borderColor=this.querySelector('input').checked?'#1e3a8a':'#e2e8f0'">
                        <input type="checkbox" name="empresas[]" value="<?= $emp['id'] ?>" <?= $sel?'checked':'' ?> style="width:16px;height:16px;accent-color:#1e3a8a;" onchange="this.closest('label').style.borderColor=this.checked?'#1e3a8a':'#e2e8f0';this.closest('label').style.background=this.checked?'#eff6ff':'white'"/>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($emp['razon_social']) ?></div>
                            <div style="font-size:11px;color:#94a3b8;font-family:monospace;"><?= $emp['ruc'] ?></div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding:14px 20px;border-top:1px solid #e2e8f0;background:#f8fafc;">
                <button type="submit" style="width:100%;background:#1e3a8a;color:white;padding:10px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                    Guardar asignación
                </button>
            </div>
        </form>
    </div>
</div>
