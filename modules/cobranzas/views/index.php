<?php
/** Cobros y pagos (rediseño). Variables: $empresa, $origen, $periodo, $estado, $docs, $resumen, $sinClasificar. */
$id = (int)$empresa['id']; $esV = $origen === 'venta'; $mes = Periodo::etiqueta($periodo, true);
$S = fn($v) => number_format((float)$v, 2);
$base = "/empresas/{$id}/cobranzas";
$etq = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC', '08' => 'ND', '00' => 'OTR'];
$lblPag = $esV ? 'cobrado' : 'pagado'; $lblPagM = ucfirst($lblPag); $verbo = $esV ? 'cobro' : 'pago';
$nSaldo = (int)$resumen['credito'] + (int)$resumen['parcial']; $nPag = (int)$resumen['pagado_n']; $nTodos = $nSaldo + $nPag + (int)$resumen['notas'];
$chips = ['saldo' => ['Con saldo', $nSaldo], 'pagado' => [$lblPagM . 's', $nPag], 'todos' => ['Todos', $nTodos]];
$badge = ['pagado' => ['ok', $lblPagM], 'parcial' => ['warn', 'Parcial'], 'credito' => ['neg', 'A crédito'], 'nota' => ['neutral', 'Nota de crédito']];
$subtabActiva = 'cobranzas'; require ROOT . '/views/layout/comprobantes_subtabs.php';
?>
<?php foreach (['cobranza_ok' => 'ok', 'cobranza_error' => 'neg'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach; ?>

<?php if ($sinClasificar > 0): ?>
<div class="gc-alert warn"><span class="msg">Hay <?= number_format($sinClasificar) ?> <?= $esV ? 'venta' . ($sinClasificar === 1 ? '' : 's') : 'compra' . ($sinClasificar === 1 ? '' : 's') ?> de <?= $mes ?> sin clasificar. No aparecen aquí hasta que las clasifiques.</span>
    <a class="gc-btn" href="/empresas/<?= $id ?>/imputacion?tipo=<?= $esV ? 'ventas' : 'compras' ?>">Ir a Clasificar</a></div>
<?php endif; ?>

<div class="gc-row between">
    <div class="gc-seg">
        <a class="<?= $esV ? 'is-active' : '' ?>" href="<?= $base ?>?origen=venta&estado=<?= $estado ?>">Ventas (por cobrar)</a>
        <a class="<?= !$esV ? 'is-active' : '' ?>" href="<?= $base ?>?origen=compra&estado=<?= $estado ?>">Compras (por pagar)</a>
    </div>
    <div class="gc-row" style="gap:8px;">
        <?php foreach ($chips as $k => [$lbl, $n]): ?>
        <a class="gc-chipbtn <?= $estado === $k ? 'is-active' : '' ?>" href="<?= $base ?>?origen=<?= $origen ?>&estado=<?= $k ?>"><?= $lbl ?> (<?= number_format($n) ?>)</a>
        <?php endforeach; ?>
    </div>
</div>

<div class="gc-kpis">
    <div class="gc-kpi"><span class="k">Total de <?= $mes ?></span><span class="v">S/ <?= $S($resumen['total']) ?></span></div>
    <div class="gc-kpi ok"><span class="k">Ya <?= $lblPag ?></span><span class="v">S/ <?= $S($resumen['pagado']) ?></span></div>
    <div class="gc-kpi <?= $resumen['saldo'] > 0.01 ? 'warn' : '' ?>"><span class="k">Falta <?= $esV ? 'cobrar' : 'pagar' ?></span><span class="v">S/ <?= $S($resumen['saldo']) ?></span><span class="s"><?= $nSaldo ?> comprobante<?= $nSaldo === 1 ? '' : 's' ?> con saldo</span></div>
</div>

<section class="gc-card clip">
    <div class="head">
        <div class="gc-sub" style="max-width:640px;font-size:15px;">Al clasificar se asume que todo está <?= $lblPag ?>. Aquí corriges lo que fue a crédito o se <?= $esV ? 'cobró' : 'pagó' ?> en parte. Cada <?= $verbo ?> genera su movimiento en Caja.</div>
        <?php if ($estado === 'saldo' && $nSaldo > 0): ?>
        <form method="POST" action="<?= $base ?>/todo" onsubmit="return confirm('¿Marcar como <?= $lblPag ?>s por su saldo todos los comprobantes de este período?');" style="margin:0;">
            <input type="hidden" name="origen" value="<?= $origen ?>"><input type="hidden" name="periodo" value="<?= $periodo ?>"><input type="hidden" name="estado" value="<?= $estado ?>">
            <button class="gc-btn" type="submit">Marcar todo como <?= $lblPag ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php if (!$docs): ?>
    <div class="gc-empty"><b><?= $estado === 'saldo' ? 'No hay comprobantes con saldo en ' . $mes : 'No hay comprobantes para mostrar' ?></b><?= $estado === 'saldo' ? 'Todo lo clasificado figura como ' . $lblPag . '.' : 'Prueba con otro filtro.' ?></div>
    <?php else: ?>
    <div class="gc-table-wrap">
    <table class="gc-table" style="min-width:980px;">
        <thead><tr><th>Comprobante</th><th><?= $esV ? 'Cliente' : 'Proveedor' ?></th><th class="r">Total</th><th class="r"><?= $lblPagM ?></th><th class="r">Saldo</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): [$bt, $bl] = $badge[$d['estado']]; $hayPagos = (float)$d['pagado'] > 0; ?>
        <tr>
            <td style="white-space:nowrap;"><span style="font-size:13px;font-weight:700;color:var(--gc-label-2);"><?= $etq[$d['tipo_comp']] ?? $d['tipo_comp'] ?></span> <span class="mono" style="font-size:15px;"><?= htmlspecialchars($d['serie'] . '-' . $d['correlativo']) ?></span>
                <div class="gc-sub" style="font-size:14px;"><?= date('d/m/Y', strtotime($d['fecha_emision'])) ?></div></td>
            <td><?= htmlspecialchars((string)$d['contraparte'] !== '' && $d['contraparte'] !== '-' ? $d['contraparte'] : '—') ?></td>
            <td class="r"><?= $S($d['total']) ?></td>
            <td class="r"><?= $d['estado'] === 'nota' ? '—' : $S($d['pagado']) ?></td>
            <td class="r" style="font-weight:700;"><?= $d['estado'] === 'nota' ? '—' : $S(max($d['saldo'], 0)) ?></td>
            <td><span class="gc-chip <?= $bt ?>"><?= $bl ?></span></td>
            <td class="r" style="white-space:nowrap;">
                <?php if ($d['estado'] === 'credito' || $d['estado'] === 'parcial'): ?>
                <button type="button" class="gc-btn gc-btn-primary" onclick="cobAbrir(<?= (int)$d['id'] ?>)">Registrar <?= $verbo ?></button>
                <?php endif; ?>
                <?php if ($hayPagos && $estado !== 'saldo'): ?>
                <form method="POST" action="<?= $base ?>/credito" style="display:inline;margin:0;" onsubmit="return confirm('¿Pasar a crédito? Se borran sus <?= (int)$d['movs'] ?> movimiento(s) de Caja.');">
                    <input type="hidden" name="origen" value="<?= $origen ?>"><input type="hidden" name="periodo" value="<?= $periodo ?>"><input type="hidden" name="estado" value="<?= $estado ?>"><input type="hidden" name="documento_id" value="<?= (int)$d['id'] ?>">
                    <button class="gc-btn" type="submit">Pasar a crédito</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<!-- Registrar cobro / pago -->
<div class="gc-modal-back" id="cob-modal" onmousedown="if(event.target===this)closeModal('cob-modal')">
  <form class="gc-modal sm" method="POST" action="<?= $base ?>/registrar" id="cob-form">
    <h2>Registrar <?= $verbo ?></h2>
    <div style="margin:10px 0 18px;line-height:1.35;"><b id="cob-doc" class="mono"></b><br><span id="cob-nombre" class="gc-sub"></span></div>
    <input type="hidden" name="origen" value="<?= $origen ?>"><input type="hidden" name="periodo" value="<?= $periodo ?>"><input type="hidden" name="estado" value="<?= $estado ?>"><input type="hidden" name="documento_id" id="cob-id">
    <div class="gc-alert info" style="margin-bottom:16px;"><span class="msg">Saldo pendiente: <b id="cob-saldo"></b></span></div>
    <div class="gc-stack" style="gap:16px;">
        <div class="gc-field"><label class="gc-label" for="cob-monto">Monto</label>
            <div class="gc-row" style="flex-wrap:nowrap;"><input class="gc-input" id="cob-monto" name="monto" type="number" step="0.01" min="0.01" required><button type="button" class="gc-btn" onclick="cobTodo()">Todo el saldo</button></div>
            <div id="cob-err" class="gc-sub" style="color:var(--gc-neg);display:none;margin-top:4px;"></div></div>
        <div class="gc-field"><label class="gc-label" for="cob-fecha">Fecha</label><input class="gc-input" id="cob-fecha" name="fecha" type="date" value="<?= date('Y-m-d') ?>" required></div>
        <div class="gc-field"><label class="gc-label" for="cob-medio">Medio de pago</label>
            <select class="gc-select" id="cob-medio" name="medio"><?php foreach (CobroPagoService::MEDIOS as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="gc-row" style="justify-content:flex-end;margin-top:22px;"><button type="button" class="gc-btn" onclick="closeModal('cob-modal')">Cancelar</button><button type="submit" class="gc-btn gc-btn-primary">Guardar <?= $verbo ?></button></div>
  </form>
</div>
<script>
const COB_DOCS = <?= json_encode(array_values(array_map(fn($d) => ['id' => (int)$d['id'], 'doc' => ($etq[$d['tipo_comp']] ?? $d['tipo_comp']) . ' ' . $d['serie'] . '-' . $d['correlativo'], 'nombre' => (string)$d['contraparte'], 'saldo' => round(max($d['saldo'], 0), 2)], array_filter($docs, fn($d) => in_array($d['estado'], ['credito', 'parcial'], true)))), JSON_UNESCAPED_UNICODE) ?>;
let cobSaldo = 0;
function cobAbrir(id) {
    const d = COB_DOCS.find(x => x.id === id); if (!d) return; cobSaldo = d.saldo;
    document.getElementById('cob-id').value = id; document.getElementById('cob-doc').textContent = d.doc;
    document.getElementById('cob-nombre').textContent = d.nombre; document.getElementById('cob-saldo').textContent = formatPEN(d.saldo);
    const m = document.getElementById('cob-monto'); m.max = d.saldo; m.value = d.saldo.toFixed(2); document.getElementById('cob-err').style.display = 'none';
    openModal('cob-modal'); setTimeout(() => m.focus(), 30);
}
function cobTodo() { document.getElementById('cob-monto').value = cobSaldo.toFixed(2); }
document.getElementById('cob-form').addEventListener('submit', function (e) {
    const v = parseFloat(document.getElementById('cob-monto').value), err = document.getElementById('cob-err');
    if (!(v > 0) || v > cobSaldo + 0.01) { e.preventDefault(); err.textContent = !(v > 0) ? 'El monto debe ser mayor a cero.' : 'El monto no puede superar el saldo (' + formatPEN(cobSaldo) + ').'; err.style.display = 'block'; }
});
</script>
