<?php
$etiquetaMin = Periodo::etiqueta($hasta, true);
$bloqueada   = !$apertura['ok'];
?>
<div class="gc-content gc-w-content">
<div style="max-width:760px;margin:0 auto;">

    <div style="margin-bottom:14px;">
        <div style="font-size:18px;font-weight:700;color:var(--gc-ink);">Depurar datos anteriores a <?= $etiquetaMin ?></div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC <?= $empresa['ruc'] ?></div>
    </div>

    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;line-height:1.55;">
        <strong>⚠ Esta acción es irreversible.</strong> Se borra de forma definitiva lo anterior a <?= $etiquetaMin ?> de <strong>esta empresa</strong>.
        Antes de hacerlo, saca un respaldo de la base de datos (<code>mysqldump</code> o el respaldo de Plesk).
    </div>

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
        <div style="padding:12px 18px;border-bottom:1px solid var(--gc-line);font-weight:700;color:var(--gc-ink);">Lo que se borraría</div>
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <?php foreach ($conteos as $c): ?>
            <tr style="border-top:1px solid var(--gc-surface-2);">
                <td style="padding:8px 18px;color:var(--gc-label);"><?= htmlspecialchars($c['etiqueta']) ?></td>
                <td style="padding:8px 18px;text-align:right;font-family:monospace;font-weight:600;" data-tabla="<?= $c['tabla'] ?>"><?= number_format($c['n']) ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="border-top:2px solid var(--gc-line);background:var(--gc-bg);font-weight:700;">
                <td style="padding:9px 18px;">Total de filas</td>
                <td style="padding:9px 18px;text-align:right;font-family:monospace;" id="depTotal"><?= number_format($total) ?></td>
            </tr>
        </table>
        <div style="padding:10px 18px;font-size:12px;color:var(--gc-muted);border-top:1px solid var(--gc-line);">
            Las ventas y compras arrastran sus clasificaciones, y los años contables viejos sus asientos, saldos de apertura y cierres.
            <strong>No se toca:</strong> comprobantes emitidos, guías, declaraciones, plan de cuentas, reglas, préstamos ni usuarios.
        </div>
    </div>

    <?php if ($bloqueada): ?>
    <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:14px 16px;font-size:13px;font-weight:600;line-height:1.5;">
        ⛔ No se puede depurar: esta empresa no tiene saldos de apertura de <?= $apertura['anio'] ?>. Si se borran los años anteriores, su balance <?= $apertura['anio'] ?> quedaría sin punto de partida.
        Carga primero la <a href="/empresas/<?= $empresa['id'] ?>/apertura" style="color:var(--gc-warn);text-decoration:underline;">Apertura <?= $apertura['anio'] ?></a>.
    </div>
    <?php elseif ($total === 0): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border-radius:10px;padding:14px 16px;font-size:13px;font-weight:600;">✓ No hay nada anterior a <?= $etiquetaMin ?> que depurar.</div>
    <?php else: ?>
    <div id="depCaja" style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:18px;">
        <label style="display:block;font-size:13px;color:var(--gc-label);margin-bottom:8px;">
            Para confirmar, escribe el RUC de la empresa: <strong style="font-family:monospace;"><?= $empresa['ruc'] ?></strong>
        </label>
        <input id="depConfirmo" type="text" inputmode="numeric" autocomplete="off" placeholder="RUC"
               style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:15px;font-family:monospace;background:var(--gc-surface);color:var(--gc-ink);">
        <div style="display:flex;justify-content:flex-end;margin-top:14px;">
            <button id="depBtn" type="button" disabled onclick="depurar()"
                style="background:var(--gc-neg);color:#fff;border:none;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;opacity:.45;">
                🗑 Borrar datos anteriores a <?= $etiquetaMin ?>
            </button>
        </div>
    </div>

    <div id="depProgreso" style="display:none;background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:18px;">
        <div style="font-weight:700;color:var(--gc-ink);margin-bottom:4px;" id="depTitulo">Borrando…</div>
        <div style="font-size:13px;color:var(--gc-label);margin-bottom:10px;">Puede tardar unos minutos. <strong>No cierres esta ventana.</strong></div>
        <div style="background:var(--gc-surface-2);border-radius:99px;height:10px;overflow:hidden;"><div id="depBarra" style="height:100%;width:0;background:var(--gc-neg);transition:width .3s;"></div></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--gc-muted);margin-top:6px;"><span id="depDetalle"></span><span id="depPct">0 %</span></div>
    </div>
    <?php endif; ?>

</div>
</div>

<?php if (!$bloqueada && $total > 0): ?>
<script>
const RUC = <?= json_encode((string)$empresa['ruc']) ?>;
const URL_PASO = '/empresas/<?= $empresa['id'] ?>/depurar/paso';
const TOTAL_INICIAL = <?= (int)$total ?>;
const num = n => Number(n).toLocaleString('es-PE');
const inp = document.getElementById('depConfirmo'), btn = document.getElementById('depBtn');
inp.addEventListener('input', () => { const ok = inp.value.trim() === RUC; btn.disabled = !ok; btn.style.opacity = ok ? 1 : .45; });

async function depurar() {
    if (inp.value.trim() !== RUC) return;
    if (!confirm('Última confirmación: ¿borrar definitivamente los datos anteriores a <?= $etiquetaMin ?> de esta empresa?')) return;
    document.getElementById('depCaja').style.display = 'none';
    const pr = document.getElementById('depProgreso'); pr.style.display = 'block';
    let pendientes = TOTAL_INICIAL, inicio = true, fallos = 0;

    while (true) {
        let r;
        try {
            const resp = await fetch(URL_PASO, {method:'POST', credentials:'same-origin',
                body:new URLSearchParams({confirmo:inp.value.trim(), inicio: inicio ? '1' : ''})});
            const txt = await resp.text();
            try { r = JSON.parse(txt); } catch (e) { r = {ok:false, gateway:[502,503,504].includes(resp.status), error:'El servidor respondió HTTP ' + resp.status}; }
        } catch (e) { r = {ok:false, error:'Error de red'}; }

        if (!r.ok) {
            // Un 504 NO se reintenta solo (el servidor sigue trabajando): se avisa y se puede reanudar a mano, es seguro.
            if (r.error === 'Error de red' && ++fallos < 2) { await new Promise(s => setTimeout(s, 1500)); continue; }
            document.getElementById('depTitulo').textContent = '⚠ Se detuvo';
            document.getElementById('depTitulo').style.color = 'var(--gc-neg)';
            document.getElementById('depDetalle').textContent = r.error + ' — Puedes reanudar recargando la página; lo ya borrado no se repite.';
            return;
        }
        inicio = false; fallos = 0;
        pendientes = r.pendientes;
        const hecho = TOTAL_INICIAL - pendientes, pct = Math.min(100, Math.round(100 * hecho / TOTAL_INICIAL));
        document.getElementById('depBarra').style.width = pct + '%';
        document.getElementById('depPct').textContent = pct + ' %';
        document.getElementById('depDetalle').textContent = (r.etiqueta ? 'Borrando ' + r.etiqueta.toLowerCase() + '… ' : '') + num(Math.max(hecho, 0)) + ' de ' + num(TOTAL_INICIAL) + ' filas';
        if (r.terminado) {
            document.getElementById('depTitulo').textContent = '✓ Listo. Datos anteriores a <?= $etiquetaMin ?> borrados.';
            document.getElementById('depTitulo').style.color = 'var(--gc-pos)';
            document.getElementById('depBarra').style.background = 'var(--gc-pos)';
            document.getElementById('depDetalle').innerHTML = '<a href="" style="color:var(--gc-brand);">Recargar</a>';
            return;
        }
    }
}
</script>
<?php endif; ?>
