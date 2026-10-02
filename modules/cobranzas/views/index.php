<?php
$esVenta   = $origen === 'venta';
$tipoLabel = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC ', '08' => 'ND ', '00' => 'OTR'];
$lblPagado = $esVenta ? 'Cobrado' : 'Pagado';
$lblSaldo  = $esVenta ? 'Por cobrar' : 'Por pagar';
$lblAccion = $esVenta ? 'Registrar cobro' : 'Registrar pago';
$fmt       = fn($v) => number_format((float)$v, 2);
$base      = "/empresas/{$empresa['id']}/cobranzas";
$estados   = ['todos' => 'Todos', 'credito' => 'A crédito', 'parcial' => 'Parciales', 'pagado' => $esVenta ? 'Cobrados' : 'Pagados'];
$badge     = [
    'pagado'  => ['var(--gc-pos-soft)',  'var(--gc-pos)',  $esVenta ? 'Cobrado' : 'Pagado'],
    'parcial' => ['var(--gc-warn-soft)', 'var(--gc-warn)', 'Parcial'],
    'credito' => ['var(--gc-neg-soft)',  'var(--gc-neg)',  'A crédito'],
    'nota'    => ['var(--gc-surface-2)', 'var(--gc-label)', 'Nota de crédito'],
];
$inp = 'padding:6px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:12px;color:var(--gc-ink);background:var(--gc-surface);';
?>
<div class="gc-content gc-w-content">
    <?php $subtabActiva = 'cobranzas'; require ROOT . '/views/layout/comprobantes_subtabs.php'; ?>

    <?php if (!empty($_SESSION['cobranza_ok'])): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ <?= htmlspecialchars($_SESSION['cobranza_ok']) ?>
    </div>
    <?php unset($_SESSION['cobranza_ok']); endif; ?>
    <?php if (!empty($_SESSION['cobranza_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['cobranza_error']) ?>
    </div>
    <?php unset($_SESSION['cobranza_error']); endif; ?>

    <?php if (!empty($sinClasificar)): ?>
    <div style="background:var(--gc-warn-soft);color:var(--gc-warn);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ Hay <?= number_format($sinClasificar) ?> <?= $esVenta ? 'venta(s)' : 'compra(s)' ?> de este período <strong>sin clasificar</strong>: no aparecen aquí hasta que las
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="color:var(--gc-warn);text-decoration:underline;">clasifiques en Imputación</a>.
    </div>
    <?php endif; ?>

    <div style="margin-bottom:14px;">
        <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Cobros y pagos</div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            Al clasificar se asume que todo está cobrado/pagado. Aquí lo corriges cuando un cliente pagó solo una parte o la operación fue a crédito.
            Cada cobro o pago genera su movimiento en Caja; regenera los asientos del mes para verlo en el Diario.
        </div>
    </div>

    <!-- Filtros -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Tipo</label>
            <?php foreach (['venta' => ['📄', 'Ventas (por cobrar)'], 'compra' => ['🧾', 'Compras (por pagar)']] as $val => [$ico, $lbl]): $act = $origen === $val; ?>
            <a href="<?= $base ?>?origen=<?= $val ?>&periodo=<?= $periodo ?>&estado=<?= $estado ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-purple)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $ico ?> <?= $lbl ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Período</label>
            <?php if (empty($periodos)): ?><span style="font-size:13px;color:var(--gc-muted);">Aún no hay comprobantes clasificados</span><?php endif; ?>
            <?php foreach ($periodos as $p): $act = $p === $periodo; ?>
            <a href="<?= $base ?>?origen=<?= $origen ?>&periodo=<?= $p ?>&estado=<?= $estado ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= Periodo::etiqueta($p) ?>
            </a>
            <?php endforeach; ?>
            <?php if (!empty($periodos) && !in_array($periodo, $periodos, true)): ?>
            <span style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;background:var(--gc-brand);color:var(--gc-on-brand);"><?= Periodo::etiqueta($periodo) ?> (sin clasificados)</span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Estado</label>
            <?php foreach ($estados as $val => $lbl): $act = $estado === $val; ?>
            <a href="<?= $base ?>?origen=<?= $origen ?>&periodo=<?= $periodo ?>&estado=<?= $val ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $act ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $act ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
                <?= $lbl ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Resumen del período -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:16px;">
        <?php foreach ([
            ['Total del período', 'S/ ' . $fmt($resumen['total']), 'var(--gc-ink)'],
            [$lblPagado, 'S/ ' . $fmt($resumen['pagado']), 'var(--gc-pos)'],
            [$lblSaldo, 'S/ ' . $fmt($resumen['saldo']), $resumen['saldo'] > 0 ? 'var(--gc-neg)' : 'var(--gc-pos)'],
            ['A crédito / parciales', $resumen['credito'] . ' / ' . $resumen['parcial'], 'var(--gc-warn)'],
        ] as [$t, $v, $col]): ?>
        <div style="background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:12px;padding:14px 18px;">
            <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;"><?= $t ?></div>
            <div style="font-size:20px;font-weight:700;color:<?= $col ?>;font-family:monospace;margin-top:4px;"><?= $v ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($resumen['credito'] + $resumen['parcial'] > 0): ?>
    <form method="POST" action="<?= $base ?>/todo" onsubmit="return confirm('¿Marcar como <?= $esVenta ? 'cobrados' : 'pagados' ?> por su saldo todos los comprobantes de este período?');" style="margin-bottom:12px;">
        <input type="hidden" name="origen" value="<?= $origen ?>">
        <input type="hidden" name="periodo" value="<?= $periodo ?>">
        <input type="hidden" name="estado" value="<?= $estado ?>">
        <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:9px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
            ✓ Marcar todo el período como <?= $esVenta ? 'cobrado' : 'pagado' ?> (por su saldo)
        </button>
    </form>
    <?php endif; ?>

    <?php if (empty($docs)): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:50px;text-align:center;color:var(--gc-muted);font-size:14px;">
        No hay comprobantes <?= $estado !== 'todos' ? 'en ese estado ' : '' ?>en <?= Periodo::etiqueta($periodo) ?>.
        Los comprobantes solo aparecen aquí una vez <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="color:var(--gc-brand);">clasificados</a>.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:1px solid var(--gc-line);">
                    <th style="padding:10px 14px;text-align:left;color:var(--gc-label-2);">Comprobante</th>
                    <th style="padding:10px 14px;text-align:left;color:var(--gc-label-2);"><?= $esVenta ? 'Cliente' : 'Proveedor' ?></th>
                    <th style="padding:10px 14px;text-align:right;color:var(--gc-label-2);">Total</th>
                    <th style="padding:10px 14px;text-align:right;color:var(--gc-label-2);"><?= $lblPagado ?></th>
                    <th style="padding:10px 14px;text-align:right;color:var(--gc-label-2);"><?= $lblSaldo ?></th>
                    <th style="padding:10px 14px;text-align:center;color:var(--gc-label-2);">Estado</th>
                    <th style="padding:10px 14px;text-align:left;color:var(--gc-label-2);">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($docs as $d): [$bgB, $fgB, $txtB] = $badge[$d['estado']]; ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 14px;white-space:nowrap;">
                        <span style="font-family:monospace;font-weight:700;color:var(--gc-label);font-size:12px;"><?= $tipoLabel[$d['tipo_comp']] ?? $d['tipo_comp'] ?></span>
                        <span style="font-family:monospace;"><?= htmlspecialchars($d['serie']) ?>-<?= htmlspecialchars($d['correlativo']) ?></span>
                        <div style="font-size:11px;color:var(--gc-muted);"><?= $d['fecha_emision'] ?></div>
                    </td>
                    <td style="padding:8px 14px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars((string)$d['contraparte']) ?>">
                        <?= htmlspecialchars((string)$d['contraparte']) ?>
                    </td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;"><?= $fmt($d['total']) ?></td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;color:var(--gc-pos);"><?= $fmt($d['pagado']) ?></td>
                    <td style="padding:8px 14px;text-align:right;font-family:monospace;font-weight:700;color:<?= $d['saldo'] > 0.01 ? 'var(--gc-neg)' : 'var(--gc-muted)' ?>;"><?= $fmt(max($d['saldo'], 0)) ?></td>
                    <td style="padding:8px 14px;text-align:center;">
                        <span style="background:<?= $bgB ?>;color:<?= $fgB ?>;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;"><?= $txtB ?></span>
                    </td>
                    <td style="padding:8px 14px;">
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <?php if ($d['estado'] !== 'pagado' && $d['estado'] !== 'nota'): ?>
                            <form method="POST" action="<?= $base ?>/registrar" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="origen" value="<?= $origen ?>">
                                <input type="hidden" name="periodo" value="<?= $periodo ?>">
                                <input type="hidden" name="estado" value="<?= $estado ?>">
                                <input type="hidden" name="documento_id" value="<?= $d['id'] ?>">
                                <input type="number" name="monto" step="0.01" min="0.01" max="<?= $d['saldo'] ?>" value="<?= $d['saldo'] ?>" required title="Monto" style="<?= $inp ?>width:100px;text-align:right;font-family:monospace;">
                                <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" title="Fecha del <?= $esVenta ? 'cobro' : 'pago' ?>" style="<?= $inp ?>">
                                <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;"><?= $lblAccion ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ((float)$d['pagado'] > 0): ?>
                            <form method="POST" action="<?= $base ?>/credito" onsubmit="return confirm('¿Pasar a crédito? Se borran sus <?= (int)$d['movs'] ?> movimiento(s) de Caja.');">
                                <input type="hidden" name="origen" value="<?= $origen ?>">
                                <input type="hidden" name="periodo" value="<?= $periodo ?>">
                                <input type="hidden" name="estado" value="<?= $estado ?>">
                                <input type="hidden" name="documento_id" value="<?= $d['id'] ?>">
                                <button type="submit" style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;">Pasar a crédito</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
