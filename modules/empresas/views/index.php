<?php $empresas = $empresas ?? []; ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
    <div>
        <div style="font-size:13px;color:var(--gc-muted);margin-bottom:4px;"><?= count($empresas) ?> empresa(s) registrada(s)</div>
    </div>
    <a href="/empresas/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:10px 20px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Nueva empresa</a>
</div>

<?php if (isset($error)): ?>
<div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:16px;margin-bottom:20px;color:var(--gc-neg);font-size:14px;">
    Error: <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (empty($empresas)): ?>
<div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;">
    <div style="font-size:48px;margin-bottom:16px;">🏢</div>
    <div style="font-size:18px;font-weight:600;color:var(--gc-ink);margin-bottom:8px;">No hay empresas registradas</div>
    <div style="font-size:14px;color:var(--gc-muted);margin-bottom:24px;">Agrega tu primera empresa para comenzar</div>
    <a href="/empresas/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">+ Agregar primera empresa</a>
</div>
<?php else: ?>
<div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.06);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:var(--gc-bg);">
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Empresa</th>
                <th style="padding:12px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">RUC</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Régimen</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Certificado</th>
                <th style="padding:12px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($empresas as $emp): ?>
            <?php
            $dias = $emp['dias_cert'];
            if (!$emp['cert_hasta'])        { $certBg='var(--gc-surface-2)'; $certColor='var(--gc-label-2)'; $certTxt='Sin certificado'; }
            elseif ($emp['cert_hasta'] < date('Y-m-d')) { $certBg='var(--gc-neg-soft-2)'; $certColor='var(--gc-neg)'; $certTxt='Vencido'; }
            elseif ($dias <= 7)             { $certBg='var(--gc-neg-soft-2)'; $certColor='var(--gc-neg)'; $certTxt="Vence en {$dias}d"; }
            elseif ($dias <= 30)            { $certBg='var(--gc-warn-soft)'; $certColor='var(--gc-warn)'; $certTxt="Vence en {$dias}d"; }
            else                            { $certBg='var(--gc-pos-soft)'; $certColor='var(--gc-pos)'; $certTxt='Vigente'; }
            $regimenes = ['general'=>'General','mype'=>'MYPE','especial'=>'Especial','rus'=>'RUS'];
            ?>
            <tr style="border-top:1px solid var(--gc-surface-2);" onmouseover="this.style.background='var(--gc-bg)'" onmouseout="this.style.background='var(--gc-surface)'">
                <td style="padding:14px 20px;">
                    <div style="font-size:14px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($emp['razon_social']) ?></div>
                    <?php if ($emp['nombre_comercial']): ?>
                    <div style="font-size:12px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($emp['nombre_comercial']) ?></div>
                    <?php endif; ?>
                </td>
                <td style="padding:14px 20px;font-size:14px;color:var(--gc-label);font-family:monospace;"><?= htmlspecialchars($emp['ruc']) ?></td>
                <td style="padding:14px 20px;text-align:center;">
                    <span style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;">
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
                        <a href="/empresas/<?= $emp['id'] ?>" style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Ver</a>
                        <a href="/empresas/<?= $emp['id'] ?>/editar" style="background:var(--gc-bg);color:var(--gc-label);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Editar</a>
                        <a href="/empresas/<?= $emp['id'] ?>/certificado" style="background:var(--gc-warn-soft);color:var(--gc-warn);padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">Cert.</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
