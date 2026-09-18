<?php
$roles = ['superadmin'=>'Super Admin','contador'=>'Contador','operador'=>'Operador','cliente'=>'Cliente'];
$roleColors = [
    'superadmin' => ['#fef3c7','#92400e'],
    'contador'   => ['#dbeafe','#1e3a8a'],
    'operador'   => ['#dcfce7','#166534'],
    'cliente'    => ['#f3f4f6','#374151'],
];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div style="font-size:13px;color:#94a3b8;"><?= count($usuarios) ?> usuario(s)</div>
    <a href="/usuarios/crear" style="background:#1e3a8a;color:white;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Nuevo usuario</a>
</div>

<?php if (isset($_GET['ok'])): ?>
<div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#166534;font-weight:600;">
    ✓ <?= ['creado'=>'Usuario creado correctamente','desactivado'=>'Usuario desactivado','editado'=>'Cambios guardados'][$_GET['ok']] ?? 'Operación exitosa' ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:#991b1b;">
    <?= $_GET['error'] === 'self' ? 'No puedes desactivar tu propio usuario.' : 'Ocurrió un error.' ?>
</div>
<?php endif; ?>

<div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
    <?php if (empty($usuarios)): ?>
    <div style="padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:12px;">👥</div>
        <div style="font-size:16px;font-weight:600;color:#1e293b;margin-bottom:8px;">No hay usuarios creados aún</div>
        <a href="/usuarios/crear" style="background:#1e3a8a;color:white;padding:10px 24px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">+ Crear primer usuario</a>
    </div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Usuario</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Rol</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Empresas</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Último acceso</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            $rc = $roleColors[$u['rol']] ?? ['#f3f4f6','#374151'];
        ?>
            <tr style="border-top:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <td style="padding:14px 20px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:#1e3a8a;display:flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($u['nombre'],0,2)) ?>
                        </div>
                        <div>
                            <div style="font-size:14px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($u['nombre']) ?></div>
                            <div style="font-size:12px;color:#94a3b8;"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <span style="background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;">
                        <?= $roles[$u['rol']] ?? $u['rol'] ?>
                    </span>
                </td>
                <td style="padding:14px 20px;text-align:center;font-size:20px;font-weight:700;color:#1e3a8a;">
                    <?= (int)($u['total_empresas'] ?? 0) ?>
                </td>
                <td style="padding:14px 20px;text-align:center;font-size:13px;color:#94a3b8;">
                    <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <a href="/usuarios/<?= $u['id'] ?>" style="background:#eff6ff;color:#1e3a8a;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Gestionar</a>
                        <a href="/usuarios/<?= $u['id'] ?>/editar" style="background:#f8fafc;color:#475569;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Editar</a>
                        <?php if ($u['id'] !== Auth::id()): ?>
                        <a href="/usuarios/<?= $u['id'] ?>/desactivar" onclick="return confirm('¿Desactivar a <?= htmlspecialchars($u['nombre']) ?>?')" style="background:#fef2f2;color:#991b1b;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Desactivar</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
