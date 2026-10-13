<?php
/** Libro Mayor (rediseño). Variables: $empresa, $periodo, $cuentas, $sel (cuenta elegida), $tiposCuenta. */
$id = (int)$empresa['id']; $mes = Periodo::etiqueta($periodo, true);
$S = fn($v) => number_format(abs((float)$v), 2);
$saldoDA = fn(float $v) => $S($v) . ($v >= 0 ? ' D' : ' A');
$destino = ['activo' => 'Balance · Activo', 'pasivo' => 'Balance · Pasivo', 'patrimonio' => 'Balance · Patrimonio', 'ingreso' => 'Resultados · Ingreso', 'gasto' => 'Resultados · Gasto', 'destino' => 'Resultados · Por función'];
$contActiva = 'mayor'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Libro Mayor';
$contDesc = 'Acumulado de enero a ' . $mes . ' · ' . count($cuentas) . ' cuentas con movimiento';
$contAcciones = $cuentas ? '<a class="gc-btn" href="/empresas/' . $id . '/mayor/pdf?periodo=' . $periodo . '" target="_blank" rel="noopener">Descargar PDF</a>' : '';
require ROOT . '/views/layout/contabilidad_head.php';
?>
<?php if (!$cuentas): ?>
<div class="gc-card"><div class="gc-empty"><b>Sin movimientos acumulados hasta este período</b>Genera los asientos del <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>
<?php else: ?>
<div class="gc-row" style="align-items:flex-start;gap:24px;flex-wrap:nowrap;">

    <aside class="gc-card" style="flex:0 0 380px;max-width:100%;min-width:0;position:sticky;top:96px;">
        <div style="padding:14px;border-bottom:1px solid var(--gc-line);"><input id="my-q" class="gc-input" type="search" placeholder="Buscar cuenta por código o nombre" autocomplete="off"></div>
        <div id="my-lista" style="max-height:calc(100vh - 260px);overflow-y:auto;">
        <?php foreach ($cuentas as $c): $act = $sel && $c['codigo'] === $sel['codigo']; ?>
        <a href="?cuenta=<?= urlencode($c['codigo']) ?>" data-q="<?= htmlspecialchars(mb_strtolower($c['codigo'] . ' ' . $c['nombre'])) ?>"
           style="display:block;padding:12px 16px;border-bottom:1px solid var(--gc-surface-2);color:var(--gc-ink);border-left:4px solid <?= $act ? 'var(--gc-brand)' : 'transparent' ?>;background:<?= $act ? 'var(--gc-brand-soft)' : 'transparent' ?>;">
            <div class="gc-row between" style="flex-wrap:nowrap;gap:10px;">
                <span><b class="mono" style="font-size:15px;"><?= $c['codigo'] ?></b> <span style="font-size:15px;"><?= htmlspecialchars($c['nombre']) ?></span></span>
            </div>
            <div class="gc-sub" style="font-size:14px;">Saldo <?= $saldoDA($c['saldo']) ?></div>
        </a>
        <?php endforeach; ?>
        <div id="my-vacio" class="gc-empty" style="display:none;padding:28px;">Sin resultados</div>
        </div>
    </aside>

    <section class="gc-card clip" style="flex:1 1 560px;min-width:0;">
        <div style="padding:20px 24px;border-bottom:1px solid var(--gc-line);">
            <div class="gc-row between" style="gap:12px;">
                <div><span class="mono" style="font-size:22px;font-weight:700;color:var(--gc-brand);"><?= $sel['codigo'] ?></span> <span style="font-size:22px;font-weight:700;"><?= htmlspecialchars($sel['nombre']) ?></span></div>
                <span class="gc-chip info"><?= $destino[$tiposCuenta[$sel['codigo']] ?? ''] ?? 'Cuenta' ?></span>
            </div>
            <div class="gc-row" style="gap:32px;margin-top:12px;">
                <div><div class="gc-sub" style="font-size:15px;">Debe</div><b class="num" style="font-size:20px;">S/ <?= $S($sel['total_debe']) ?></b></div>
                <div><div class="gc-sub" style="font-size:15px;">Haber</div><b class="num" style="font-size:20px;">S/ <?= $S($sel['total_haber']) ?></b></div>
                <div><div class="gc-sub" style="font-size:15px;">Saldo</div><b class="num" style="font-size:20px;">S/ <?= $saldoDA($sel['saldo']) ?></b></div>
            </div>
        </div>
        <div class="gc-table-wrap">
        <table class="gc-table">
            <thead><tr><th>Fecha</th><th>Glosa</th><th class="r">Debe</th><th class="r">Haber</th><th class="r">Saldo acumulado</th></tr></thead>
            <tbody>
            <?php $acum = 0.0;
            if ($sel['saldo_apertura']):
                $acum = $sel['saldo_apertura']['debe'] - $sel['saldo_apertura']['haber']; ?>
                <tr style="color:var(--gc-label-2);font-style:italic;"><td></td><td>Saldo de apertura</td>
                    <td class="r"><?= $sel['saldo_apertura']['debe'] != 0 ? $S($sel['saldo_apertura']['debe']) : '—' ?></td><td class="r"><?= $sel['saldo_apertura']['haber'] != 0 ? $S($sel['saldo_apertura']['haber']) : '—' ?></td><td class="r"><?= $saldoDA($acum) ?></td></tr>
            <?php endif;
            foreach ($sel['movimientos'] as $m): $acum += (float)$m['debe'] - (float)$m['haber']; ?>
                <tr><td class="num" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($m['fecha'])) ?></td><td><?= htmlspecialchars($m['glosa']) ?></td>
                    <td class="r"><?= (float)$m['debe'] != 0 ? $S($m['debe']) : '<span style="color:var(--gc-muted);">—</span>' ?></td><td class="r"><?= (float)$m['haber'] != 0 ? $S($m['haber']) : '<span style="color:var(--gc-muted);">—</span>' ?></td>
                    <td class="r" style="font-weight:600;"><?= $saldoDA($acum) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="2">Saldo al <?= date('d/m/Y', strtotime(date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01')))) ?></td>
                <td class="r"><?= $S($sel['total_debe']) ?></td><td class="r"><?= $S($sel['total_haber']) ?></td><td class="r"><?= $saldoDA($sel['saldo']) ?></td></tr></tfoot>
        </table>
        </div>
    </section>
</div>
<script>
(function () {
    var q = document.getElementById('my-q'); if (!q) return;
    var norm = function (s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    q.addEventListener('input', function () {
        var t = norm(q.value), n = 0;
        document.querySelectorAll('#my-lista a').forEach(function (a) { var ok = !t || norm(a.dataset.q).indexOf(t) >= 0; a.style.display = ok ? 'block' : 'none'; if (ok) n++; });
        document.getElementById('my-vacio').style.display = n ? 'none' : 'block';
    });
})();
</script>
<?php endif; ?>
