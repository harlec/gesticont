<?php
$certMap = [
    'vigente'    => ['var(--gc-pos-soft)','var(--gc-pos)','Al día'],
    'por_vencer' => ['var(--gc-warn-soft)','var(--gc-warn)','Por vencer'],
    'critico'    => ['var(--gc-neg-soft-2)','var(--gc-neg)','Crítico'],
    'vencido'    => ['var(--gc-neg-soft-2)','var(--gc-neg)','Vencido'],
    'sin_cert'   => ['var(--gc-surface-2)','var(--gc-label-2)','Sin cert.'],
];
?>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px;">
    <div style="background:var(--gc-surface);border-radius:14px;padding:18px 20px;border:1px solid var(--gc-line);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Empresas activas</div>
        <div style="font-size:30px;font-weight:700;color:var(--gc-ink);"><?= $dash['total_empresas'] ?></div>
        <a href="/empresas" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:6px;display:inline-block;">Ver todas →</a>
    </div>
    <div style="background:var(--gc-surface);border-radius:14px;padding:18px 20px;border:1px solid var(--gc-line);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Por clasificar</div>
        <div style="font-size:30px;font-weight:700;color:<?= $dash['total_pendientes'] > 0 ? 'var(--gc-neg)' : 'var(--gc-pos)' ?>;"><?= $dash['total_pendientes'] ?></div>
        <div style="font-size:12px;color:var(--gc-muted);margin-top:6px;">en todas tus empresas</div>
    </div>
    <div style="background:var(--gc-surface);border-radius:14px;padding:18px 20px;border:1px solid var(--gc-line);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Períodos sin cerrar</div>
        <div style="font-size:30px;font-weight:700;color:<?= count($dash['empresas_sin_cerrar']) > 0 ? 'var(--gc-warn)' : 'var(--gc-pos)' ?>;"><?= count($dash['empresas_sin_cerrar']) ?></div>
        <div style="font-size:12px;color:var(--gc-muted);margin-top:6px;">de años anteriores</div>
    </div>
    <div style="background:var(--gc-surface);border-radius:14px;padding:18px 20px;border:1px solid var(--gc-line);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Alertas pendientes</div>
        <div style="font-size:30px;font-weight:700;color:<?= $dash['total_alertas'] > 0 ? 'var(--gc-neg-strong-2)' : 'var(--gc-success-strong)' ?>;"><?= $dash['total_alertas'] ?></div>
        <a href="/alertas" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:6px;display:inline-block;">Ver alertas →</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:16px;">
    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:15px;color:var(--gc-ink);">Mis empresas</span>
            <span style="font-size:11.5px;color:var(--gc-muted);">ordenadas por lo más urgente</span>
        </div>
        <?php if (empty($dash['empresas'])): ?>
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
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Por clasificar</th>
                    <th style="padding:10px 12px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Certificado</th>
                    <th style="padding:10px 20px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dash['empresas'] as $emp):
                $cert = $certMap[$emp['estado_cert']] ?? $certMap['sin_cert'];
            ?>
                <tr style="border-top:1px solid var(--gc-surface-2);" onmouseover="this.style.background='var(--gc-bg)'" onmouseout="this.style.background='var(--gc-surface)'">
                    <td style="padding:14px 20px;">
                        <div style="font-size:14px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($emp['razon_social']) ?></div>
                        <div style="font-size:12px;color:var(--gc-muted);margin-top:2px;">
                            RUC: <?= htmlspecialchars($emp['ruc']) ?>
                            <?php if ($emp['anios_sin_cerrar'] !== ''): ?>
                            · <span style="color:var(--gc-warn);font-weight:600;">sin cerrar <?= htmlspecialchars($emp['anios_sin_cerrar']) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td style="padding:14px 12px;text-align:center;">
                        <?php if ((int)$emp['pendientes'] > 0): ?>
                        <span style="background:var(--gc-neg-soft);color:var(--gc-neg);padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;"><?= $emp['pendientes'] ?></span>
                        <?php else: ?>
                        <span style="color:var(--gc-pos);font-size:13px;">✓</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:14px 12px;text-align:center;">
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
        <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;font-size:15px;color:var(--gc-ink);">⚠ Alertas activas</div>
            <?php if (empty($dash['alertas'])): ?>
            <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:14px;">✅ Sin alertas pendientes</div>
            <?php else: ?>
            <?php foreach ($dash['alertas'] as $al): ?>
            <div style="padding:14px 20px;border-top:1px solid var(--gc-surface-2);">
                <div style="font-size:13px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($al['razon_social']) ?></div>
                <div style="font-size:12px;color:var(--gc-label-2);margin-top:2px;"><?= htmlspecialchars($al['titulo']) ?></div>
                <div style="font-size:11px;color:var(--gc-neg-strong-2);margin-top:2px;">Vence: <?= date('d/m/Y', strtotime($al['fecha_vence'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($dash['empresas_sin_cerrar'])): ?>
        <div style="background:var(--gc-warn-soft);border:1px solid var(--gc-warn-border);border-radius:14px;padding:18px 20px;">
            <div style="color:var(--gc-warn);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">🔒 Períodos por cerrar</div>
            <?php foreach ($dash['empresas_sin_cerrar'] as $e): ?>
            <div style="font-size:13px;color:var(--gc-label);margin-bottom:6px;">
                <a href="/empresas/<?= $e['id'] ?>/cierre" style="color:var(--gc-warn);font-weight:600;text-decoration:none;"><?= htmlspecialchars($e['razon_social']) ?></a>
                — <?= htmlspecialchars($e['anios_sin_cerrar']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="background:var(--gc-brand);border-radius:14px;padding:20px;">
            <div style="color:rgba(255,255,255,0.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Estado del sistema</div>
            <div style="color:var(--gc-on-brand);font-size:13px;margin-bottom:6px;">✓ GestiCont activo</div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;">Todos los períodos de años anteriores están cerrados.</div>
        </div>
        <?php endif; ?>
    </div>
</div>
