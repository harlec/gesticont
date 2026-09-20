<?php
$totalEmpresas = $totalEmpresas ?? 0;
$totalAlertas  = $totalAlertas ?? 0;
$empresas      = $empresas ?? [];
$alertas       = $alertas ?? [];
?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div style="background:var(--gc-surface);border-radius:12px;padding:20px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Empresas activas</div>
        <div style="font-size:36px;font-weight:700;color:var(--gc-brand);font-family:Georgia,serif;"><?= $totalEmpresas ?></div>
        <a href="/empresas" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:8px;display:inline-block;">Ver todas →</a>
    </div>
    <div style="background:var(--gc-surface);border-radius:12px;padding:20px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Alertas pendientes</div>
        <div style="font-size:36px;font-weight:700;font-family:Georgia,serif;color:<?= $totalAlertas > 0 ? 'var(--gc-neg-strong-2)' : 'var(--gc-success-strong)' ?>;"><?= $totalAlertas ?></div>
        <a href="/alertas" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:8px;display:inline-block;">Ver alertas →</a>
    </div>
    <div style="background:var(--gc-surface);border-radius:12px;padding:20px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Acciones rápidas</div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
            <a href="/empresas/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;text-align:center;">+ Nueva empresa</a>
            <a href="/usuarios" style="background:var(--gc-brand-soft);color:var(--gc-brand);padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;text-align:center;">+ Nuevo usuario</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;">
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:15px;color:var(--gc-ink);">Mis empresas</span>
            <a href="/empresas" style="font-size:13px;color:var(--gc-link);text-decoration:none;">Ver todas →</a>
        </div>
        <?php if (empty($empresas)): ?>
        <div style="padding:50px;text-align:center;color:var(--gc-muted);">
            <div style="font-size:40px;margin-bottom:12px;">🏢</div>
            <div style="font-size:14px;margin-bottom:16px;">No hay empresas registradas aún.</div>
            <a href="/empresas/crear" style="background:var(--gc-brand);color:var(--gc-on-brand);padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">+ Agregar primera empresa</a>
        </div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:var(--gc-bg);">
                    <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Empresa</th>
                    <th style="padding:10px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Certificado</th>
                    <th style="padding:10px 20px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $emp):
                $certMap = [
                    'vigente'    => ['var(--gc-pos-soft)','var(--gc-pos)','Al día'],
                    'por_vencer' => ['var(--gc-warn-soft)','var(--gc-warn)','Por vencer'],
                    'critico'    => ['var(--gc-neg-soft-2)','var(--gc-neg)','Crítico'],
                    'vencido'    => ['var(--gc-neg-soft-2)','var(--gc-neg)','Vencido'],
                    'sin_cert'   => ['var(--gc-surface-2)','var(--gc-label-2)','Sin cert.'],
                ];
                $cert = $certMap[$emp['estado_cert']] ?? $certMap['sin_cert'];
            ?>
                <tr style="border-top:1px solid var(--gc-surface-2);" onmouseover="this.style.background='var(--gc-bg)'" onmouseout="this.style.background='var(--gc-surface)'">
                    <td style="padding:14px 20px;">
                        <div style="font-size:14px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($emp['razon_social']) ?></div>
                        <div style="font-size:12px;color:var(--gc-muted);margin-top:2px;">RUC: <?= htmlspecialchars($emp['ruc']) ?></div>
                    </td>
                    <td style="padding:14px 20px;text-align:center;">
                        <span style="background:<?= $cert[0] ?>;color:<?= $cert[1] ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;"><?= $cert[2] ?></span>
                    </td>
                    <td style="padding:14px 20px;text-align:right;">
                        <a href="/empresas/<?= $emp['id'] ?>" style="color:var(--gc-link);font-size:13px;text-decoration:none;font-weight:600;">Ver →</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;font-size:15px;color:var(--gc-ink);">⚠ Alertas activas</div>
            <?php if (empty($alertas)): ?>
            <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:14px;">✅ Sin alertas pendientes</div>
            <?php else: ?>
            <?php foreach ($alertas as $al): ?>
            <div style="padding:14px 20px;border-top:1px solid var(--gc-surface-2);">
                <div style="font-size:13px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($al['razon_social']) ?></div>
                <div style="font-size:12px;color:var(--gc-label-2);margin-top:2px;"><?= htmlspecialchars($al['titulo']) ?></div>
                <div style="font-size:11px;color:var(--gc-neg-strong-2);margin-top:2px;">Vence: <?= date('d/m/Y', strtotime($al['fecha_vence'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="background:var(--gc-brand);border-radius:12px;padding:20px;">
            <div style="color:rgba(255,255,255,0.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Estado del sistema</div>
            <div style="color:var(--gc-on-brand);font-size:13px;margin-bottom:6px;">✓ GestiCont v1.0 activo</div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;margin-bottom:4px;">PHP <?= phpversion() ?></div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;">gesticont.harlec.com.pe</div>
        </div>
    </div>
</div>
