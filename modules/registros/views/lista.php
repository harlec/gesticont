<?php
/** Ventas / Compras del período. Variables: $tipo, $empresa, $periodo, $registros, $resumen, $q, $f, $mostrar, $totalFiltrado. */
$esV = $tipo === 'ventas'; $id = (int)$empresa['id'];
$urlBase = "/empresas/{$id}/" . $tipo;
$mes = Periodo::etiqueta($periodo, true);
$S = fn($v) => number_format((float)$v, 2);
$etqTipo = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC', '08' => 'ND', '00' => 'OTR'];
$qs = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(['q' => $q, 'f' => $f === 'todos' ? '' : $f], $extra), fn($v) => $v !== '' && $v !== null));
$chips = ['todos' => ['Todos', (int)$resumen['todos']], 'boletas' => ['Boletas', (int)$resumen['boletas']], 'facturas' => ['Facturas', (int)$resumen['facturas']], 'anulados' => ['Anulados', (int)$resumen['anulados']]];
$subtabActiva = $tipo; require ROOT . '/views/layout/comprobantes_subtabs.php';
?>
<?php if ((int)$resumen['todos'] === 0): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay comprobantes de <?= $mes ?></b>Descarga las <?= $esV ? 'ventas' : 'compras' ?> de ese mes desde SUNAT.<br><br><a href="/empresas/<?= $id ?>/sync" class="gc-btn gc-btn-primary gc-btn-lg">Sincronizar con SUNAT</a></div></div>
<?php else: ?>
<div class="gc-kpis">
    <div class="gc-kpi"><span class="k">Comprobantes</span><span class="v"><?= number_format($resumen['cant']) ?></span><span class="s">Sin contar anulados</span></div>
    <div class="gc-kpi"><span class="k">Base imponible</span><span class="v">S/ <?= $S($resumen['base']) ?></span></div>
    <div class="gc-kpi"><span class="k">IGV</span><span class="v">S/ <?= $S($resumen['igv']) ?></span></div>
    <div class="gc-kpi"><span class="k">Total</span><span class="v">S/ <?= $S($resumen['total']) ?></span></div>
</div>

<section class="gc-card clip">
    <div class="head">
        <form method="GET" action="<?= $urlBase ?>" class="gc-row" style="flex:1 1 360px;gap:8px;margin:0;flex-wrap:nowrap;">
            <?php if ($f !== 'todos'): ?><input type="hidden" name="f" value="<?= htmlspecialchars($f) ?>"><?php endif; ?>
            <input class="gc-input" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Buscar por número, <?= $esV ? 'cliente' : 'proveedor' ?> o RUC" style="max-width:420px;">
            <button class="gc-btn" type="submit">Buscar</button>
            <?php if ($q !== ''): ?><a class="gc-btn" href="<?= $urlBase . $qs(['q' => '']) ?>">Quitar búsqueda</a><?php endif; ?>
        </form>
        <div class="gc-row" style="gap:8px;">
            <?php foreach ($chips as $k => [$lbl, $n]): ?>
            <a class="gc-chipbtn <?= $f === $k ? 'is-active' : '' ?>" href="<?= $urlBase . $qs(['f' => $k === 'todos' ? '' : $k, 'n' => '']) ?>"><?= $lbl ?> (<?= number_format($n) ?>)</a>
            <?php endforeach; ?>
            <a class="gc-btn" href="<?= $urlBase ?>/excel<?= $qs([]) ?>">Descargar Excel</a>
        </div>
    </div>
    <?php if (!$registros): ?>
    <div class="gc-empty"><b>No hay comprobantes con ese filtro</b>Prueba con otra búsqueda o quita los filtros.</div>
    <?php else: ?>
    <div class="gc-table-wrap">
    <table class="gc-table" style="min-width:1000px;">
        <thead><tr>
            <th>Fecha</th><th>Comprobante</th><th><?= $esV ? 'Cliente' : 'Proveedor' ?></th>
            <th class="r">Base</th><th class="r">IGV</th><th class="r">Total</th><th>Cuenta</th>
        </tr></thead>
        <tbody>
        <?php foreach ($registros as $r):
            $anulado = $r['estado_sunat'] !== '1';
            $doc = $esV ? ($r['cliente_num_doc'] ?? '') : ($r['proveedor_ruc'] ?? '');
            $nom = $esV ? ($r['cliente_nombre'] ?? '') : ($r['proveedor_nombre'] ?? '');
        ?>
        <tr style="<?= $anulado ? 'opacity:.6;' : '' ?>">
            <td class="num" style="white-space:nowrap;"><?= date('d/m/Y', strtotime($r['fecha_emision'])) ?></td>
            <td style="white-space:nowrap;"><span style="font-size:13px;font-weight:700;color:var(--gc-label-2);"><?= $etqTipo[$r['tipo_comp']] ?? $r['tipo_comp'] ?></span> <span class="mono" style="font-size:15px;"><?= htmlspecialchars($r['serie'] . '-' . $r['correlativo']) ?></span></td>
            <td><?= htmlspecialchars($nom !== '' && $nom !== '-' ? $nom : '—') ?><div class="mono gc-sub" style="font-size:14px;"><?= $doc !== '' ? htmlspecialchars($doc) : 'Sin documento' ?></div></td>
            <td class="r"><?= $S($r['base_imponible']) ?></td><td class="r"><?= $S($r['igv']) ?></td><td class="r" style="font-weight:700;"><?= $S($r['total']) ?></td>
            <td>
                <?php if ($anulado): ?><span class="gc-chip neg">Anulado</span>
                <?php elseif ($r['estado_imputacion'] === 'pendiente'): ?><span class="gc-chip warn">Sin clasificar</span>
                <?php else: ?><span class="mono" style="font-size:15px;"><?= htmlspecialchars((string)($r['cuentas'] ?? '—')) ?></span><?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="gc-row between" style="padding:16px 24px;border-top:1px solid var(--gc-line);">
        <span class="gc-sub">Mostrando <?= number_format(count($registros)) ?> de <?= number_format($totalFiltrado) ?></span>
        <?php if (count($registros) < $totalFiltrado): ?><a class="gc-btn" href="<?= $urlBase . $qs(['n' => $mostrar + 15]) ?>">Mostrar 15 más</a><?php endif; ?>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
