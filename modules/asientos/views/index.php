<?php
/** Asientos manuales (rediseño). Variables: $empresa, $anio, $cuentas, $manuales, $editando, $periodo, $fechaPeriodo, $plantillaDatos. */
$id = (int)$empresa['id']; $base = "/empresas/{$id}/asientos";
$S = fn($v) => number_format((float)$v, 2);
$anios = Periodo::anios(); if (!in_array($anio, $anios, true)) array_unshift($anios, $anio);
$contActiva = 'asientos'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Asientos manuales';
$contDesc = 'Para provisiones, depreciación, el costo de ventas, el impuesto a la renta y correcciones. Entran al Libro Diario, al Mayor y a los estados como cualquier asiento, y volver a generar el Diario nunca los borra.';
require ROOT . '/views/layout/contabilidad_head.php';

// Borrador tras un error de validación (no se pierde lo digitado) o asiento en edición
$borrador = $_SESSION['asiento_borrador'] ?? null; unset($_SESSION['asiento_borrador']);
$form = $borrador ?: ($editando ? ['fecha' => $editando['fecha'], 'glosa' => $editando['glosa'], 'id' => $editando['id'],
        'lineas' => array_map(fn($l) => ['cuenta_id' => $l['cuenta_id'], 'debe' => $l['debe'], 'haber' => $l['haber']], $editando['lineas'])]
        : ['fecha' => $fechaPeriodo, 'glosa' => '', 'id' => 0, 'lineas' => []]);
$plantillaPedida = $_GET['plantilla'] ?? '';
$mostrarForm = $form['id'] || $borrador || $plantillaPedida !== '' || isset($_GET['nuevo']);
$plantillas = [
    'cts'     => ['Provisión de CTS', 'Compensación por tiempo de servicios del mes: gasto 6291 contra 4151 por pagar.'],
    'grati'   => ['Gratificación', 'Provisión de la gratificación: gasto 6214 contra 4114 por pagar.'],
    'vacac'   => ['Vacaciones', 'Provisión de vacaciones: gasto 6215 contra 4115 por pagar.'],
    'deprec'  => ['Depreciación', 'Depreciación de propiedad, planta y equipo: gasto 684 contra 395 acumulada.'],
    'costo'   => ['Costo de ventas', 'Se calcula con el inventario final: existencias en libros (201) menos lo que queda.'],
    'ir'      => ['Impuesto a la renta', 'Provisión del impuesto del ejercicio: 881 contra 4017 por pagar.'],
    'blanco'  => ['En blanco', 'Armas el asiento línea por línea.'],
];
?>
<?php foreach (['asiento_ok' => 'ok', 'asiento_error' => 'neg'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach; ?>

<?php if (!$form['id']): ?>
<section class="gc-card pad gc-stack" style="gap:14px;">
    <h3 class="gc-h3">1. Elige una plantilla</h3>
    <div class="gc-kpis" style="grid-template-columns:repeat(auto-fill,minmax(210px,1fr));">
        <?php foreach ($plantillas as $k => [$tit, $desc]): ?>
        <button type="button" class="gc-kpi plantilla" data-plantilla="<?= $k ?>" style="gap:6px;">
            <span class="k" style="color:var(--gc-ink);font-size:17px;font-weight:700;"><?= $tit ?></span><span class="s" style="font-size:14px;line-height:1.35;"><?= $desc ?></span>
        </button>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<form method="POST" action="<?= $base ?>/guardar" id="as-form" class="gc-card clip" style="<?= $mostrarForm ? '' : 'display:none;' ?>">
    <input type="hidden" name="asiento_id" value="<?= (int)$form['id'] ?>">
    <div class="head"><h2><?= $form['id'] ? 'Editando el asiento' : '2. Completa el asiento' ?></h2><span id="as-plantilla-nombre" class="gc-chip info" style="display:none;"></span></div>
    <div class="body gc-stack" style="gap:16px;">
        <div id="as-nota" class="gc-alert info" style="display:none;"><span class="msg" id="as-nota-txt"></span></div>
        <div id="as-extra" style="display:none;"></div>
        <div class="gc-row" style="align-items:flex-end;gap:16px;">
            <div class="gc-field" style="flex:0 0 190px;"><label class="gc-label" for="as-fecha">Fecha</label><input class="gc-input" id="as-fecha" type="date" name="fecha" required value="<?= htmlspecialchars($form['fecha']) ?>"></div>
            <div class="gc-field" style="flex:1 1 320px;"><label class="gc-label" for="as-glosa">Glosa (descripción)</label><input class="gc-input" id="as-glosa" type="text" name="glosa" required maxlength="250" value="<?= htmlspecialchars($form['glosa']) ?>" placeholder="Ej.: Provisión de CTS del mes"></div>
        </div>
        <div class="gc-table-wrap">
        <table class="gc-table compacta" style="min-width:640px;">
            <thead><tr><th>Cuenta</th><th class="r" style="width:170px;">Debe</th><th class="r" style="width:170px;">Haber</th><th style="width:90px;"></th></tr></thead>
            <tbody id="as-lineas"></tbody>
            <tfoot><tr><td><button type="button" id="as-add" class="gc-btn">+ Agregar línea</button></td><td class="r" id="as-tdebe">0.00</td><td class="r" id="as-thaber">0.00</td><td></td></tr></tfoot>
        </table>
        </div>
    </div>
    <div class="gc-row between" style="padding:16px 24px;border-top:1px solid var(--gc-line);background:var(--gc-bg);position:sticky;bottom:0;">
        <b id="as-estado" style="font-size:17px;"></b>
        <div class="gc-row" style="gap:10px;">
            <?php if ($form['id']): ?><a class="gc-btn" href="<?= $base ?>?anio=<?= $anio ?>">Cancelar</a><?php endif; ?>
            <button type="submit" id="as-guardar" class="gc-btn gc-btn-primary gc-btn-lg"><?= $form['id'] ? 'Guardar cambios' : 'Registrar asiento' ?></button>
        </div>
    </div>
</form>

<section class="gc-stack" style="gap:14px;">
    <div class="gc-row between">
        <h2 class="gc-h2">Asientos manuales de <?= $anio ?></h2>
        <div class="gc-row" style="gap:8px;"><?php foreach ($anios as $a): ?><a class="gc-chipbtn <?= $a === $anio ? 'is-active' : '' ?>" href="<?= $base ?>?anio=<?= $a ?>"><?= $a ?></a><?php endforeach; ?></div>
    </div>
    <?php if (!$manuales): ?><div class="gc-card"><div class="gc-empty"><b>Aún no hay asientos manuales en <?= $anio ?></b>Elige una plantilla arriba para registrar el primero.</div></div><?php endif; ?>
    <?php foreach ($manuales as $m): ?>
    <div class="gc-card clip">
        <div class="gc-row between" style="padding:12px 20px;background:var(--gc-bg);">
            <span><b class="mono">N.° <?= $m['correlativo'] ?></b> · <?= date('d/m/Y', strtotime($m['fecha'])) ?> · <?= htmlspecialchars($m['glosa']) ?> <span class="gc-chip info" style="margin-left:6px;">Manual</span></span>
            <span class="gc-row" style="gap:10px;"><a class="gc-btn" href="<?= $base ?>?anio=<?= $anio ?>&editar=<?= $m['id'] ?>">Editar</a>
                <form method="POST" action="<?= $base ?>/<?= $m['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar este asiento?');" style="margin:0;"><input type="hidden" name="anio" value="<?= $anio ?>"><button class="gc-btn" type="submit" style="color:var(--gc-neg);">Eliminar</button></form></span>
        </div>
        <table class="gc-table compacta">
            <?php foreach ($m['lineas'] as $l): ?>
            <tr><td style="<?= $l['haber'] > 0 ? 'padding-left:48px;' : '' ?>"><b class="mono" style="font-size:15px;"><?= $l['codigo'] ?></b> <?= htmlspecialchars($l['nombre']) ?></td>
                <td class="r" style="width:150px;"><?= $l['debe'] > 0 ? $S($l['debe']) : '' ?></td><td class="r" style="width:150px;"><?= $l['haber'] > 0 ? $S($l['haber']) : '' ?></td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endforeach; ?>
</section>

<script>
(function () {
    const CUENTAS = <?= json_encode($cuentas, JSON_UNESCAPED_UNICODE) ?>;
    const POR_CODIGO = {}; CUENTAS.forEach(c => POR_CODIGO[c.codigo] = c.id);
    const INICIAL = <?= json_encode($form['lineas'], JSON_UNESCAPED_UNICODE) ?>;
    const DATOS = <?= json_encode($plantillaDatos) ?>;
    const PEDIDA = <?= json_encode($plantillaPedida) ?>;
    const NOMBRES = <?= json_encode(array_map(fn($p) => $p[0], $plantillas), JSON_UNESCAPED_UNICODE) ?>;
    const DESTINO = 'El destino a gastos de administración o de venta (94 y 95) se genera solo al volver a generar el Libro Diario de ese mes.';
    const PLANTILLAS = {
        cts:    { glosa: 'Provisión de CTS', lineas: [['6291', 'debe'], ['4151', 'haber']], nota: 'Escribe el monto de la CTS del mes. ' + DESTINO },
        grati:  { glosa: 'Provisión de gratificación', lineas: [['6214', 'debe'], ['4114', 'haber']], nota: 'Escribe el monto de la gratificación provisionada. ' + DESTINO },
        vacac:  { glosa: 'Provisión de vacaciones', lineas: [['6215', 'debe'], ['4115', 'haber']], nota: 'Escribe el monto de las vacaciones provisionadas. ' + DESTINO },
        deprec: { glosa: 'Depreciación del mes', lineas: [['684', 'debe'], ['395', 'haber']], nota: 'Escribe el monto de la depreciación. ' + DESTINO },
        costo:  { glosa: 'Costo de ventas por inventario final', lineas: [['691', 'debe'], ['201', 'haber']], extra: 'costo',
                  nota: 'Costo de ventas = inventario inicial + compras − inventario final. Con las existencias en libros (cuenta 201) y el inventario final real, el monto se calcula solo.' },
        ir:     { glosa: 'Provisión del impuesto a la renta', lineas: [['881', 'debe'], ['4017', 'haber']], extra: 'ir',
                  nota: 'El monto propuesto es la estimación con la tasa del ejercicio. Puedes corregirlo con el cálculo del contador.' },
        blanco: { glosa: '', lineas: [], nota: '' }
    };
    const tbody = document.getElementById('as-lineas');
    const opciones = '<option value="">Elegir cuenta…</option>' + CUENTAS.map(c => '<option value="' + c.id + '">' + c.codigo + ' — ' + c.nombre.replace(/</g, '&lt;') + '</option>').join('');
    const num = v => parseFloat(String(v || '0').replace(/,/g, '')) || 0;
    const f2 = n => Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function fila(cuentaId, debe, haber) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><select name="cuenta_id[]" class="gc-select">' + opciones + '</select></td>'
            + '<td><input type="number" step="0.01" min="0" name="debe[]" class="gc-input" style="text-align:right;"></td>'
            + '<td><input type="number" step="0.01" min="0" name="haber[]" class="gc-input" style="text-align:right;"></td>'
            + '<td class="c"><button type="button" class="gc-btn as-del" aria-label="Quitar línea">Quitar</button></td>';
        const [sel, d, h] = [tr.querySelector('select'), tr.querySelector('[name="debe[]"]'), tr.querySelector('[name="haber[]"]')];
        if (cuentaId) sel.value = String(cuentaId);
        if (num(debe) > 0) d.value = num(debe).toFixed(2); if (num(haber) > 0) h.value = num(haber).toFixed(2);
        d.addEventListener('input', () => { if (num(d.value) > 0) h.value = ''; recalcular(); });      // una línea es de un solo lado
        h.addEventListener('input', () => { if (num(h.value) > 0) d.value = ''; recalcular(); });
        tr.querySelector('.as-del').addEventListener('click', () => { tr.remove(); recalcular(); });
        tbody.appendChild(tr);
        return tr;
    }
    function recalcular() {
        let d = 0, h = 0, lineas = 0;
        tbody.querySelectorAll('tr').forEach(tr => { const a = num(tr.querySelector('[name="debe[]"]').value), b = num(tr.querySelector('[name="haber[]"]').value); d += a; h += b; if ((a > 0 || b > 0) && tr.querySelector('select').value) lineas++; });
        d = Math.round(d * 100) / 100; h = Math.round(h * 100) / 100;
        document.getElementById('as-tdebe').textContent = f2(d); document.getElementById('as-thaber').textContent = f2(h);
        const est = document.getElementById('as-estado'), btn = document.getElementById('as-guardar'), dif = Math.round((d - h) * 100) / 100;
        let ok = false;
        if (d === 0 && h === 0) { est.textContent = 'Agrega las líneas del asiento.'; est.style.color = 'var(--gc-label-2)'; }
        else if (dif !== 0) { est.textContent = 'Falta S/ ' + f2(Math.abs(dif)) + ' en el ' + (dif > 0 ? 'Haber' : 'Debe') + ' para que cuadre.'; est.style.color = 'var(--gc-neg)'; }
        else if (lineas < 2) { est.textContent = 'Necesita al menos dos líneas con cuenta y monto.'; est.style.color = 'var(--gc-warn)'; }
        else { est.textContent = 'El asiento cuadra: Debe = Haber = S/ ' + f2(d); est.style.color = 'var(--gc-pos)'; ok = true; }
        btn.disabled = !ok; btn.style.opacity = ok ? '1' : '.45';
    }
    function fijarMonto(m) {   // pone el mismo monto en la línea del Debe y en la del Haber de la plantilla
        const trs = tbody.querySelectorAll('tr'); if (trs.length < 2) return;
        trs[0].querySelector('[name="debe[]"]').value = m > 0 ? m.toFixed(2) : ''; trs[1].querySelector('[name="haber[]"]').value = m > 0 ? m.toFixed(2) : ''; recalcular();
    }
    function abrirPlantilla(k) {
        const p = PLANTILLAS[k]; if (!p) return;
        document.getElementById('as-form').style.display = ''; tbody.innerHTML = '';
        p.lineas.forEach(([cod]) => fila(POR_CODIGO[cod] || '', '', ''));
        if (!p.lineas.length) { fila('', '', ''); fila('', '', ''); }
        const g = document.getElementById('as-glosa'); g.value = p.glosa ? p.glosa + ' · ' + <?= json_encode(Periodo::etiqueta($periodo, true)) ?> : '';
        const nombre = document.getElementById('as-plantilla-nombre'); nombre.textContent = NOMBRES[k]; nombre.style.display = '';
        const nota = document.getElementById('as-nota'); nota.style.display = p.nota ? '' : 'none'; document.getElementById('as-nota-txt').textContent = p.nota;
        const extra = document.getElementById('as-extra'); extra.style.display = 'none'; extra.innerHTML = '';
        if (p.extra === 'costo') {
            extra.style.display = '';
            extra.innerHTML = '<div class="gc-kpis" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));"><div class="gc-kpi"><span class="k">Existencias en libros (201)</span><span class="v">S/ ' + f2(DATOS.existencias) + '</span></div>'
                + '<div class="gc-kpi"><span class="k">Inventario final (lo que queda)</span><input id="as-inv-final" type="number" step="0.01" min="0" class="gc-input gc-input-lg" placeholder="0.00"></div>'
                + '<div class="gc-kpi"><span class="k">Costo de ventas</span><span class="v" id="as-costo-v">S/ 0.00</span></div></div>';
            document.getElementById('as-inv-final').addEventListener('input', function () {
                const c = Math.max(0, Math.round((DATOS.existencias - num(this.value)) * 100) / 100);
                document.getElementById('as-costo-v').textContent = 'S/ ' + f2(c); fijarMonto(c);
            });
        } else if (p.extra === 'ir') {
            if (DATOS.ir_registrado) { nota.style.display = ''; document.getElementById('as-nota-txt').textContent = 'El impuesto a la renta ya está registrado en 881. Solo agrega otro asiento si necesitas una corrección.'; }
            else fijarMonto(DATOS.ir_estimado);
            if (!DATOS.ir_registrado) document.getElementById('as-nota-txt').textContent = 'Estimado: ' + DATOS.tasa_ir.toFixed(1) + ' % de la utilidad antes de impuestos (S/ ' + f2(DATOS.utilidad_antes) + '). Puedes corregir el monto.';
        }
        recalcular(); document.getElementById('as-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    document.querySelectorAll('.plantilla').forEach(b => b.addEventListener('click', () => abrirPlantilla(b.dataset.plantilla)));
    document.getElementById('as-add').addEventListener('click', () => { fila('', '', ''); recalcular(); });
    tbody.addEventListener('change', recalcular);

    if (INICIAL.length) { INICIAL.forEach(l => fila(l.cuenta_id, l.debe, l.haber)); recalcular(); }
    else if (PLANTILLAS[PEDIDA]) abrirPlantilla(PEDIDA);
    else { fila('', '', ''); fila('', '', ''); recalcular(); }
})();
</script>
