<?php
/**
 * Clasificar (rediseño). Variables: $empresa, $periodo, $tipoFiltro ('ventas'|'compras'), $pendientes (de ese tipo),
 * $pendVentas, $pendCompras, $propuestas, $tiposVenta, $tiposCompra, $sugerencias, $otrosMeses, $cantReglas.
 */
$id = (int)$empresa['id']; $mes = Periodo::etiqueta($periodo, true);
$esV = $tipoFiltro === 'ventas'; $origen = $esV ? 'venta' : 'compra';
$nomPl = $esV ? 'ventas' : 'compras'; $nVenta = count($pendVentas); $nCompra = count($pendCompras);
$tipos = $esV ? $tiposVenta : $tiposCompra;
$S = fn($v) => number_format((float)$v, 2);
$etq = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC', '08' => 'ND', '00' => 'OTR'];
$urlPer = fn(string $p) => "/empresas/{$id}/periodo?periodo={$p}&volver=" . rawurlencode("/empresas/{$id}/imputacion?tipo={$tipoFiltro}");
$general = null; $porRuc = [];
foreach ($propuestas as $i => $p) { $p['_i'] = $i; if (!empty($p['general']) || ($p['fuente'] ?? '') !== 'regla') { $general ??= $p; } else { $porRuc[] = $p; } }
$subtabActiva = 'imputacion'; require ROOT . '/views/layout/comprobantes_subtabs.php';
?>
<?php if (!empty($_SESSION['imputacion_error'])): ?>
<div class="gc-alert neg"><span class="msg"><?= htmlspecialchars($_SESSION['imputacion_error']) ?></span></div>
<?php unset($_SESSION['imputacion_error']); endif; ?>

<div class="gc-row between">
    <div class="gc-seg" role="tablist">
        <a class="<?= $esV ? 'is-active' : '' ?>" href="?tipo=ventas">Ventas (<?= $nVenta ?>)</a>
        <a class="<?= !$esV ? 'is-active' : '' ?>" href="?tipo=compras">Compras (<?= $nCompra ?>)</a>
    </div>
    <a class="gc-btn" href="/empresas/<?= $id ?>/imputacion/reglas">Reglas de clasificación (<?= $cantReglas ?>)</a>
</div>

<?php if ($pendientes): ?>

<?php if ($propuestas): ?>
<section class="gc-card pad gc-stack" style="gap:14px;">
    <div>
        <h2 class="gc-h2">Propuesta en lote</h2>
        <div class="gc-sub">Aplica una cuenta a todo lo que está esperando; después revisas lo que no encaje.</div>
    </div>
    <?php if ($general): $n = count($general['documentos']); ?>
    <div class="gc-row between" style="background:var(--gc-bg);border-radius:12px;padding:16px 20px;gap:20px;">
        <div style="font-size:18px;line-height:1.35;">
            Asignar <b><?= $n ?> <?= $n === 1 ? rtrim($nomPl, 's') : $nomPl ?> pendiente<?= $n === 1 ? '' : 's' ?></b> a
            <b class="mono" style="color:var(--gc-brand);"><?= htmlspecialchars($general['cuenta_codigo']) ?></b> <?= htmlspecialchars($general['cuenta_nombre']) ?>
            <span class="gc-sub">· S/ <?= $S($general['monto_total']) ?></span>
        </div>
        <div class="gc-row" style="gap:10px;">
            <button type="button" class="gc-btn gc-btn-primary gc-btn-lg im-propuesta-apply" data-index="<?= $general['_i'] ?>">Aplicar a <?= $n === 1 ? 'la ' . rtrim($nomPl, 's') : 'las ' . $n . ' ' . $nomPl ?></button>
            <a class="gc-btn" href="/empresas/<?= $id ?>/imputacion/reglas">Cambiar cuenta general</a>
        </div>
    </div>
    <?php endif; ?>
    <?php foreach ($porRuc as $p): $n = count($p['documentos']); ?>
    <div class="gc-row between im-propuesta" style="border:1px solid var(--gc-line);border-radius:12px;padding:12px 18px;gap:16px;">
        <div style="font-size:16px;line-height:1.35;">
            <b><?= htmlspecialchars($p['contraparte']) ?></b> <span class="gc-sub mono" style="font-size:14px;">RUC <?= htmlspecialchars($p['ruc']) ?></span>
            — <?= $n ?> comprobante<?= $n === 1 ? '' : 's' ?> → <b class="mono"><?= htmlspecialchars($p['cuenta_codigo']) ?></b> <?= htmlspecialchars($p['cuenta_nombre']) ?>
            <span class="gc-sub">· S/ <?= $S($p['monto_total']) ?></span>
        </div>
        <button type="button" class="gc-btn gc-btn-soft im-propuesta-apply" data-index="<?= $p['_i'] ?>">Aplicar regla</button>
    </div>
    <?php endforeach; ?>
    <div class="gc-sub" style="font-size:15px;">Se marcan como <?= $esV ? 'cobradas' : 'pagadas' ?> en la fecha del comprobante. Las que fueron a crédito se corrigen en <a href="/empresas/<?= $id ?>/cobranzas?origen=<?= $origen ?>">Cobros y pagos</a>.</div>
</section>
<?php endif; ?>

<section class="gc-card clip">
    <div class="head">
        <div>
            <h2>Revisión una por una</h2>
            <div class="gc-sub" style="font-size:15px;"><span id="im-restan"><?= count($pendientes) ?></span> <?= $nomPl ?> de <?= $mes ?> por clasificar</div>
        </div>
        <label class="gc-check"><input type="checkbox" id="im-todas"> Seleccionar estas <?= count($pendientes) ?></label>
    </div>

    <div id="im-bulkbar" class="gc-row" style="display:none;position:sticky;top:76px;z-index:5;background:var(--gc-brand-soft);border-bottom:1px solid var(--gc-brand-line);padding:12px 24px;gap:12px;">
        <b id="im-bulk-count"></b>
        <span>Asignar a</span>
        <select id="im-bulk-select" class="gc-select" style="max-width:340px;"></select>
        <button id="im-bulk-apply" class="gc-btn gc-btn-primary" type="button">Confirmar seleccionadas</button>
        <button id="im-bulk-clear" class="gc-btn" type="button">Quitar selección</button>
    </div>

    <div id="im-lista">
    <?php foreach ($pendientes as $doc):
        $sug = $sugerencias[$doc['origen'] . ':' . $doc['contraparte_doc']] ?? null;
        $nota = (float)$doc['total'] < 0;
        $nom = trim((string)$doc['contraparte_nombre']);
    ?>
    <form class="im-form im-card" data-origen="<?= $doc['origen'] ?>" data-id="<?= $doc['id'] ?>" method="POST" action="/empresas/<?= $id ?>/imputacion/clasificar"
          style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:14px 24px;border-top:1px solid var(--gc-surface-2);">
        <input type="hidden" name="origen" value="<?= $doc['origen'] ?>"><input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
        <input type="checkbox" class="im-check" style="width:22px;height:22px;flex:0 0 22px;" aria-label="Seleccionar">
        <div style="flex:0 0 140px;">
            <div class="mono" style="font-size:15px;font-weight:600;"><span style="font-size:13px;font-weight:700;color:var(--gc-label-2);"><?= $etq[$doc['tipo_comp']] ?? $doc['tipo_comp'] ?></span> <?= htmlspecialchars($doc['serie'] . '-' . $doc['correlativo']) ?></div>
            <div class="gc-sub" style="font-size:14px;"><?= date('d/m/Y', strtotime($doc['fecha_emision'])) ?></div>
        </div>
        <div style="flex:1 1 150px;min-width:0;">
            <div style="font-weight:600;"><?= htmlspecialchars($nom !== '' && $nom !== '-' ? $nom : '—') ?></div>
            <div class="mono gc-sub" style="font-size:14px;"><?= $doc['contraparte_doc'] !== '' && $doc['contraparte_doc'] !== null ? htmlspecialchars($doc['contraparte_doc']) : 'Sin documento' ?></div>
        </div>
        <div class="num" style="flex:0 0 112px;text-align:right;font-weight:700;font-size:17px;">S/ <?= $S($doc['total']) ?></div>
        <select name="tipo_gasto_id" class="gc-select" style="flex:0 1 250px;" required>
            <option value="">Elegir cuenta…</option>
            <?php foreach ($tipos as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id'] === $sug ? 'selected' : '' ?>><?= htmlspecialchars($t['nombre_visible']) ?></option><?php endforeach; ?>
        </select>
        <?php if ($nota): ?>
        <div class="gc-sub" style="flex:0 1 220px;font-size:14px;line-height:1.3;">↩ Nota de crédito: <?= $esV ? 'anula la venta; si la factura figura cobrada, pásala a crédito' : 'queda como saldo a favor' ?>. Sin cobro ni pago.</div>
        <input type="checkbox" class="im-cobrado" name="cobrado" disabled style="display:none;"><input type="hidden" class="im-fecha-cobro" value="<?= $doc['fecha_emision'] ?>">
        <?php else: ?>
        <label class="gc-check" style="flex:0 0 auto;"><input type="checkbox" class="im-cobrado" name="cobrado" checked> <?= $esV ? 'Ya se cobró' : 'Ya se pagó' ?></label>
        <input type="hidden" class="im-fecha-cobro" name="fecha_cobro" value="<?= $doc['fecha_emision'] ?>">
        <?php endif; ?>
        <button type="submit" class="gc-btn gc-btn-primary im-btn">Confirmar</button>
    </form>
    <?php endforeach; ?>
    </div>
</section>

<?php endif; ?>

<div id="im-vacio" class="gc-card" style="<?= $pendientes ? 'display:none;' : '' ?>"><div class="gc-empty">
    <b>Todas las <?= $nomPl ?> de <?= $mes ?> están clasificadas</b>
    <?php if ($tipoFiltro === 'ventas' && $nCompra): ?>Aún hay <?= $nCompra ?> compra<?= $nCompra === 1 ? '' : 's' ?> por clasificar. <a href="?tipo=compras">Ir a Compras</a><?php endif; ?>
    <?php if ($tipoFiltro === 'compras' && $nVenta): ?>Aún hay <?= $nVenta ?> venta<?= $nVenta === 1 ? '' : 's' ?> por clasificar. <a href="?tipo=ventas">Ir a Ventas</a><?php endif; ?>
</div></div>

<?php if ($otrosMeses): ?>
<section class="gc-card pad gc-stack" style="gap:12px;">
    <h2 class="gc-h3">Otros meses con <?= $nomPl ?> por clasificar</h2>
    <div class="gc-row" style="gap:10px;">
        <?php foreach ($otrosMeses as $m): ?>
        <a class="gc-btn" href="<?= htmlspecialchars($urlPer($m['periodo'])) ?>"><?= Periodo::etiqueta($m['periodo'], true) ?> <span class="gc-chip warn"><?= (int)$m['n'] ?></span></a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<script>
(function () {
    const URL_LOTE = '/empresas/<?= $id ?>/imputacion/clasificar-lote';
    const TIPOS = <?= json_encode($tipos, JSON_UNESCAPED_UNICODE) ?>;
    const PROPUESTAS = <?= json_encode($propuestas ?? [], JSON_UNESCAPED_UNICODE) ?>;
    const lista = document.getElementById('im-lista');
    if (!lista) return;
    const bulk = document.getElementById('im-bulkbar'), sel = document.getElementById('im-bulk-select');
    sel.innerHTML = '<option value="">Elegir cuenta…</option>' + TIPOS.map(t => '<option value="' + t.id + '">' + t.nombre_visible.replace(/</g, '&lt;') + '</option>').join('');

    function restantes() { const n = lista.querySelectorAll('.im-card').length; document.getElementById('im-restan').textContent = n; return n; }
    function terminar() { gcToast('Listo: todo clasificado en este período.'); setTimeout(() => location.reload(), 900); }
    function quitar(card) { card.remove(); actualizar(); if (restantes() === 0) terminar(); }
    async function lote(items, extra) {
        const r = await fetch(URL_LOTE, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ items }, extra || {})) });
        return r.json();
    }
    function procesar(data, quitarFilas) {
        let ok = 0, mal = 0, err = '';
        (data.resultados || []).forEach(r => {
            const c = lista.querySelector('.im-card[data-origen="' + r.origen + '"][data-id="' + r.documento_id + '"]');
            if (r.ok) { ok++; if (c && quitarFilas) c.remove(); } else { mal++; err = err || r.error; }
        });
        return { ok, mal, err };
    }

    // una por una
    lista.addEventListener('submit', async function (e) {
        const f = e.target.closest('.im-form'); if (!f) return; e.preventDefault();
        const s = f.querySelector('select[name="tipo_gasto_id"]'); if (!s.value) { s.focus(); return; }
        const btn = f.querySelector('.im-btn'); btn.disabled = true; btn.textContent = 'Guardando…';
        const cb = f.querySelector('.im-cobrado'), fe = f.querySelector('.im-fecha-cobro');
        try {
            const d = await lote([{ origen: f.dataset.origen, documento_id: +f.dataset.id, tipo_gasto_id: +s.value, cobrado: cb && !cb.disabled && cb.checked, fecha: cb && cb.checked ? fe.value : null }]);
            const r = (d.resultados || [])[0];
            if (r && r.ok) { gcToast('Clasificado como ' + r.cuenta_codigo + ' · ' + r.cuenta_nombre + (r.cobrado ? ' · registrado en Caja' : '')); quitar(f); }
            else { gcToast((r && r.error) || 'No se pudo clasificar.', 'error'); btn.disabled = false; btn.textContent = 'Confirmar'; }
        } catch (x) { gcToast('Error de conexión al guardar.', 'error'); btn.disabled = false; btn.textContent = 'Confirmar'; }
    });

    // propuestas del motor de reglas
    document.querySelectorAll('.im-propuesta-apply').forEach(b => b.addEventListener('click', async function () {
        const p = PROPUESTAS[+this.dataset.index]; if (!p) return;
        const txt = this.textContent; this.disabled = true; this.textContent = 'Aplicando…';
        try {
            const d = await lote(p.documentos.map(x => ({ origen: x.origen, documento_id: x.documento_id, cuenta_id: p.cuenta_id, cobrado: true, fecha: null })), { regla_id: p.regla_id });
            const r = procesar(d, true);
            gcToast(r.ok + ' clasificado(s)' + (r.mal ? ', ' + r.mal + ' con error: ' + r.err : ''), r.mal ? 'error' : undefined);
            setTimeout(() => location.reload(), r.mal ? 1800 : 700);
        } catch (x) { gcToast('Error de conexión al aplicar la propuesta.', 'error'); this.disabled = false; this.textContent = txt; }
    }));

    // selección múltiple
    const checks = () => Array.from(lista.querySelectorAll('.im-check:checked'));
    function actualizar() {
        const c = checks(); bulk.style.display = c.length ? 'flex' : 'none';
        document.getElementById('im-bulk-count').textContent = c.length + ' seleccionada' + (c.length === 1 ? '' : 's');
        document.getElementById('im-todas').checked = c.length > 0 && c.length === lista.querySelectorAll('.im-check').length;
    }
    lista.addEventListener('change', e => { if (e.target.classList.contains('im-check')) actualizar(); });
    document.getElementById('im-todas').addEventListener('change', function () { lista.querySelectorAll('.im-check').forEach(c => c.checked = this.checked); actualizar(); });
    document.getElementById('im-bulk-clear').addEventListener('click', () => { lista.querySelectorAll('.im-check').forEach(c => c.checked = false); actualizar(); });
    document.getElementById('im-bulk-apply').addEventListener('click', async function () {
        if (!sel.value) { gcToast('Elige primero la cuenta.', 'error'); sel.focus(); return; }
        const sel_ = checks(); if (!sel_.length) return;
        this.disabled = true; this.textContent = 'Aplicando…';
        const items = sel_.map(c => { const f = c.closest('.im-card'), cb = f.querySelector('.im-cobrado'), fe = f.querySelector('.im-fecha-cobro');
            return { origen: f.dataset.origen, documento_id: +f.dataset.id, tipo_gasto_id: +sel.value, cobrado: !!(cb && !cb.disabled && cb.checked), fecha: cb && cb.checked ? fe.value : null }; });
        try {
            const r = procesar(await lote(items), true);
            gcToast(r.ok + ' clasificada(s)' + (r.mal ? ', ' + r.mal + ' con error: ' + r.err : ''), r.mal ? 'error' : undefined);
            actualizar(); if (restantes() === 0) terminar();
        } catch (x) { gcToast('Error de conexión al aplicar en lote.', 'error'); }
        this.disabled = false; this.textContent = 'Confirmar seleccionadas';
    });
})();
</script>
