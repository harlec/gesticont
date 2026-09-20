<?php
$ventasIdx  = array_column($ventasSinc,  null, 'periodo');
$comprasIdx = array_column($comprasSinc, null, 'periodo');
$resultado  = $_SESSION['sync_resultado'] ?? null;
if ($resultado) unset($_SESSION['sync_resultado']);

function badgeFuente(?string $fuente): string {
    if ($fuente === 'declarado') return '<span style="background:#dcfce7;color:#166534;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">✓ Declarado</span>';
    if ($fuente === 'propuesta') return '<span style="background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">⚠ Propuesta</span>';
    return '';
}
?>
<?php require ROOT . '/views/layout/empresa_tabs.php'; ?>

<div style="max-width:900px;">

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:20px;">
        <div style="padding:18px 24px;border-bottom:1px solid #e2e8f0;background:#eff6ff;display:flex;align-items:center;gap:12px;">
            <span style="font-size:24px;">🔄</span>
            <div>
                <div style="font-weight:700;font-size:16px;color:#1e3a8a;">Sincronizar con SIRE — SUNAT</div>
                <div style="font-size:13px;color:#475569;margin-top:2px;">
                    <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
                </div>
            </div>
        </div>

        <div style="padding:14px 24px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:13px;color:#475569;">
            <strong style="color:#166534;">✓ Declarado</strong> = datos presentados ante SUNAT &nbsp;|&nbsp;
            <strong style="color:#92400e;">⚠ Propuesta</strong> = sugerencia de SUNAT (período no presentado en SIRE)
        </div>

        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/sync/ejecutar"
              onsubmit="return confirmarSync(this);">
            <div style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">¿Qué sincronizar?</label>
                    <select name="tipo" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;background:white;font-family:inherit;">
                        <option value="ambos">Ventas + Compras</option>
                        <option value="ventas">Solo Ventas</option>
                        <option value="compras">Solo Compras</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Rango</label>
                    <select name="rango" id="selRango" onchange="togglePeriodo(this.value)"
                        style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;background:white;font-family:inherit;">
                        <option value="periodo">Un período específico</option>
                        <option value="todo">Últimos 12 meses (solo faltante)</option>
                    </select>
                </div>
                <div id="divPeriodo" style="grid-column:1/-1;">
                    <label style="display:block;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Período</label>
                    <select name="periodo" style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;background:white;font-family:inherit;">
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
                    <div style="font-size:12px;color:#64748b;margin-top:6px;">✓V/✓C = datos guardados · ★ = declarado · ~ = propuesta</div>
                </div>
            </div>
            <div style="padding:0 24px 24px;display:flex;justify-content:flex-end;gap:12px;">
                <a href="/empresas/<?= $empresa['id'] ?>" style="background:#f1f5f9;color:#475569;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">Cancelar</a>
                <button type="submit" id="btnSync"
                    style="background:#1e3a8a;color:white;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                    <span id="btnIcon">🔄</span> <span id="btnText">Sincronizar ahora</span>
                </button>
            </div>
        </form>
    </div>

    <?php if ($resultado): ?>
    <div style="background:white;border-radius:12px;border:1px solid #bbf7d0;overflow:hidden;margin-bottom:20px;">
        <div style="padding:14px 24px;border-bottom:1px solid #e2e8f0;background:#f0fdf4;font-weight:700;color:#166534;">✅ Sincronización completada</div>
        <div style="padding:16px 24px;">
            <?php foreach (['ventas','compras'] as $tipo): ?>
            <?php if (!empty($resultado[$tipo])): ?>
            <div style="font-weight:600;color:#1e293b;margin-bottom:8px;margin-top:<?= $tipo==='compras'?'12px':'0' ?>;"><?= ucfirst($tipo) ?>:</div>
            <?php foreach ($resultado[$tipo] as $p => $r): ?>
            <div style="font-size:13px;color:#475569;margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                <strong><?= substr($p,0,4).'-'.substr($p,4,2) ?>:</strong>
                <?php if (isset($r['error'])): ?>
                    <span style="color:#dc2626;">⚠ <?= htmlspecialchars($r['error']) ?></span>
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
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="padding:14px 24px;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1e293b;">Datos sincronizados</div>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 20px;text-align:left;color:#64748b;font-weight:600;">Período</th>
                    <th style="padding:10px 12px;text-align:right;color:#64748b;font-weight:600;">Ventas</th>
                    <th style="padding:10px 12px;text-align:right;color:#64748b;font-weight:600;">Total ventas</th>
                    <th style="padding:10px 12px;text-align:center;color:#64748b;font-weight:600;">Fuente V</th>
                    <th style="padding:10px 12px;text-align:right;color:#64748b;font-weight:600;">Compras</th>
                    <th style="padding:10px 12px;text-align:right;color:#64748b;font-weight:600;">Total compras</th>
                    <th style="padding:10px 20px;text-align:center;color:#64748b;font-weight:600;">Fuente C</th>
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
                $bg = $idx % 2 === 0 ? 'white' : '#f8fafc';
            ?>
            <tr style="background:<?= $bg ?>;border-top:1px solid #f1f5f9;">
                <td style="padding:10px 20px;font-weight:600;color:#1e293b;"><?= date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')) ?></td>
                <td style="padding:10px 12px;text-align:right;color:#475569;"><?= $v ? $v['cant'].' comp.' : '—' ?></td>
                <td style="padding:10px 12px;text-align:right;color:#1e3a8a;font-weight:600;"><?= $v ? 'S/'.number_format($v['total'],2) : '—' ?></td>
                <td style="padding:10px 12px;text-align:center;"><?= badgeFuente($v['fuente'] ?? null) ?></td>
                <td style="padding:10px 12px;text-align:right;color:#475569;"><?= $c ? $c['cant'].' comp.' : '—' ?></td>
                <td style="padding:10px 12px;text-align:right;color:#166534;font-weight:600;"><?= $c ? 'S/'.number_format($c['total'],2) : '—' ?></td>
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
