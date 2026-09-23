<?php
$regimenes = ['general'=>'Régimen General','mype'=>'MYPE Tributario','especial'=>'Régimen Especial','rus'=>'Nuevo RUS'];
$fmt = fn($v) => number_format((float)$v, 2);
$mesesCorto = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Set','10'=>'Oct','11'=>'Nov','12'=>'Dic'];

// ── Gráfico Ventas vs Compras (SVG a mano — no amerita cargar una
// librería entera para 12 barras dobles) ────────────────────────────────
$maxValor = 0;
foreach ($dash['meses'] as $m) $maxValor = max($maxValor, $m['ventas'], $m['compras']);
$chartW = 900; $chartH = 190; $padL = 10; $padR = 10; $padB = 26;
$colW = ($chartW - $padL - $padR) / 12;
$barW = min(16, $colW * 0.32);
function barY(float $valor, float $max, int $chartH, int $padB): array {
    if ($max <= 0) return [$chartH - $padB, 0];
    $h = ($valor / $max) * ($chartH - $padB - 14);
    return [$chartH - $padB - $h, $h];
}
?>

<!-- Encabezado empresa + botones -->
<div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap;">
    <div style="width:52px;height:52px;background:var(--gc-brand);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:22px;font-weight:700;color:var(--gc-on-brand);flex-shrink:0;">
        <?= strtoupper(substr($empresa['razon_social'], 0, 1)) ?>
    </div>
    <div style="flex:1;min-width:200px;">
        <div style="font-size:20px;font-weight:700;color:var(--gc-ink);"><?= htmlspecialchars($empresa['razon_social']) ?></div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            RUC: <?= htmlspecialchars($empresa['ruc']) ?> &middot; <?= $regimenes[$empresa['regimen']] ?? $empresa['regimen'] ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/empresas/<?= $empresa['id'] ?>/editar"
           style="background:var(--gc-surface-2);color:var(--gc-label);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            ✏️ Editar
        </a>
    </div>
</div>

<!-- KPIs del año -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:16px;">
    <?php
    $kpis = [
        ['Ventas',    $dash['ventas_anio'],   '↗', 'var(--gc-brand)', 'var(--gc-brand-soft)'],
        ['Compras',   $dash['compras_anio'],  '↘', 'var(--gc-neg)',   'var(--gc-neg-soft)'],
        ['Utilidad',  $dash['utilidad_anio'], '⚖',  'var(--gc-pos)',   'var(--gc-pos-soft)'],
        ['IGV Neto',  $dash['igv_neto_anio'], '📄', 'var(--gc-warn)',  'var(--gc-warn-soft)'],
    ];
    foreach ($kpis as [$label, $valor, $icon, $fg, $bg]): ?>
    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);padding:18px 20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
            <span style="font-size:12.5px;color:var(--gc-label-2);font-weight:600;"><?= $label ?></span>
            <span style="width:30px;height:30px;border-radius:9px;background:<?= $bg ?>;color:<?= $fg ?>;display:flex;align-items:center;justify-content:center;font-size:14px;"><?= $icon ?></span>
        </div>
        <div style="font-size:24px;font-weight:700;color:var(--gc-ink);">
            <?= $valor === null ? '—' : 'S/ ' . $fmt($valor) ?>
        </div>
        <div style="font-size:11.5px;color:var(--gc-muted);margin-top:4px;">Año <?= $dash['anio'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Gráfico + pendientes -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px;align-items:stretch;">

    <div style="background:linear-gradient(160deg,var(--gc-brand),color-mix(in srgb,var(--gc-brand) 75%,black));border-radius:14px;padding:20px 22px;color:var(--gc-on-brand);display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;flex-wrap:wrap;gap:8px;">
            <div>
                <div style="font-weight:700;font-size:15px;">Ventas vs Compras</div>
                <div style="font-size:11.5px;color:rgba(255,255,255,0.65);">Año <?= $dash['anio'] ?></div>
            </div>
            <div style="display:flex;gap:14px;font-size:11.5px;">
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:2px;background:var(--gc-accent-light);display:inline-block;"></span>Ventas</span>
                <span style="display:flex;align-items:center;gap:5px;"><span style="width:9px;height:9px;border-radius:2px;background:#f9a8d4;display:inline-block;"></span>Compras</span>
            </div>
        </div>
        <div style="flex:1;display:flex;align-items:center;">
        <?php if ($maxValor > 0): ?>
        <svg viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" style="width:100%;height:auto;" preserveAspectRatio="none">
            <?php for ($g = 1; $g <= 3; $g++):
                $y = $chartH - $padB - ($g * (($chartH - $padB - 14) / 3));
            ?>
            <line x1="<?= $padL ?>" y1="<?= $y ?>" x2="<?= $chartW - $padR ?>" y2="<?= $y ?>" stroke="rgba(255,255,255,0.12)" stroke-dasharray="3,4"/>
            <?php endfor; ?>
            <?php foreach ($dash['meses'] as $i => $m):
                $cx = $padL + $i * $colW + $colW / 2;
                [$yV, $hV] = barY($m['ventas'], $maxValor, $chartH, $padB);
                [$yC, $hC] = barY($m['compras'], $maxValor, $chartH, $padB);
                $activo = $m['mes'] === substr($dash['periodo_activo'], 4, 2);
            ?>
            <rect x="<?= $cx - $barW - 2 ?>" y="<?= $yV ?>" width="<?= $barW ?>" height="<?= max($hV,1.5) ?>" rx="2.5" fill="var(--gc-accent-light)" opacity="<?= $hV>0?1:0.25 ?>"/>
            <rect x="<?= $cx + 2 ?>" y="<?= $yC ?>" width="<?= $barW ?>" height="<?= max($hC,1.5) ?>" rx="2.5" fill="#f9a8d4" opacity="<?= $hC>0?1:0.25 ?>"/>
            <text x="<?= $cx ?>" y="<?= $chartH - 8 ?>" text-anchor="middle" font-size="11" font-weight="<?= $activo ? '700' : '500' ?>" fill="<?= $activo ? '#ffffff' : 'rgba(255,255,255,0.55)' ?>"><?= $mesesCorto[$m['mes']] ?></text>
            <?php endforeach; ?>
        </svg>
        <?php else: ?>
        <div style="color:rgba(255,255,255,0.7);font-size:13px;text-align:center;width:100%;padding:30px 0;">Sin comprobantes sincronizados este año todavía.</div>
        <?php endif; ?>
        </div>
    </div>

    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--gc-line);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-weight:700;font-size:14px;color:var(--gc-ink);">Por clasificar</div>
            <?php if ($dash['pendientes'] > 0): ?>
            <span style="background:var(--gc-neg-soft);color:var(--gc-neg);padding:2px 9px;border-radius:20px;font-size:12px;font-weight:700;"><?= $dash['pendientes'] ?></span>
            <?php else: ?>
            <span style="background:var(--gc-pos-soft);color:var(--gc-pos);padding:2px 9px;border-radius:20px;font-size:12px;font-weight:700;">✓ al día</span>
            <?php endif; ?>
        </div>
        <div style="padding:16px 18px;flex:1;display:flex;flex-direction:column;gap:10px;">
            <?php if ($dash['pendientes'] > 0): ?>
                <div style="font-size:13px;color:var(--gc-label);"><?= $dash['pendientes_ventas'] ?> venta(s) y <?= $dash['pendientes_compras'] ?> compra(s) sin cuenta asignada.</div>
                <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="background:var(--gc-brand);color:var(--gc-on-brand);text-align:center;padding:9px;border-radius:8px;font-size:13px;font-weight:700;text-decoration:none;">Clasificar ahora →</a>
            <?php else: ?>
                <div style="font-size:13px;color:var(--gc-label);">Todos los comprobantes sincronizados ya tienen cuenta asignada.</div>
            <?php endif; ?>
            <div style="border-top:1px solid var(--gc-surface-2);margin-top:2px;padding-top:12px;display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;justify-content:space-between;font-size:12.5px;">
                    <span style="color:var(--gc-label-2);">Por cobrar</span>
                    <span style="font-weight:700;color:var(--gc-ink);"><?= $dash['cxc_cant'] ?> · S/ <?= $fmt($dash['cxc_monto']) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:12.5px;">
                    <span style="color:var(--gc-label-2);">Por pagar</span>
                    <span style="font-weight:700;color:var(--gc-ink);"><?= $dash['cxp_cant'] ?> · S/ <?= $fmt($dash['cxp_monto']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Período activo -->
<div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);padding:18px 20px;margin-bottom:16px;">
    <div style="font-weight:700;font-size:14px;color:var(--gc-ink);margin-bottom:14px;">Período activo — <?= Periodo::etiqueta($dash['periodo_activo']) ?></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
        <div style="display:flex;align-items:center;gap:12px;background:var(--gc-bg);border-radius:10px;padding:12px 14px;">
            <span style="width:34px;height:34px;border-radius:9px;background:var(--gc-brand-soft);color:var(--gc-brand);display:flex;align-items:center;justify-content:center;">↗</span>
            <div><div style="font-size:11.5px;color:var(--gc-label-2);">Ventas del mes</div><div style="font-size:15px;font-weight:700;color:var(--gc-ink);">S/ <?= $fmt($dash['ventas_activo']) ?></div></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;background:var(--gc-bg);border-radius:10px;padding:12px 14px;">
            <span style="width:34px;height:34px;border-radius:9px;background:var(--gc-neg-soft);color:var(--gc-neg);display:flex;align-items:center;justify-content:center;">↘</span>
            <div><div style="font-size:11.5px;color:var(--gc-label-2);">Compras del mes</div><div style="font-size:15px;font-weight:700;color:var(--gc-ink);">S/ <?= $fmt($dash['compras_activo']) ?></div></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;background:var(--gc-bg);border-radius:10px;padding:12px 14px;">
            <span style="width:34px;height:34px;border-radius:9px;background:var(--gc-pos-soft);color:var(--gc-pos);display:flex;align-items:center;justify-content:center;">⚖</span>
            <div><div style="font-size:11.5px;color:var(--gc-label-2);">Resultado del mes</div><div style="font-size:15px;font-weight:700;color:var(--gc-ink);">S/ <?= $fmt($dash['resultado_activo']) ?></div></div>
        </div>
    </div>
</div>

<!-- Balance / Alertas / Certificado -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Balance de comprobación</div>
        <?php if ($dash['balance_cuadra'] === null): ?>
            <div style="font-size:13px;color:var(--gc-muted);">Sin asientos generados aún.</div>
        <?php elseif ($dash['balance_cuadra']): ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-pos);">✓ Cuadra</div>
        <?php else: ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-neg);">⚠ No cuadra</div>
        <?php endif; ?>
        <a href="/empresas/<?= $empresa['id'] ?>/balance" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:6px;display:block;">Ver detalle →</a>
    </div>

    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Alertas</div>
        <?php if (empty($dash['alertas'])): ?>
            <div style="font-size:13px;color:var(--gc-pos);">✓ Sin alertas pendientes</div>
        <?php else: ?>
            <?php foreach ($dash['alertas'] as $al): ?>
            <div style="font-size:12.5px;color:var(--gc-label);margin-bottom:5px;">
                <?= $al['nivel'] === 'danger' ? '🔴' : ($al['nivel'] === 'warning' ? '🟡' : '🔵') ?>
                <?= htmlspecialchars($al['titulo']) ?> — <?= date('d/m', strtotime($al['fecha_vence'])) ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div style="background:var(--gc-surface);border-radius:14px;border:1px solid var(--gc-line);padding:18px 20px;">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Certificado digital</div>
        <?php if ($cert): ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-pos);">✓ Credenciales activas</div>
            <div style="font-size:11.5px;color:var(--gc-muted);margin-top:3px;">Ambiente: <?= ucfirst($cert['ambiente'] ?? 'beta') ?><?= !empty($cert['api_client_id']) ? ' · API SIRE ✓' : '' ?></div>
        <?php else: ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-warn);">⚠ Sin credenciales SOL</div>
        <?php endif; ?>
        <a href="/empresas/<?= $empresa['id'] ?>/certificado" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:6px;display:block;">Gestionar →</a>
    </div>
</div>
