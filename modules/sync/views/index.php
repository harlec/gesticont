<?php
/**
 * Sincronizar SIRE (rediseño). Variables: $empresa, $meses (una fila por mes), $solUsuario, $resultado (último resumen).
 * El motor por pasos (plan / paso) es el mismo de siempre; aquí solo cambia la forma de pedirlo.
 */
$id = (int)$empresa['id'];
$subtabActiva = 'sync'; require ROOT . '/views/layout/comprobantes_subtabs.php';
$resultado = $resultadoSync ?? null;
$faltan = array_values(array_filter($meses, fn($m) => $m['v'] === 0 && $m['c'] === 0));
$ultimoFalta = $faltan && $faltan[0]['periodo'] === array_key_first($meses) ? $faltan[0] : null;   // ¿falta justo el mes más reciente?
$chipFuente = function (?string $f) { return $f === 'declarado' ? '<span class="gc-chip ok" title="Datos presentados ante SUNAT">Declarado</span>' : ($f === 'propuesta' ? '<span class="gc-chip warn" title="Propuesta de SUNAT: aún no presentada en SIRE">Propuesta</span>' : ''); };
?>
<?php if (!empty($_SESSION['sync_error'])): ?>
<div class="gc-alert neg"><span class="msg"><?= htmlspecialchars($_SESSION['sync_error']) ?></span></div>
<?php unset($_SESSION['sync_error']); endif; ?>

<?php if ($resultado):
    $hayProblemas = false;
    foreach (['ventas', 'compras'] as $t) foreach ($resultado[$t] ?? [] as $r) if (isset($r['error']) || !empty($r['aviso'])) $hayProblemas = true;
?>
<section class="gc-card pad gc-stack" style="gap:10px;border-color:<?= $hayProblemas ? 'var(--gc-warn-border)' : 'var(--gc-pos-border)' ?>;">
    <b style="font-size:18px;color:<?= $hayProblemas ? 'var(--gc-warn)' : 'var(--gc-pos)' ?>;"><?= $hayProblemas ? 'La descarga terminó con problemas: hay meses incompletos, no los declares hasta resolverlos' : 'Descarga completada' ?></b>
    <?php foreach (['ventas' => 'Ventas', 'compras' => 'Compras'] as $t => $tl): foreach ($resultado[$t] ?? [] as $p => $r): ?>
    <div style="font-size:16px;"><b><?= $tl ?> <?= Periodo::etiqueta($p, true) ?>:</b>
        <?php if (isset($r['error'])): ?><span style="color:var(--gc-neg);"><?= htmlspecialchars($r['error']) ?></span>
        <?php elseif ($r['total'] === 0 && empty($r['aviso'])): ?>SUNAT no tiene datos de este mes todavía.
        <?php else: ?><?= number_format($r['total']) ?> comprobantes · <b><?= number_format($r['nuevos']) ?> nuevos</b> · <?= number_format($r['duplicados']) ?> ya estaban<?= !empty($r['rellenados']) ? ' · ' . $r['rellenados'] . ' completados con RUC' : '' ?>
            <?php if (!empty($r['aviso'])): ?><div style="color:var(--gc-neg);font-weight:600;">Incompleto<?= !empty($r['total_sunat']) ? ': SUNAT informa ' . (int)$r['total_sunat'] . ' y se guardaron ' . (int)$r['total'] : '' ?>. <?= htmlspecialchars($r['aviso']) ?></div><?php endif; ?>
        <?php endif; ?></div>
    <?php endforeach; endforeach; ?>
</section>
<?php endif; ?>

<section class="gc-card pad">
    <div class="gc-row between">
        <div class="gc-stack" style="gap:4px;">
            <?php if ($solUsuario): ?>
                <span class="gc-chip ok" style="align-self:flex-start;">Clave SOL conectada</span>
                <span class="gc-sub">Usuario secundario: <b class="mono" style="color:var(--gc-ink);"><?= htmlspecialchars($solUsuario) ?></b></span>
            <?php else: ?>
                <span class="gc-chip neg" style="align-self:flex-start;">Falta la clave SOL</span>
                <span class="gc-sub">Sin ella no se puede descargar de SUNAT. <a href="/empresas/<?= $id ?>/certificado">Configurarla</a></span>
            <?php endif; ?>
        </div>
        <?php if ($solUsuario && $faltan): ?>
        <button type="button" class="gc-btn gc-btn-primary gc-btn-lg" onclick="sincronizar(<?= htmlspecialchars(json_encode($ultimoFalta ? [$ultimoFalta['periodo']] : array_column($faltan, 'periodo'))) ?>)">
            <?= $ultimoFalta ? 'Descargar ' . Periodo::etiqueta($ultimoFalta['periodo'], true) : 'Descargar lo que falta (' . count($faltan) . ' meses)' ?>
        </button>
        <?php endif; ?>
    </div>
</section>

<section class="gc-card clip">
    <div class="gc-table-wrap">
    <table class="gc-table" style="min-width:900px;">
        <thead><tr><th>Mes</th><th>Ventas</th><th>Compras</th><th>Última descarga</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($meses as $m):
            $hay = $m['v'] > 0 || $m['c'] > 0;
            $per = "/empresas/{$id}/periodo?periodo={$m['periodo']}&volver=" . rawurlencode("/empresas/{$id}/ventas");
        ?>
        <tr>
            <td><a href="<?= htmlspecialchars($per) ?>" style="font-weight:700;font-size:17px;"><?= Periodo::etiqueta($m['periodo'], true) ?></a></td>
            <?php foreach ([['v', 'pv', 'fv'], ['c', 'pc', 'fc']] as [$n, $pn, $fn]): ?>
            <td>
                <?php if ($m[$n] > 0): ?>
                    <div style="font-weight:600;"><?= number_format($m[$n]) ?> comprobante<?= $m[$n] === 1 ? '' : 's' ?> <?= $chipFuente($m[$fn]) ?></div>
                    <?php if ($m[$pn] > 0): ?><span class="gc-chip warn" style="margin-top:4px;"><?= number_format($m[$pn]) ?> por clasificar</span><?php endif; ?>
                <?php else: ?><span class="gc-chip warn">Sin descargar</span><?php endif; ?>
            </td>
            <?php endforeach; ?>
            <td class="gc-sub"><?= $m['sync'] ? date('d/m/Y', strtotime($m['sync'])) : '—' ?></td>
            <td class="r"><?php if ($solUsuario): ?><button type="button" class="gc-btn <?= $hay ? '' : 'gc-btn-primary' ?>" onclick="sincronizar(['<?= $m['periodo'] ?>'])"><?= $hay ? 'Volver a descargar' : 'Descargar' ?></button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="padding:14px 24px;border-top:1px solid var(--gc-line);background:var(--gc-bg);font-size:15px;color:var(--gc-label-2);">
        SUNAT publica la propuesta de cada mes desde el día 1 del mes siguiente. Volver a descargar no borra lo que ya clasificaste.
    </div>
</section>

<?php if (\Auth::isSuperadmin()): ?>
<div class="gc-sub" style="font-size:14px;text-align:right;">
    Solo superadmin ·
    <a href="/empresas/<?= $id ?>/depurar" style="color:var(--gc-neg);">Depurar datos anteriores a <?= Periodo::etiqueta(Periodo::minimo(), true) ?></a> ·
    <a href="/empresas/<?= $id ?>/sync/diagnostico?periodo=<?= Periodo::ultimoCerrado() ?>&tipo=ventas" target="_blank" rel="noopener">Diagnóstico SIRE</a>
</div>
<?php endif; ?>

<!-- Progreso de la descarga (por pasos cortos: evita el 504 del servidor) -->
<div class="gc-modal-back" id="syncOverlay" style="align-items:center;padding-top:16px;">
  <div class="gc-modal sm">
    <h2>Sincronizando con SUNAT…</h2>
    <p class="gc-sub" style="margin:6px 0 16px;">Puede tardar varios minutos si hay muchos comprobantes. <b style="color:var(--gc-ink);">No cierres esta ventana.</b></p>
    <div class="gc-progress"><div id="syncBarra"></div></div>
    <div class="gc-row between" style="font-size:15px;color:var(--gc-label-2);margin:6px 0 14px;"><span id="syncTareas">Preparando…</span><span id="syncPct">0 %</span></div>
    <div id="syncDetalle" style="font-size:17px;font-weight:600;min-height:24px;"></div>
    <div id="syncLog" style="margin-top:12px;max-height:150px;overflow:auto;font-size:15px;color:var(--gc-label);line-height:1.6;"></div>
    <div class="gc-row" style="justify-content:flex-end;margin-top:16px;"><button type="button" class="gc-btn" id="syncCancelar" onclick="syncCancelado=true;this.disabled=true;this.textContent='Cancelando al terminar este paso…';">Cancelar</button></div>
  </div>
</div>

<script>
const SYNC_URL = '/empresas/<?= $id ?>/sync';
const MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
const etiquetaPeriodo = p => MESES[parseInt(p.slice(4,6),10)-1] + ' ' + p.slice(0,4);
const num = n => Number(n).toLocaleString('es-PE');
let syncCancelado = false;

// Un 502/503/504 NO se reintenta: nginx corta al navegador pero PHP sigue trabajando en el servidor, y reintentar
// solo apila más trabajo encima. Solo se repite una vez si fue un corte de red (la petición ni siquiera llegó).
async function syncPost(ruta, datos) {
    const cuerpo = new URLSearchParams();
    Object.entries(datos).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => cuerpo.append(k + '[]', x)) : cuerpo.append(k, v));
    for (let intento = 1; intento <= 2; intento++) {
        try {
            const r = await fetch(SYNC_URL + ruta, { method: 'POST', body: cuerpo, credentials: 'same-origin' });
            const t = await r.text();
            try { return JSON.parse(t); } catch (e) {
                const gw = [502, 503, 504].includes(r.status);
                return { ok: false, gateway: gw, error: `El servidor respondió HTTP ${r.status}` + (gw ? ' (tardó demasiado en ' + ruta + ')' : '') };
            }
        } catch (e) { if (intento === 2) return { ok: false, error: 'Error de red en ' + ruta }; await new Promise(res => setTimeout(res, 1500)); }
    }
}
function syncLog(html) { const l = document.getElementById('syncLog'); l.insertAdjacentHTML('beforeend', '<div>' + html + '</div>'); l.scrollTop = l.scrollHeight; }

async function sincronizar(periodos) {
    const nombres = periodos.map(etiquetaPeriodo).join(', ');
    if (!confirm('¿Descargar de SUNAT ' + (periodos.length === 1 ? 'el mes ' : 'los meses ') + nombres + '? Lo que ya clasificaste no se borra.')) return;
    const ov = document.getElementById('syncOverlay'); ov.classList.add('is-open'); syncCancelado = false;

    const plan = await syncPost('/plan', { tipo: 'ambos', periodos });
    const cerrar = txt => { const d = document.getElementById('syncDetalle'); d.textContent = txt; d.style.color = 'var(--gc-neg)'; const b = document.getElementById('syncCancelar'); b.disabled = false; b.textContent = 'Cerrar'; b.onclick = () => location.reload(); };
    if (!plan.ok) { cerrar('⚠ ' + plan.error); return; }

    const tareas = plan.tareas, n = tareas.length; let detenido = false, ultimaFalla = '';
    const progreso = (i, f) => { const pct = Math.min(100, Math.round(((i + f) / n) * 100)); document.getElementById('syncBarra').style.width = pct + '%'; document.getElementById('syncPct').textContent = pct + ' %'; document.getElementById('syncTareas').textContent = `Tarea ${Math.min(i + 1, n)} de ${n}`; };

    for (let i = 0; i < n && !syncCancelado && !detenido; i++) {
        const t = tareas[i], nombre = `${t.tipo} ${etiquetaPeriodo(t.periodo)}`, det = x => document.getElementById('syncDetalle').textContent = x;
        progreso(i, 0); let falla = null, total = 0, aviso = null;

        let page = 1;                                         // 1) descargar de SUNAT, una página por petición
        while (page && !syncCancelado) {
            det(`Descargando ${nombre}…` + (total ? ` ${num(total.unicos)} de ${num(total.total)}` : ''));
            const r = await syncPost('/paso', { fase: 'descargar', tipo: t.tipo, periodo: t.periodo, page });
            if (!r.ok) { falla = r.error; if (r.gateway || r.fatal) detenido = true; break; }
            if (r.reintentar) { page = 1; continue; }
            total = r; progreso(i, r.total ? 0.5 * Math.min(1, r.unicos / r.total) : 0.5);
            if (r.aviso) aviso = r.aviso;
            syncLog(`· página ${r.pagina}: ${num(r.recibidas)} filas en ${(r.ms / 1000).toFixed(1)} s (SUNAT ${(r.ms_sunat / 1000).toFixed(1)} s)`);
            page = r.siguiente;
        }
        let offset = 0, fin = false;                          // 2) guardar en la base, por lotes
        while (!falla && !syncCancelado && !fin) {
            det(`Guardando ${nombre}…` + (total ? ` ${num(Math.min(offset, total.unicos))} de ${num(total.unicos)}` : ''));
            const r = await syncPost('/paso', { fase: 'guardar', tipo: t.tipo, periodo: t.periodo, offset });
            if (!r.ok) { falla = r.error; if (r.gateway || r.fatal) detenido = true; break; }
            progreso(i, 0.5 + 0.5 * (r.total ? Math.min(1, r.guardados / r.total) : 1));
            if (r.fin) { fin = true; syncLog(`✓ ${nombre}: ${num(r.total)} comprobantes (${num(r.nuevos)} nuevos)` + (aviso ? ' — <b style="color:var(--gc-neg);">incompleto</b>' : '')); } else offset = r.siguiente;
        }
        if (falla) { ultimaFalla = falla; syncLog(`<span style="color:var(--gc-neg);">⚠ ${nombre}: ${falla}</span>`); }
    }
    progreso(n, 0);
    if (detenido) { cerrar(ultimaFalla && !/tard/i.test(ultimaFalla) ? '⚠ Se detuvo: ' + ultimaFalla : '⚠ Se detuvo: el servidor tardó demasiado. No reintentes de inmediato; avisa a soporte con este mensaje.'); return; }
    document.getElementById('syncDetalle').textContent = syncCancelado ? 'Cancelado.' : 'Listo. Cargando resultados…';
    setTimeout(() => location.href = SYNC_URL + '?ok=1', 700);
}
</script>
