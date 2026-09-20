<?php
$tipoLabel = ['01' => 'FAC', '03' => 'BOL', '07' => 'NC ', '08' => 'ND ', '00' => 'OTR'];
$labelMes  = fn($p) => date('M Y', strtotime(substr($p, 0, 4) . '-' . substr($p, 4, 2) . '-01'));
?>
<?php require ROOT . '/views/layout/empresa_tabs.php'; ?>

<div style="max-width:900px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">🏷️ Clasificación de comprobantes</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['imputacion_error'])): ?>
    <div style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['imputacion_error']) ?>
    </div>
    <?php unset($_SESSION['imputacion_error']); endif; ?>

    <div id="im-feedback"></div>

    <!-- Filtros: período + tipo -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;flex-direction:column;gap:12px;">
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Período</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if (empty($periodosConPendientes)): ?>
                    <span style="font-size:13px;color:#94a3b8;">Sin pendientes en ningún período</span>
                <?php endif; ?>
                <?php foreach ($periodosConPendientes as $p):
                    $activo = $p === $periodo;
                ?>
                <a href="?periodo=<?= $p ?>&tipo=<?= $tipoFiltro ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                    <?= $labelMes($p) ?>
                </a>
                <?php endforeach; ?>
                <?php if (!in_array($periodo, $periodosConPendientes, true)): ?>
                <span style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;background:#1e3a8a;color:white;">
                    <?= $labelMes($periodo) ?> (sin pendientes)
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <label style="font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Tipo</label>
            <div style="display:flex;gap:8px;">
                <?php foreach (['todos' => 'Todos', 'ventas' => 'Solo ventas', 'compras' => 'Solo compras'] as $val => $lbl):
                    $activo = $tipoFiltro === $val;
                ?>
                <a href="?periodo=<?= $periodo ?>&tipo=<?= $val ?>"
                   style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                          background:<?= $activo ? '#5b21b6' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                    <?= $lbl ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($pendientes)): ?>

    <!-- Barra de selección múltiple -->
    <div id="im-bulkbar" style="display:none;position:sticky;top:0;z-index:10;background:#1e293b;color:white;border-radius:12px;padding:12px 18px;margin-bottom:12px;align-items:center;gap:12px;flex-wrap:wrap;">
        <span id="im-bulk-count" style="font-weight:700;font-size:13px;"></span>
        <select id="im-bulk-select" style="flex:1;min-width:200px;padding:7px 10px;border-radius:8px;border:none;font-size:13px;">
            <option value="">Seleccionar cuenta para todos…</option>
        </select>
        <button id="im-bulk-apply" style="background:#1e3a8a;color:white;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
            ✓ Aplicar a seleccionados
        </button>
        <button id="im-bulk-clear" type="button" style="background:transparent;color:#cbd5e1;border:none;font-size:12px;cursor:pointer;text-decoration:underline;">
            Cancelar selección
        </button>
        <span id="im-bulk-warning" style="color:#fca5a5;font-size:12px;font-weight:600;"></span>
    </div>

    <?php
        $cantVentas  = count(array_filter($pendientes, fn($d) => $d['origen'] === 'venta'));
        $cantCompras = count(array_filter($pendientes, fn($d) => $d['origen'] === 'compra'));
    ?>
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
        <span style="font-size:12px;color:#94a3b8;font-weight:700;">Seleccionar:</span>
        <?php if ($cantVentas > 0): ?>
        <button type="button" class="im-select-todos" data-origen="venta"
                style="background:#dcfce7;color:#166534;border:none;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
            Todas las ventas (<?= $cantVentas ?>)
        </button>
        <?php endif; ?>
        <?php if ($cantCompras > 0): ?>
        <button type="button" class="im-select-todos" data-origen="compra"
                style="background:#fef3c7;color:#92400e;border:none;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
            Todas las compras (<?= $cantCompras ?>)
        </button>
        <?php endif; ?>
        <button type="button" id="im-select-ninguno"
                style="background:#f1f5f9;color:#475569;border:none;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
            Ninguno
        </button>
    </div>

    <div id="im-lista" style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($pendientes as $doc):
            $esVenta  = $doc['origen'] === 'venta';
            $tipos    = $esVenta ? $tiposVenta : $tiposCompra;
            $sugerida = $sugerencias[$doc['origen'] . ':' . $doc['contraparte_doc']] ?? null;
        ?>
        <div class="im-card" data-origen="<?= $doc['origen'] ?>" data-id="<?= $doc['id'] ?>"
             style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:14px 18px;transition:opacity .25s;">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <input type="checkbox" class="im-check" style="margin-top:4px;width:16px;height:16px;flex-shrink:0;">
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:10px;">
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <span style="background:<?= $esVenta ? '#dcfce7' : '#fef3c7' ?>;color:<?= $esVenta ? '#166534' : '#92400e' ?>;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                                <?= $esVenta ? 'VENTA' : 'COMPRA' ?>
                            </span>
                            <span style="font-family:monospace;font-weight:700;color:#475569;font-size:12px;">
                                <?= $tipoLabel[$doc['tipo_comp']] ?? $doc['tipo_comp'] ?>
                            </span>
                            <span style="font-family:monospace;font-weight:600;">
                                <?= htmlspecialchars($doc['serie']) ?>-<?= htmlspecialchars($doc['correlativo']) ?>
                            </span>
                            <span style="color:#94a3b8;font-size:12px;">· <?= $doc['fecha_emision'] ?></span>
                        </div>
                        <div style="font-family:monospace;font-weight:700;font-size:15px;color:#1e293b;">
                            S/ <?= number_format((float)$doc['monto_neto'], 2) ?>
                        </div>
                    </div>
                    <div style="font-size:13px;color:#475569;margin-bottom:10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                         title="<?= htmlspecialchars($doc['contraparte_nombre']) ?>">
                        <?= htmlspecialchars($doc['contraparte_nombre']) ?>
                    </div>
                    <form class="im-form" method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/clasificar" style="display:flex;gap:8px;">
                        <input type="hidden" name="origen" value="<?= $doc['origen'] ?>">
                        <input type="hidden" name="documento_id" value="<?= $doc['id'] ?>">
                        <select name="tipo_gasto_id" required
                                style="flex:1;min-width:0;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1e293b;">
                            <option value="">Seleccionar cuenta…</option>
                            <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] === $sugerida ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nombre_visible']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="im-btn" style="flex-shrink:0;background:#1e3a8a;color:white;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                            ✓ Confirmar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div id="im-vacio" style="display:none;background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">✅</div>
        <div style="font-size:16px;font-weight:600;color:#475569;">Todo clasificado en este período.</div>
    </div>

    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">✅</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">
            No hay comprobantes pendientes en <?= $labelMes($periodo) ?><?= $tipoFiltro !== 'todos' ? ' (' . ($tipoFiltro === 'ventas' ? 'ventas' : 'compras') . ')' : '' ?>
        </div>
        <div style="font-size:13px;">
            Prueba otro período o revisa que ya se haya sincronizado desde SIRE.
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const URL_LOTE = '/empresas/<?= $empresa['id'] ?>/imputacion/clasificar-lote';
    const TIPOS = {
        venta: <?= json_encode($tiposVenta ?? [], JSON_UNESCAPED_UNICODE) ?>,
        compra: <?= json_encode($tiposCompra ?? [], JSON_UNESCAPED_UNICODE) ?>
    };

    const lista   = document.getElementById('im-lista');
    const vacio   = document.getElementById('im-vacio');
    const bulkbar = document.getElementById('im-bulkbar');
    if (!lista) return; // no había pendientes, nada que cablear

    function feedback(msg, ok) {
        const box = document.getElementById('im-feedback');
        box.innerHTML = '<div style="background:' + (ok ? '#f0fdf4' : '#fef2f2') + ';color:' + (ok ? '#166534' : '#991b1b') +
            ';border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">' +
            (ok ? '✓ ' : '⚠ ') + msg + '</div>';
        setTimeout(() => { box.innerHTML = ''; }, 4000);
    }

    function quitarTarjeta(card) {
        card.style.opacity = '0';
        setTimeout(() => {
            card.remove();
            if (!lista.querySelector('.im-card')) {
                lista.style.display = 'none';
                bulkbar.style.display = 'none';
                vacio.style.display = 'block';
            }
        }, 200);
    }

    async function clasificarLote(items) {
        const resp = await fetch(URL_LOTE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ items })
        });
        return resp.json();
    }

    // --- Envío individual por tarjeta ---
    lista.addEventListener('submit', async function (e) {
        const form = e.target.closest('.im-form');
        if (!form) return;
        e.preventDefault();

        const card   = form.closest('.im-card');
        const btn    = form.querySelector('.im-btn');
        const select = form.querySelector('select[name="tipo_gasto_id"]');
        if (!select.value) return;

        btn.disabled = true; btn.textContent = 'Guardando…';
        const item = {
            origen: form.origen.value,
            documento_id: parseInt(form.documento_id.value, 10),
            tipo_gasto_id: parseInt(select.value, 10)
        };
        try {
            const data = await clasificarLote([item]);
            const r = (data.resultados || [])[0];
            if (r && r.ok) {
                feedback('Clasificado como ' + r.cuenta_codigo + ' - ' + r.cuenta_nombre + '.', true);
                quitarTarjeta(card);
            } else {
                feedback((r && r.error) || 'No se pudo clasificar.', false);
                btn.disabled = false; btn.textContent = '✓ Confirmar';
            }
        } catch (err) {
            feedback('Error de conexión al guardar.', false);
            btn.disabled = false; btn.textContent = '✓ Confirmar';
        }
    });

    // --- Selección múltiple ---
    function checksSeleccionados() {
        return Array.from(lista.querySelectorAll('.im-check:checked'));
    }

    function actualizarBarra() {
        const checks = checksSeleccionados();
        const warning = document.getElementById('im-bulk-warning');
        const select  = document.getElementById('im-bulk-select');
        const applyBtn = document.getElementById('im-bulk-apply');

        if (checks.length === 0) { bulkbar.style.display = 'none'; return; }
        bulkbar.style.display = 'flex';
        document.getElementById('im-bulk-count').textContent = checks.length + ' seleccionado(s)';

        const origenes = new Set(checks.map(c => c.closest('.im-card').dataset.origen));
        if (origenes.size > 1) {
            warning.textContent = 'Selecciona solo ventas o solo compras para clasificar en lote.';
            select.innerHTML = '<option value="">—</option>';
            select.disabled = true; applyBtn.disabled = true;
            return;
        }
        warning.textContent = '';
        select.disabled = false; applyBtn.disabled = false;
        const origen = origenes.values().next().value;
        select.innerHTML = '<option value="">Seleccionar cuenta para todos…</option>' +
            TIPOS[origen].map(t => '<option value="' + t.id + '">' + t.nombre_visible + '</option>').join('');
    }

    lista.addEventListener('change', function (e) {
        if (e.target.classList.contains('im-check')) actualizarBarra();
    });

    document.getElementById('im-bulk-clear').addEventListener('click', function () {
        checksSeleccionados().forEach(c => c.checked = false);
        actualizarBarra();
    });

    document.querySelectorAll('.im-select-todos').forEach(btn => {
        btn.addEventListener('click', function () {
            const origen = this.dataset.origen;
            lista.querySelectorAll('.im-card').forEach(card => {
                const check = card.querySelector('.im-check');
                check.checked = card.dataset.origen === origen;
            });
            actualizarBarra();
        });
    });

    document.getElementById('im-select-ninguno').addEventListener('click', function () {
        checksSeleccionados().forEach(c => c.checked = false);
        actualizarBarra();
    });

    document.getElementById('im-bulk-apply').addEventListener('click', async function () {
        const select = document.getElementById('im-bulk-select');
        if (!select.value) return;
        const checks = checksSeleccionados();
        if (!checks.length) return;

        this.disabled = true; this.textContent = 'Aplicando…';
        const items = checks.map(c => {
            const card = c.closest('.im-card');
            return { origen: card.dataset.origen, documento_id: parseInt(card.dataset.id, 10), tipo_gasto_id: parseInt(select.value, 10) };
        });

        try {
            const data = await clasificarLote(items);
            let ok = 0, fail = 0;
            (data.resultados || []).forEach(r => {
                const card = lista.querySelector('.im-card[data-origen="' + r.origen + '"][data-id="' + r.documento_id + '"]');
                if (r.ok) { ok++; if (card) quitarTarjeta(card); }
                else { fail++; }
            });
            feedback(ok + ' clasificado(s)' + (fail ? ', ' + fail + ' con error' : '') + '.', fail === 0);
        } catch (err) {
            feedback('Error de conexión al aplicar en lote.', false);
        }
        bulkbar.style.display = 'none';
        this.disabled = false; this.textContent = '✓ Aplicar a seleccionados';
    });
})();
</script>
