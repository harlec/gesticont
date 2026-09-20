<?php
$roles = ['superadmin'=>'Super Admin','contador'=>'Contador','operador'=>'Operador','cliente'=>'Cliente'];
$roleColors = [
    'superadmin' => ['var(--gc-warn-soft)','var(--gc-warn)'],
    'contador'   => ['var(--gc-link-bg)','var(--gc-brand)'],
    'operador'   => ['var(--gc-pos-soft)','var(--gc-pos)'],
    'cliente'    => ['var(--gc-neutral-bg)','var(--gc-neutral-text)'],
];
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div style="font-size:13px;color:var(--gc-muted);"><?= count($usuarios) ?> usuario(s)</div>
    <a href="/usuarios/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Nuevo usuario</a>
</div>

<?php if (isset($_GET['ok'])): ?>
<div style="background:var(--gc-pos-soft);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:var(--gc-pos);font-weight:600;">
    ✓ <?= ['creado'=>'Usuario creado correctamente','desactivado'=>'Usuario desactivado','editado'=>'Cambios guardados'][$_GET['ok']] ?? 'Operación exitosa' ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 18px;margin-bottom:20px;font-size:13px;color:var(--gc-neg);">
    <?= $_GET['error'] === 'self' ? 'No puedes desactivar tu propio usuario.' : 'Ocurrió un error.' ?>
</div>
<?php endif; ?>

<div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
    <?php if (empty($usuarios)): ?>
    <div style="padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:12px;">👥</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-ink);margin-bottom:8px;">No hay usuarios creados aún</div>
        <a href="/usuarios/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:10px 24px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">+ Crear primer usuario</a>
    </div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:var(--gc-bg);">
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Usuario</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Rol</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Empresas</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Último acceso</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
            $rc = $roleColors[$u['rol']] ?? ['var(--gc-neutral-bg)','var(--gc-neutral-text)'];
        ?>
            <tr style="border-top:1px solid var(--gc-surface-2);" onmouseover="this.style.background='var(--gc-bg)'" onmouseout="this.style.background='var(--gc-surface)'">
                <td style="padding:14px 20px;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--gc-brand);display:flex;align-items:center;justify-content:center;color:var(--gc-on-brand);font-size:13px;font-weight:700;flex-shrink:0;">
                            <?= strtoupper(substr($u['nombre'],0,2)) ?>
                        </div>
                        <div>
                            <div style="font-size:14px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($u['nombre']) ?></div>
                            <div style="font-size:12px;color:var(--gc-muted);"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <span style="background:<?= $rc[0] ?>;color:<?= $rc[1] ?>;padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;">
                        <?= $roles[$u['rol']] ?? $u['rol'] ?>
                    </span>
                </td>
                <td style="padding:14px 20px;text-align:center;font-size:20px;font-weight:700;color:var(--gc-brand);">
                    <?= (int)($u['total_empresas'] ?? 0) ?>
                </td>
                <td style="padding:14px 20px;text-align:center;font-size:13px;color:var(--gc-muted);">
                    <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'Nunca' ?>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <div style="display:flex;gap:6px;justify-content:center;">
                        <a href="/usuarios/<?= $u['id'] ?>" style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Gestionar</a>
                        <a href="/usuarios/<?= $u['id'] ?>/editar" style="background:var(--gc-bg);color:var(--gc-label);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Editar</a>
                        <?php if ($u['id'] !== Auth::id()): ?>
                        <a href="/usuarios/<?= $u['id'] ?>/desactivar" onclick="return confirm('¿Desactivar a <?= htmlspecialchars($u['nombre']) ?>?')" style="background:var(--gc-neg-soft);color:var(--gc-neg);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Desactivar</a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
