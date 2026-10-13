<?php
/** Balance de comprobación (rediseño). Variables: $empresa, $periodo, $balance, $verif, $mensual. */
$id = (int)$empresa['id']; $mes = Periodo::etiqueta($periodo, true);
$contActiva = 'balance'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Balance de comprobación';
$contDesc = 'Acumulado de enero a ' . $mes . ' · hoja de trabajo con el resultado por naturaleza y por función';
$contAcciones = !empty($balance['filas']) ? '<a class="gc-btn" href="/empresas/' . $id . '/balance/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>' : '';
require ROOT . '/views/layout/contabilidad_head.php';
$N = fn($v) => abs((float)$v) < 0.005 ? '<span style="color:var(--gc-muted);">—</span>' : number_format((float)$v, 2);
?>
<?php if (empty($balance['filas'])): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin movimientos acumulados hasta este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else:
    $t = $balance['totales'];
    $nat = BalanceService::cierrePar((float)$t['nat_perdidas'], (float)$t['nat_ganancias']);
    $fun = BalanceService::cierrePar((float)$t['fun_perdidas'], (float)$t['fun_ganancias']);
    $coinciden = $nat['tipo'] === $fun['tipo'] && abs($nat['monto'] - $fun['monto']) < 0.02;
    $cr = BalanceService::cierreResultado($t);
    $elementos = [1 => 'Elemento 1 · Activo disponible y exigible', 2 => 'Elemento 2 · Activo realizable', 3 => 'Elemento 3 · Activo inmovilizado', 4 => 'Elemento 4 · Pasivo', 5 => 'Elemento 5 · Patrimonio', 6 => 'Elemento 6 · Gastos por naturaleza', 7 => 'Elemento 7 · Ingresos', 8 => 'Elemento 8 · Saldos intermedios y determinación del resultado', 9 => 'Elemento 9 · Cuentas analíticas de explotación'];
    $grupos = [];
    foreach ($balance['filas'] as $f) $grupos[(int)$f['codigo'][0]][] = $f;
    ksort($grupos);
    $col = 'color:var(--gc-ink);';
?>
<div class="gc-kpis">
    <div class="gc-kpi <?= $cr['tipo'] === 'perdida' ? 'neg' : 'ok' ?>" style="border-width:2px;">
        <span class="k"><?= $nat['tipo'] === 'perdida' ? 'Pérdida' : 'Utilidad' ?> del ejercicio</span>
        <span class="v">S/ <?= number_format($nat['monto'], 2) ?></span>
        <span class="s">Antes de impuesto a la renta y reserva legal<?= $coinciden ? ' · igual por naturaleza y por función' : '' ?></span>
    </div>
    <div class="gc-kpi">
        <span class="k">Activo contra pasivo + patrimonio</span>
        <span class="v" style="font-size:22px;">S/ <?= number_format($t['activo'], 2) ?> <span style="color:var(--gc-label-2);">/</span> S/ <?= number_format($t['pasivo'] + $cr['ajuste']['pasivo'], 2) ?></span>
        <span class="s">Inventario del balance de comprobación</span>
    </div>
</div>

<section class="gc-card pad gc-stack" style="gap:10px;">
    <h3 class="gc-h3">Verificaciones</h3>
    <?php $ver = [
        ['El Libro Diario cuadra', 'Suma del Debe igual a la suma del Haber (S/ ' . number_format($t['debe'], 2) . ')', $verif['diario'], '/empresas/' . $id . '/diario', 'Ir al Libro Diario'],
        ['Los saldos cuadran', 'Saldos deudores igual a saldos acreedores', $verif['saldos'], '/empresas/' . $id . '/mayor', 'Ir al Mayor'],
        ['El balance general cuadra', $verif['bg'] ? ($verif['balance_general'] ? 'Activo igual a Pasivo + Patrimonio' : 'Diferencia de S/ ' . number_format(abs($verif['bg']['descuadre']), 2)) : 'No se pudo calcular', $verif['balance_general'], '/empresas/' . $id . '/balance-general', 'Ver balance general'],
        ['Las cuentas 9 igualan a la 79', 'Costos y gastos por función (S/ ' . number_format($verif['nueve'] ?? 0, 2) . ') contra cargas imputables (S/ ' . number_format($verif['s79'] ?? 0, 2) . ')', $verif['nueve_79'], '/empresas/' . $id . '/mayor?cuenta=791', 'Ver la cuenta 791'],
    ]; foreach ($ver as [$tit, $det, $ok, $url, $btn]): if ($ok === null) continue; ?>
    <div class="gc-alert <?= $ok ? 'ok' : 'warn' ?>" style="padding:10px 16px;"><span class="msg"><b><?= $ok ? '✓' : '⚠' ?> <?= $tit ?></b> · <?= $det ?></span><?php if (!$ok): ?><a class="gc-btn" href="<?= $url ?>"><?= $btn ?></a><?php endif; ?></div>
    <?php endforeach; ?>
</section>

<section class="gc-card clip">
    <div class="head">
        <input id="bc-q" class="gc-input" type="search" placeholder="Buscar cuenta por código o nombre" style="max-width:380px;" autocomplete="off">
        <div class="gc-seg"><button type="button" class="is-active" data-vista="completo">Completo</button><button type="button" data-vista="saldos">Solo saldos</button></div>
    </div>
    <div class="gc-table-wrap">
    <table class="gc-table compacta" id="bc-tabla" style="min-width:1060px;">
        <thead>
            <tr>
                <th rowspan="2" style="vertical-align:bottom;">Cuenta</th>
                <th colspan="2" class="c col-sumas">Sumas del mayor</th><th colspan="2" class="c">Saldos</th><th colspan="2" class="c col-extra">Inventario</th>
                <th colspan="2" class="c col-extra">Resultado por naturaleza</th><th colspan="2" class="c col-extra">Resultado por función</th>
            </tr>
            <tr><?php foreach (['Debe', 'Haber', 'Deudor', 'Acreedor', 'Activo', 'Pasivo', 'Pérdidas', 'Ganancias', 'Pérdidas', 'Ganancias'] as $i => $h): ?>
                <th class="r <?= $i < 2 ? 'col-sumas' : ($i < 4 ? '' : 'col-extra') ?>" style="font-size:14px;"><?= $h ?></th><?php endforeach; ?></tr>
        </thead>
        <?php foreach ($grupos as $el => $filas): ?>
        <tbody class="bc-grupo" data-el="<?= $el ?>">
            <tr class="bc-cab" style="background:var(--gc-brand-soft);cursor:pointer;" onclick="this.parentNode.classList.toggle('cerrado')">
                <td colspan="11" style="font-weight:700;color:var(--gc-brand);"><span class="bc-flecha">▾</span> <?= $elementos[$el] ?? 'Elemento ' . $el ?> <span class="gc-sub" style="font-weight:400;font-size:14px;">· <?= count($filas) ?> cuenta<?= count($filas) === 1 ? '' : 's' ?></span></td>
            </tr>
            <?php foreach ($filas as $f): ?>
            <tr class="bc-fila" data-cuenta="<?= $f['codigo'] ?>" data-q="<?= htmlspecialchars(mb_strtolower($f['codigo'] . ' ' . $f['nombre'])) ?>" style="cursor:pointer;" title="Ver el detalle por mes">
                <td><b class="mono" style="font-size:15px;"><?= $f['codigo'] ?></b> <?= htmlspecialchars($f['nombre']) ?></td>
                <td class="r col-sumas"><?= $N($f['debe']) ?></td><td class="r col-sumas"><?= $N($f['haber']) ?></td>
                <td class="r"><?= $N($f['deudor']) ?></td><td class="r"><?= $N($f['acreedor']) ?></td>
                <td class="r col-extra"><?= $N($f['activo']) ?></td><td class="r col-extra"><?= $N($f['pasivo']) ?></td>
                <td class="r col-extra"><?= $N($f['nat_perdidas']) ?></td><td class="r col-extra"><?= $N($f['nat_ganancias']) ?></td>
                <td class="r col-extra"><?= $N($f['fun_perdidas']) ?></td><td class="r col-extra"><?= $N($f['fun_ganancias']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php endforeach; ?>
        <tfoot>
            <tr><td>Totales</td><td class="r col-sumas"><?= $N($t['debe']) ?></td><td class="r col-sumas"><?= $N($t['haber']) ?></td><td class="r"><?= $N($t['deudor']) ?></td><td class="r"><?= $N($t['acreedor']) ?></td>
                <td class="r col-extra"><?= $N($t['activo']) ?></td><td class="r col-extra"><?= $N($t['pasivo']) ?></td><td class="r col-extra"><?= $N($t['nat_perdidas']) ?></td><td class="r col-extra"><?= $N($t['nat_ganancias']) ?></td><td class="r col-extra"><?= $N($t['fun_perdidas']) ?></td><td class="r col-extra"><?= $N($t['fun_ganancias']) ?></td></tr>
            <tr style="font-weight:700;"><td><?= $nat['tipo'] === 'perdida' ? 'Pérdida' : 'Utilidad' ?> del ejercicio</td><td class="col-sumas" colspan="2"></td><td colspan="2"></td>
                <td class="r col-extra"><?= $N($cr['ajuste']['activo']) ?></td><td class="r col-extra"><?= $N($cr['ajuste']['pasivo']) ?></td>
                <td class="r col-extra"><?= $N($nat['ajuste_perdidas']) ?></td><td class="r col-extra"><?= $N($nat['ajuste_ganancias']) ?></td><td class="r col-extra"><?= $N($fun['ajuste_perdidas']) ?></td><td class="r col-extra"><?= $N($fun['ajuste_ganancias']) ?></td></tr>
            <tr style="font-weight:700;"><td>Sumas iguales</td><td class="col-sumas" colspan="2"></td><td colspan="2"></td>
                <td class="r col-extra"><?= $N($cr['iguales']['activo']) ?></td><td class="r col-extra"><?= $N($cr['iguales']['pasivo']) ?></td>
                <td class="r col-extra"><?= $N($nat['iguales_perdidas']) ?></td><td class="r col-extra"><?= $N($nat['iguales_ganancias']) ?></td><td class="r col-extra"><?= $N($fun['iguales_perdidas']) ?></td><td class="r col-extra"><?= $N($fun['iguales_ganancias']) ?></td></tr>
        </tfoot>
    </table>
    </div>
    <div id="bc-vacio" class="gc-empty" style="display:none;padding:32px;">Ninguna cuenta coincide con la búsqueda</div>
</section>
<style>
  #bc-tabla.solo-saldos .col-sumas, #bc-tabla.solo-saldos .col-extra { display: none; }
  .bc-grupo.cerrado .bc-fila { display: none; } .bc-grupo.cerrado .bc-flecha { display: inline-block; transform: rotate(-90deg); }
  .bc-fila:hover { background: var(--gc-brand-soft) !important; } .bc-detalle td { background: #faf9f6; padding: 14px 24px !important; }
</style>
<script>
(function () {
    const MENSUAL = <?= json_encode($mensual) ?>, MES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Setiembre','Octubre','Noviembre','Diciembre'];
    const f2 = n => Number(n).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const tabla = document.getElementById('bc-tabla');
    document.querySelectorAll('[data-vista]').forEach(b => b.addEventListener('click', function () {
        document.querySelectorAll('[data-vista]').forEach(x => x.classList.toggle('is-active', x === this)); tabla.classList.toggle('solo-saldos', this.dataset.vista === 'saldos');
    }));
    const q = document.getElementById('bc-q'), norm = s => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    q.addEventListener('input', function () {
        const t = norm(q.value); let n = 0;
        document.querySelectorAll('.bc-fila').forEach(tr => { const ok = !t || norm(tr.dataset.q).indexOf(t) >= 0; tr.style.display = ok ? '' : 'none'; if (ok) n++; });
        document.querySelectorAll('.bc-grupo').forEach(g => { g.style.display = (!t || g.querySelector('.bc-fila:not([style*="none"])')) ? '' : 'none'; if (t) g.classList.remove('cerrado'); });
        document.getElementById('bc-vacio').style.display = n ? 'none' : 'block';
    });
    // detalle mensual de la cuenta, debajo de su fila
    document.querySelectorAll('.bc-fila').forEach(tr => tr.addEventListener('click', function () {
        const sig = this.nextElementSibling;
        if (sig && sig.classList.contains('bc-detalle')) { sig.remove(); return; }
        document.querySelectorAll('.bc-detalle').forEach(x => x.remove());
        const cod = this.dataset.cuenta, mov = MENSUAL[cod] || [];
        let filas = mov.map(m => '<tr><td>' + MES[m[0] - 1] + '</td><td class="r">' + (m[1] ? f2(m[1]) : '—') + '</td><td class="r">' + (m[2] ? f2(m[2]) : '—') + '</td></tr>').join('');
        const d = document.createElement('tr'); d.className = 'bc-detalle';
        d.innerHTML = '<td colspan="11"><div class="gc-row between" style="margin-bottom:8px;"><b>Movimiento de la cuenta ' + cod + ' por mes</b><a class="gc-btn" href="/empresas/<?= $id ?>/mayor?cuenta=' + cod + '">Ver en el Mayor</a></div>'
            + '<table class="gc-table compacta" style="max-width:520px;background:#fff;border:1px solid var(--gc-line);border-radius:10px;"><thead><tr><th>Mes</th><th class="r">Debe</th><th class="r">Haber</th></tr></thead><tbody>' + (filas || '<tr><td colspan="3" class="gc-sub">Solo saldo de apertura.</td></tr>') + '</tbody></table></td>';
        this.after(d);
    }));
})();
</script>
<?php endif; ?>
