<?php
/** Mis empresas (pantalla de entrada). Variables: $cartera (CarteraService::resumen), $tot (totales). */
$certTxt = ['activo' => ['ok', 'Activo'], 'vencido' => ['neg', 'Vencido'], 'falta' => ['neg', 'Falta subir']];
$regTxt  = RegimenService::CORTOS;
require_once ROOT . '/services/RegimenService.php';
$fechaCorta = fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : '';
?>
<div class="gc-kpis">
    <div class="gc-kpi <?= $tot['con_vencidos'] ? 'neg' : 'ok' ?>"><span class="k">Con declaraciones vencidas</span><span class="v"><?= $tot['con_vencidos'] ?></span><span class="s"><?= $tot['con_vencidos'] ? 'Empresas con algo vencido' : 'Ninguna' ?></span></div>
    <div class="gc-kpi <?= $tot['por_vencer'] ? 'warn' : '' ?>"><span class="k">Vencen en los próximos 10 días</span><span class="v"><?= $tot['por_vencer'] ?></span><span class="s">Empresas</span></div>
    <div class="gc-kpi <?= $tot['por_clasificar'] ? 'warn' : 'ok' ?>"><span class="k">Comprobantes por clasificar</span><span class="v"><?= number_format($tot['por_clasificar']) ?></span><span class="s">En todas tus empresas</span></div>
    <div class="gc-kpi <?= $tot['sin_cert'] ? 'neg' : 'ok' ?>"><span class="k">Sin certificado digital</span><span class="v"><?= $tot['sin_cert'] ?></span><span class="s"><?= $tot['sin_cert'] ? 'Empresas sin certificado' : 'Todas tienen' ?></span></div>
</div>

<section class="gc-card clip">
    <div class="head">
        <div class="gc-row" style="flex:1 1 360px;">
            <input id="mis-q" class="gc-input" type="search" placeholder="Buscar por nombre o RUC" style="max-width:420px;" autocomplete="off">
            <span class="gc-sub" style="font-size:15px;">Ordenadas por vencimiento más cercano</span>
        </div>
        <?php if (\Auth::isContador()): ?><a href="/empresas/crear" class="gc-btn gc-btn-soft">Registrar empresa</a><?php endif; ?>
    </div>
    <?php if (!$cartera): ?>
    <div class="gc-empty"><b>Aún no tienes empresas</b>Registra la primera para empezar.</div>
    <?php else: ?>
    <div class="gc-table-wrap">
    <table class="gc-table" style="min-width:900px;">
        <thead><tr>
            <th>Empresa</th><th>Próximo vencimiento</th><th class="c">Por presentar</th><th class="c">Por clasificar</th><th>Certificado</th><th></th>
        </tr></thead>
        <tbody id="mis-filas">
        <?php foreach ($cartera as $e):
            $p = $e['proximo'];
            $tono = $p ? VencimientosService::tono($p['dias'], $p['estado']) : 'ok';
            [$ct, $cl] = $certTxt[$e['certificado']] ?? ['neutral', '—'];
        ?>
        <tr data-q="<?= htmlspecialchars(mb_strtolower($e['nombre'] . ' ' . $e['ruc'])) ?>">
            <td>
                <a href="/empresas/<?= $e['id'] ?>" style="color:var(--gc-ink);font-weight:700;font-size:17px;"><?= htmlspecialchars($e['nombre']) ?></a>
                <div class="mono gc-sub" style="font-size:14px;">RUC <?= htmlspecialchars($e['ruc']) ?> · <?= $regTxt[$e['regimen']] ?? $e['regimen'] ?></div>
            </td>
            <td>
                <?php if ($e['sin_obligaciones']): ?>
                    <span class="gc-sub">Sin declaraciones mensuales</span>
                <?php elseif ($p): ?>
                    <div style="font-weight:600;"><?= $fechaCorta($p['fecha']) ?> <span class="gc-sub" style="font-weight:400;font-size:15px;">· <?= htmlspecialchars($p['nombre']) ?></span></div>
                    <span class="gc-chip <?= $tono ?>"><?= htmlspecialchars(VencimientosService::textoDias($p['dias'], $p['estado'])) ?></span>
                <?php elseif ($e['obligaciones'] && $e['por_presentar'] === 0): ?>
                    <span class="gc-chip ok">Todo presentado</span>
                <?php else: ?>
                    <span class="gc-chip neutral">Fecha por cargar</span>
                <?php endif; ?>
            </td>
            <td class="c num"><?= $e['sin_obligaciones'] ? '—' : $e['por_presentar'] . ' de ' . $e['obligaciones'] ?></td>
            <td class="c"><?= $e['por_clasificar'] > 0 ? '<span class="gc-chip warn">' . number_format($e['por_clasificar']) . '</span>' : '<span class="gc-sub">Al día</span>' ?></td>
            <td><span class="gc-chip <?= $ct ?>"><?= $cl ?></span></td>
            <td class="r"><a href="/empresas/<?= $e['id'] ?>" class="gc-btn gc-btn-primary">Abrir</a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div id="mis-vacio" class="gc-empty" style="display:none;"><b>No hay empresas con ese nombre o RUC</b>Prueba con otra palabra.</div>
    <?php endif; ?>
</section>
<script>
(function () {
    var q = document.getElementById('mis-q'); if (!q) return;
    var norm = function (s) { return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    q.addEventListener('input', function () {
        var t = norm(q.value), n = 0;
        document.querySelectorAll('#mis-filas tr').forEach(function (tr) { var ok = !t || norm(tr.dataset.q).indexOf(t) >= 0; tr.style.display = ok ? '' : 'none'; if (ok) n++; });
        document.getElementById('mis-vacio').style.display = n ? 'none' : 'block';
    });
})();
</script>
