<?php $empresas = $empresas ?? []; ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <div style="font-size:13px;color:#94a3b8;margin-bottom:4px;"><?= count($empresas) ?> empresa(s) registrada(s)</div>
    </div>
    <a href="/empresas/crear" style="background:#1e3a8a;color:white;padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Nueva empresa</a>
</div>

<?php if (isset($error)): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:16px;margin-bottom:20px;color:#991b1b;font-size:14px;">
    Error: <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (empty($empresas)): ?>
<div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;">
    <div style="font-size:48px;margin-bottom:16px;">🏢</div>
    <div style="font-size:18px;font-weight:600;color:#1e293b;margin-bottom:8px;">No hay empresas registradas</div>
    <div style="font-size:14px;color:#94a3b8;margin-bottom:24px;">Agrega tu primera empresa para comenzar</div>
    <a href="/empresas/crear" style="background:#1e3a8a;color:white;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Agregar primera empresa</a>
</div>
<?php else: ?>
<div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Empresa</th>
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">RUC</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Régimen</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Certificado</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($empresas as $emp): ?>
            <?php
            $dias = $emp['dias_cert'];
            if (!$emp['cert_hasta'])        { $certBg='#f1f5f9'; $certColor='#64748b'; $certTxt='Sin certificado'; }
            elseif ($emp['cert_hasta'] < date('Y-m-d')) { $certBg='#fee2e2'; $certColor='#991b1b'; $certTxt='Vencido'; }
            elseif ($dias <= 7)             { $certBg='#fee2e2'; $certColor='#991b1b'; $certTxt="Vence en {$dias}d"; }
            elseif ($dias <= 30)            { $certBg='#fef3c7'; $certColor='#92400e'; $certTxt="Vence en {$dias}d"; }
            else                            { $certBg='#dcfce7'; $certColor='#166534'; $certTxt='Vigente'; }
            $regimenes = ['general'=>'General','mype'=>'MYPE','especial'=>'Especial','rus'=>'RUS'];
            ?>
            <tr style="border-top:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                <td style="padding:14px 20px;">
                    <div style="font-size:14px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($emp['razon_social']) ?></div>
                    <?php if ($emp['nombre_comercial']): ?>
                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($emp['nombre_comercial']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 20px;font-size:14px;color:#475569;font-family:monospace;"><?= htmlspecialchars($emp['ruc']) ?></td>
                <td style="padding:14px 20px;text-align:center;">
                    <span style="background:#eff6ff;color:#1e3a8a;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
                        <?= $regimenes[$emp['regimen']] ?? $emp['regimen'] ?>
                    </span>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <span style="background:<?= $certBg ?>;color:<?= $certColor ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                        <?= $certTxt ?>
                    </span>
                </td>
                <td style="padding:14px 20px;text-align:center;">
                    <div style="display:flex;gap:8px;justify-content:center;">
                        <a href="/empresas/<?= $emp['id'] ?>" style="background:#eff6ff;color:#1e3a8a;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Ver</a>
                        <a href="/empresas/<?= $emp['id'] ?>/editar" style="background:#f8fafc;color:#475569;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Editar</a>
                        <a href="/empresas/<?= $emp['id'] ?>/certificado" style="background:#fef3c7;color:#92400e;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Cert.</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
