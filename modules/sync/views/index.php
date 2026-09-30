<?php
$ventasIdx  = array_column($ventasSinc,  null, 'periodo');
$comprasIdx = array_column($comprasSinc, null, 'periodo');
$resultado  = $resultadoSync ?? null;   // lo deja el controlador (archivo temporal, no sesión)

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
              onsubmit="return iniciarSync(this);">
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
                        <option value="anio">Todo el año <?= date('Y') ?> (desde enero)</option>
                        <option value="todo">Últimos 12 meses (solo faltante)</option>
                    </select>
                </div>
                <div id="divPeriodo" style="grid-column:1/-1;">
                    <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Período</label>
                    <select name="periodo" style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;background:var(--gc-surface);font-family:inherit;">
                        <?php foreach ($periodos as $p):
                            $etV  = isset($ventasIdx[$p])  ? ' ✓V' : '';
                            $etC  = isset($comprasIdx[$p]) ? ' ✓C' : '';
                            $fV   = $ventasIdx[$p]['fuente']  ?? '';
                            $fC   = $comprasIdx[$p]['fuente'] ?? '';
                            $tagV = $fV === 'declarado' ? '★' : ($fV === 'propuesta' ? '~' : '');
                            $tagC = $fC === 'declarado' ? '★' : ($fC === 'propuesta' ? '~' : '');
                            $label = Periodo::etiqueta($p, true) . $etV . $tagV . $etC . $tagC;
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

    <?php if ($resultado):
        $hayProblemas = false;
        foreach (['ventas','compras'] as $t) foreach ($resultado[$t] ?? [] as $r) if (isset($r['error']) || !empty($r['aviso'])) $hayProblemas = true;
    ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid <?= $hayProblemas ? 'var(--gc-warn)' : 'var(--gc-pos-border)' ?>;overflow:hidden;margin-bottom:20px;">
        <?php if ($hayProblemas): ?>
        <div style="padding:14px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-warn-soft);font-weight:700;color:var(--gc-warn);">⚠ Sincronización con problemas — hay períodos incompletos, no los declares hasta resolverlos</div>
        <?php else: ?>
        <div style="padding:14px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-pos-soft-2);font-weight:700;color:var(--gc-pos);">✅ Sincronización completada</div>
        <?php endif; ?>
        <div style="padding:16px 24px;">
            <?php foreach (['ventas','compras'] as $tipo): ?>
            <?php if (!empty($resultado[$tipo])): ?>
            <div style="font-weight:600;color:var(--gc-ink);margin-bottom:8px;margin-top:<?= $tipo==='compras'?'12px':'0' ?>;"><?= ucfirst($tipo) ?>:</div>
            <?php foreach ($resultado[$tipo] as $p => $r): ?>
            <div style="font-size:13px;color:var(--gc-label);margin-bottom:6px;display:flex;align-items:center;gap:8px;">
                <strong><?= substr($p,0,4).'-'.substr($p,4,2) ?>:</strong>
                <?php if (isset($r['error'])): ?>
                    <span style="color:var(--gc-neg-strong);">⚠ <?= htmlspecialchars($r['error']) ?></span>
                <?php elseif ($r['total'] === 0 && empty($r['aviso'])): ?>
                    <span>Sin datos en SIRE</span>
                <?php else: ?>
                    <?= $r['total'] ?> encontrados · <strong><?= $r['nuevos'] ?> nuevos</strong> · <?= $r['duplicados'] ?> ya existían<?php if (!empty($r['rellenados'])): ?> · <strong><?= $r['rellenados'] ?> completados con RUC de proveedor</strong><?php endif; ?>
                    <?= badgeFuente($r['fuente'] ?? null) ?>
                <?php endif; ?>
                <?php if (!empty($r['aviso'])): ?>
                    <span style="color:var(--gc-neg-strong);font-weight:600;">⚠ INCOMPLETO<?= !empty($r['total_sunat']) ? ' — SUNAT informa ' . (int)$r['total_sunat'] . ', se guardaron ' . (int)$r['total'] : '' ?>. <?= htmlspecialchars($r['aviso']) ?></span>
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
                <td style="padding:10px 20px;font-weight:600;color:var(--gc-ink);"><?= Periodo::etiqueta($p) ?></td>
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

<!-- Progreso de la sincronización por partes -->
<div id="syncOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--gc-surface);border-radius:14px;max-width:560px;width:100%;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.35);">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;">
            <div class="sync-spinner"></div>
            <div>
                <div style="font-weight:700;font-size:17px;color:var(--gc-ink);">Sincronizando con SUNAT…</div>
                <div style="font-size:13px;color:var(--gc-label);margin-top:2px;">Puede tardar varios minutos si hay muchos comprobantes. <strong>No cierres esta ventana.</strong></div>
            </div>
        </div>
        <div style="background:var(--gc-surface-2);border-radius:99px;height:10px;overflow:hidden;">
            <div id="syncBarra" style="height:100%;width:0;background:var(--gc-brand);transition:width .3s;"></div>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gc-muted);margin:6px 0 14px;">
            <span id="syncTareas">Preparando…</span><span id="syncPct">0 %</span>
        </div>
        <div id="syncDetalle" style="font-size:14px;font-weight:600;color:var(--gc-ink);min-height:20px;"></div>
        <div id="syncLog" style="margin-top:12px;max-height:150px;overflow:auto;font-size:12px;color:var(--gc-label);line-height:1.6;"></div>
        <div style="display:flex;justify-content:flex-end;margin-top:16px;">
            <button type="button" id="syncCancelar" onclick="syncCancelado=true;this.disabled=true;this.textContent='Cancelando al terminar este paso…';"
                style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">Cancelar</button>
        </div>
    </div>
</div>
<style>
.sync-spinner{width:30px;height:30px;border:4px solid var(--gc-surface-2);border-top-color:var(--gc-brand);border-radius:50%;animation:syncgiro .9s linear infinite;flex-shrink:0;}
@keyframes syncgiro{to{transform:rotate(360deg)}}
</style>

<script>
function togglePeriodo(val) { document.getElementById('divPeriodo').style.display = val==='periodo'?'block':'none'; }

const SYNC_URL = '/empresas/<?= $empresa['id'] ?>/sync';
const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
const etiquetaPeriodo = p => MESES[parseInt(p.slice(4,6),10)-1] + ' ' + p.slice(0,4);
const num = n => Number(n).toLocaleString('es-PE');
let syncCancelado = false;

// POST con reintentos: un corte de red o un 504 puntual no debe tirar toda la sincronización.
async function syncPost(ruta, datos) {
    let ultimoError = 'sin respuesta';
    for (let intento = 1; intento <= 3; intento++) {
        try {
            const r = await fetch(SYNC_URL + ruta, {method:'POST', body:new URLSearchParams(datos), credentials:'same-origin'});
            const t = await r.text();
            try { return JSON.parse(t); } catch (e) { ultimoError = 'HTTP ' + r.status + (r.status===504 ? ' (el servidor tardó demasiado)' : ''); }
        } catch (e) { ultimoError = 'error de red'; }
        await new Promise(res => setTimeout(res, 1500 * intento));
    }
    return {ok:false, error:`No respondió el servidor en ${ruta} (${ultimoError}). Si acabas de lanzar otra sincronización, espera 2-3 minutos a que termine y reintenta.`};
}

function syncLog(html) {
    const l = document.getElementById('syncLog');
    l.insertAdjacentHTML('beforeend', '<div>' + html + '</div>');
    l.scrollTop = l.scrollHeight;
}

async function iniciarSync(form) {
    const rango = form.rango.value;
    const tipo  = form.tipo.options[form.tipo.selectedIndex].text;
    const msg = rango==='todo' ? `¿Sincronizar ${tipo} de los últimos 12 meses?`
        : rango==='anio' ? `¿Sincronizar ${tipo} de todo el año ${new Date().getFullYear()} (desde enero)? Puede tardar unos minutos.`
        : `¿Sincronizar ${tipo}?`;
    if (!confirm(msg)) return false;

    const datos = Object.fromEntries(new FormData(form));
    const ov = document.getElementById('syncOverlay');
    ov.style.display = 'flex';
    document.getElementById('btnSync').disabled = true;
    syncCancelado = false;

    const plan = await syncPost('/plan', datos);
    if (!plan.ok) {
        document.getElementById('syncDetalle').textContent = '⚠ ' + plan.error;
        document.getElementById('syncDetalle').style.color = 'var(--gc-neg)';
        document.getElementById('syncCancelar').textContent = 'Cerrar';
        document.getElementById('syncCancelar').disabled = false;
        document.getElementById('syncCancelar').onclick = () => location.reload();
        return false;
    }

    const tareas = plan.tareas, n = tareas.length;
    const progreso = (i, fraccion) => {
        const pct = Math.min(100, Math.round(((i + fraccion) / n) * 100));
        document.getElementById('syncBarra').style.width = pct + '%';
        document.getElementById('syncPct').textContent = pct + ' %';
        document.getElementById('syncTareas').textContent = `Tarea ${Math.min(i + 1, n)} de ${n}`;
    };

    for (let i = 0; i < n && !syncCancelado; i++) {
        const t = tareas[i], nombre = `${t.tipo === 'ventas' ? 'ventas' : 'compras'} ${etiquetaPeriodo(t.periodo)}`;
        const det = txt => document.getElementById('syncDetalle').textContent = txt;
        progreso(i, 0);
        let falla = null, total = 0, aviso = null;

        // 1) Descargar de SUNAT, una página por petición
        let page = 1;
        while (page && !syncCancelado) {
            det(`Descargando ${nombre}…` + (total ? ` ${num(total.unicos)} de ${num(total.total)}` : ''));
            const r = await syncPost('/paso', {fase:'descargar', tipo:t.tipo, periodo:t.periodo, page});
            if (!r.ok) { falla = r.error; break; }
            if (r.reintentar) { page = 1; continue; }
            total = r;
            progreso(i, r.total ? 0.5 * Math.min(1, r.unicos / r.total) : 0.5);
            if (r.aviso) aviso = r.aviso;
            page = r.siguiente;
        }

        // 2) Guardar en la base, por lotes
        let offset = 0, fin = false;
        while (!falla && !syncCancelado && !fin) {
            det(`Guardando ${nombre}…` + (total ? ` ${num(Math.min(offset, total.unicos))} de ${num(total.unicos)}` : ''));
            const r = await syncPost('/paso', {fase:'guardar', tipo:t.tipo, periodo:t.periodo, offset});
            if (!r.ok) { falla = r.error; break; }
            progreso(i, 0.5 + 0.5 * (r.total ? Math.min(1, r.guardados / r.total) : 1));
            if (r.fin) { fin = true; syncLog(`✓ ${nombre}: ${num(r.total)} comprobantes (${num(r.nuevos)} nuevos)` + (aviso ? ` — <span style="color:var(--gc-neg);font-weight:600;">⚠ incompleto</span>` : '')); }
            else offset = r.siguiente;
        }
        if (falla) syncLog(`<span style="color:var(--gc-neg);">⚠ ${nombre}: ${falla}</span>`);
    }

    progreso(n, 0);
    document.getElementById('syncDetalle').textContent = syncCancelado ? 'Cancelado.' : 'Listo. Cargando resultados…';
    setTimeout(() => location.href = SYNC_URL + '?ok=1', 700);
    return false;
}
</script>
