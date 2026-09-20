<?php
$ventasIdx  = array_column($ventasSinc,  null, 'periodo');
$comprasIdx = array_column($comprasSinc, null, 'periodo');
$resultado  = $_SESSION['sync_resultado'] ?? null;
if ($resultado) unset($_SESSION['sync_resultado']);

function badgeFuente(?string $fuente): string {
    if ($fuente === 'declarado') return '<span style="background:var(--gc-pos-soft);color:var(--gc-pos);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">✓ Declarado</span>';
    if ($fuente === 'propuesta') return '<span style="background:var(--gc-warn-soft);color:var(--gc-warn);padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">⚠ Propuesta</span>';
    return '';
}
?>

<div class="gc-content gc-w-content">
    <?php $subtabActiva = 'sync'; require ROOT . '/views/layout/comprobantes_subtabs.php'; ?>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:20px;">
        <div style="padding:18px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-brand-soft);display:flex;align-items:center;gap:12px;">
            <span style="font-size:24px;">🔄</span>
            <div>
                <div style="font-weight:700;font-size:16px;color:var(--gc-brand);">Sincronizar con SIRE — SUNAT</div>
                <div style="font-size:13px;color:var(--gc-label);margin-top:2px;">
                    <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
                </div>
            </div>
        </div>

        <div style="padding:14px 24px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-size:13px;color:var(--gc-label);">
            <strong style="color:var(--gc-pos);">✓ Declarado</strong> = datos presentados ante SUNAT &nbsp;|&nbsp;
            <strong style="color:var(--gc-warn);">⚠ Propuesta</strong> = sugerencia de SUNAT (período no presentado en SIRE)
        </div>

        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/sync/ejecutar"
              onsubmit="return confirmarSync(this);">
            <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">¿Qué sincronizar?</label>
                    <select name="tipo" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-surface);font-family:inherit;">
                        <option value="ambos">Ventas + Compras</option>
                        <option value="ventas">Solo Ventas</option>
                        <option value="compras">Solo Compras</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Rango</label>
                    <select name="rango" id="selRango" onchange="togglePeriodo(this.value)"
                        style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-surface);font-family:inherit;">
                        <option value="periodo">Un período específico</option>
                        <option value="todo">Últimos 12 meses (solo faltante)</option>
                    </select>
                </div>
                <div id="divPeriodo" style="grid-column:1/-1;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Período</label>
                    <select name="periodo" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-surface);font-family:inherit;">
                        <?php foreach ($periodos as $p):
                            $ym   = substr($p,0,4).'-'.substr($p,4,2);
                            $etV  = isset($ventasIdx[$p])  ? ' ✓V' : '';
                            $etC  = isset($comprasIdx[$p]) ? ' ✓C' : '';
                            $fV   = $ventasIdx[$p]['fuente']  ?? '';
                            $fC   = $comprasIdx[$p]['fuente'] ?? '';
                            $tagV = $fV === 'declarado' ? '★' : ($fV === 'propuesta' ? '~' : '');
                            $tagC = $fC === 'declarado' ? '★' : ($fC === 'propuesta' ? '~' : '');
                            $label = date('F Y', strtotime($ym.'-01')) . $etV . $tagV . $etC . $tagC;
                        ?>
                        <option value="<?= $p ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:12px;color:var(--gc-label-2);margin-top:6px;">✓V/✓C = datos guardados · ★ = declarado · ~ = propuesta</div>
                </div>
            </div>
            <div style="padding:0 24px 24px;display:flex;justify-content:flex-end;gap:12px;">
                <a href="/empresas/<?= $empresa['id'] ?>" style="background:var(--gc-surface-2);color:var(--gc-label);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
                <button type="submit" id="btnSync"
                    style="background:var(--gc-brand);color:var(--gc-on-brand);padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                    <span id="btnIcon">🔄</span> <span id="btnText">Sincronizar ahora</span>
                </button>
            </div>
        </form>
    </div>

    <?php if ($resultado): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-pos-border);overflow:hidden;margin-bottom:20px;">
        <div style="padding:14px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-pos-soft-2);font-weight:700;color:var(--gc-pos);">✅ Sincronización completada</div>
        <div style="padding:16px 24px;">
            <?php foreach (['ventas','compras'] as $tipo): ?>
            <?php if (!empty($resultado[$tipo])): ?>
            <div style="font-weight:600;color:var(--gc-ink);margin-bottom:8px;margin-top:<?= $tipo==='compras'?'12px':'0' ?>;"><?= ucfirst($tipo) ?>:</div>
            <?php foreach ($resultado[$tipo] as $p => $r): ?>
            <div style="font-size:13px;color:var(--gc-label);margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                <strong><?= substr($p,0,4).'-'.substr($p,4,2) ?>:</strong>
                <?php if (isset($r['error'])): ?>
                    <span style="color:var(--gc-neg-strong);">⚠ <?= htmlspecialchars($r['error']) ?></span>
                <?php elseif ($r['total'] === 0): ?>
                    <span>Sin datos en SIRE</span>
                <?php else: ?>
                    <?= $r['total'] ?> encontrados · <strong><?= $r['nuevos'] ?> nuevos</strong> · <?= $r['duplicados'] ?> ya existían
                    <?= badgeFuente($r['fuente'] ?? null) ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($ventasSinc) || !empty($comprasSinc)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:14px 24px;border-bottom:1px solid var(--gc-line);font-weight:700;color:var(--gc-ink);">Datos sincronizados</div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gc-bg);">
                    <th style="padding:10px 20px;text-align:left;color:var(--gc-label-2);font-weight:600;">Período</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gc-label-2);font-weight:600;">Ventas</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gc-label-2);font-weight:600;">Total ventas</th>
                    <th style="padding:10px 12px;text-align:center;color:var(--gc-label-2);font-weight:600;">Fuente V</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gc-label-2);font-weight:600;">Compras</th>
                    <th style="padding:10px 12px;text-align:right;color:var(--gc-label-2);font-weight:600;">Total compras</th>
                    <th style="padding:10px 20px;text-align:center;color:var(--gc-label-2);font-weight:600;">Fuente C</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $todos = array_unique(array_merge(
                array_column($ventasSinc,'periodo'),
                array_column($comprasSinc,'periodo')
            ));
            rsort($todos);
            foreach ($todos as $idx => $p):
                $v  = $ventasIdx[$p]  ?? null;
                $c  = $comprasIdx[$p] ?? null;
                $bg = $idx % 2 === 0 ? 'var(--gc-surface)' : 'var(--gc-bg)';
            ?>
            <tr style="background:<?= $bg ?>;border-top:1px solid var(--gc-surface-2);">
                <td style="padding:10px 20px;font-weight:600;color:var(--gc-ink);"><?= date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')) ?></td>
                <td style="padding:10px 12px;text-align:right;color:var(--gc-label);"><?= $v ? $v['cant'].' comp.' : '—' ?></td>
                <td style="padding:10px 12px;text-align:right;color:var(--gc-brand);font-weight:600;"><?= $v ? 'S/'.number_format($v['total'],2) : '—' ?></td>
                <td style="padding:10px 12px;text-align:center;"><?= badgeFuente($v['fuente'] ?? null) ?></td>
                <td style="padding:10px 12px;text-align:right;color:var(--gc-label);"><?= $c ? $c['cant'].' comp.' : '—' ?></td>
                <td style="padding:10px 12px;text-align:right;color:var(--gc-pos);font-weight:600;"><?= $c ? 'S/'.number_format($c['total'],2) : '—' ?></td>
                <td style="padding:10px 20px;text-align:center;"><?= badgeFuente($c['fuente'] ?? null) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function togglePeriodo(val) { document.getElementById('divPeriodo').style.display = val==='todo'?'none':'block'; }
function confirmarSync(form) {
    const rango = form.rango.value;
    const tipo  = form.tipo.options[form.tipo.selectedIndex].text;
    if (!confirm(rango==='todo' ? `¿Sincronizar ${tipo} de los últimos 12 meses?` : `¿Sincronizar ${tipo}?`)) return false;
    document.getElementById('btnIcon').textContent='⏳';
    document.getElementById('btnText').textContent='Sincronizando...';
    document.getElementById('btnSync').disabled=true;
    return true;
}
</script>
