<div class="gc-content gc-w-content">
    <?php $subtabActiva = 'imputacion'; require ROOT . '/views/layout/comprobantes_subtabs.php'; ?>

    <?php
        $esVenta   = $origen === 'venta';
        $contraLbl = $esVenta ? 'cliente' : 'proveedor';
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
        <div>
            <div style="font-size:16px;font-weight:700;color:var(--gc-ink);">Reglas de clasificación por proveedor/cliente</div>
            <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
                Define una vez a qué cuenta va cada RUC y en Clasificar aparecerá como propuesta lista para aplicar.
            </div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion" style="font-size:13px;font-weight:600;color:var(--gc-brand);text-decoration:none;">← Volver a Clasificar</a>
    </div>

    <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
        <?php foreach (['compra' => ['🧾', 'Compras (proveedores)'], 'venta' => ['📄', 'Ventas (clientes)']] as $val => [$ico, $lbl]):
            $activo = $origen === $val;
        ?>
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion/reglas?origen=<?= $val ?>"
           style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                  background:<?= $activo ? 'var(--gc-purple)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;">
            <span><?= $ico ?></span><?= $lbl ?>
            <span style="opacity:.75;font-size:11px;">· <?= (int)$conteos[$val] ?> <?= (int)$conteos[$val] === 1 ? 'regla' : 'reglas' ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($_SESSION['reglas_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['reglas_error']) ?>
    </div>
    <?php unset($_SESSION['reglas_error']); endif; ?>

    <?php if (isset($_GET['ok'])): ?>
    <div style="background:var(--gc-pos-soft-2);color:var(--gc-pos);border:1px solid var(--gc-pos-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Regla creada.
    </div>
    <?php endif; ?>

    <!-- Reglas activas -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:20px;">
        <div style="padding:14px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">
            Reglas de <?= $esVenta ? 'ventas' : 'compras' ?> configuradas
        </div>
        <?php if (empty($reglas)): ?>
        <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:13px;">
            Todavía no hay reglas para <?= $esVenta ? 'clientes' : 'proveedores' ?>. Usa el detector de abajo o el formulario manual.
        </div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--gc-line);">
                    <th style="padding:8px 20px;text-align:left;color:var(--gc-label-2);font-weight:700;">RUC del <?= $contraLbl ?></th>
                    <th style="padding:8px 20px;text-align:left;color:var(--gc-label-2);font-weight:700;">Cuenta destino</th>
                    <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Veces aplicada</th>
                    <th style="padding:8px 20px;text-align:center;color:var(--gc-label-2);font-weight:700;">Estado</th>
                    <th style="padding:8px 20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reglas as $r): ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 20px;font-family:monospace;"><?= htmlspecialchars($r['valor_criterio']) ?></td>
                    <td style="padding:8px 20px;">
                        <span style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($r['cuenta_codigo']) ?></span>
                        <?= htmlspecialchars($r['cuenta_nombre']) ?>
                    </td>
                    <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= (int)$r['veces_aplicada'] ?></td>
                    <td style="padding:8px 20px;text-align:center;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/reglas/<?= $r['id'] ?>/toggle" style="display:inline;">
                            <input type="hidden" name="origen" value="<?= $origen ?>">
                            <button type="submit" style="border:none;padding:3px 10px;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer;
                                    background:<?= $r['activa'] ? 'var(--gc-pos-soft)' : 'var(--gc-surface-2)' ?>;color:<?= $r['activa'] ? 'var(--gc-pos)' : 'var(--gc-muted)' ?>;">
                                <?= $r['activa'] ? 'Activa' : 'Inactiva' ?>
                            </button>
                        </form>
                    </td>
                    <td style="padding:8px 20px;text-align:right;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/reglas/<?= $r['id'] ?>/eliminar" style="display:inline;"
                              onsubmit="return confirm('¿Eliminar esta regla?');">
                            <input type="hidden" name="origen" value="<?= $origen ?>">
                            <button type="submit" style="background:none;border:none;color:var(--gc-neg);font-size:12px;font-weight:600;cursor:pointer;">Eliminar</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Detector de proveedores/clientes frecuentes -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:20px;">
        <div style="padding:14px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);">
            <div style="font-weight:700;font-size:13px;color:var(--gc-ink);">🔍 <?= $esVenta ? 'Clientes' : 'Proveedores' ?> de tu historial sin regla</div>
            <div style="font-size:12px;color:var(--gc-muted);margin-top:2px;">Ordenados por comprobantes pendientes de clasificar; los que se repiten en varios meses son los mejores candidatos.</div>
        </div>
        <?php if (empty($candidatas)): ?>
        <div style="padding:30px;text-align:center;color:var(--gc-muted);font-size:13px;">
            No hay <?= $esVenta ? 'ventas' : 'compras' ?> sincronizadas de <?= $esVenta ? 'clientes' : 'proveedores' ?> sin regla.
        </div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="border-bottom:1px solid var(--gc-line);">
                    <th style="padding:8px 20px;text-align:left;color:var(--gc-label-2);font-weight:700;">Contraparte</th>
                    <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Pendientes</th>
                    <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Total histórico</th>
                    <th style="padding:8px 20px;text-align:right;color:var(--gc-label-2);font-weight:700;">Monto total</th>
                    <th style="padding:8px 20px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidatas as $c): ?>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 20px;">
                        <div><?= htmlspecialchars($c['nombre'] ?: '(sin nombre)') ?></div>
                        <div style="font-family:monospace;font-size:11px;color:var(--gc-muted);"><?= htmlspecialchars($c['ruc']) ?></div>
                    </td>
                    <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= (int)$c['pendientes'] ?></td>
                    <td style="padding:8px 20px;text-align:right;font-family:monospace;"><?= (int)$c['apariciones'] ?></td>
                    <td style="padding:8px 20px;text-align:right;font-family:monospace;">S/ <?= number_format((float)$c['monto_total'], 2) ?></td>
                    <td style="padding:8px 20px;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/reglas/crear" style="display:flex;gap:6px;align-items:center;">
                            <input type="hidden" name="ruc" value="<?= htmlspecialchars($c['ruc']) ?>">
                            <input type="hidden" name="origen" value="<?= $origen ?>">
                            <select name="cuenta_id" required style="padding:6px 8px;border:1px solid var(--gc-line);border-radius:6px;font-size:12px;color:var(--gc-ink);min-width:220px;">
                                <option value="">Cuenta destino…</option>
                                <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['cuenta_id'] ?>"><?= htmlspecialchars($t['nombre_visible']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                                Crear regla
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Alta manual -->
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <div style="padding:14px 20px;background:var(--gc-bg);border-bottom:1px solid var(--gc-line);font-weight:700;font-size:13px;color:var(--gc-ink);">
            Agregar regla de <?= $esVenta ? 'cliente' : 'proveedor' ?> manualmente
        </div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/imputacion/reglas/crear" style="padding:16px 20px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <input type="hidden" name="origen" value="<?= $origen ?>">
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">RUC</label>
                <input type="text" name="ruc" required pattern="\d{8,11}" maxlength="11"
                       style="padding:8px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;color:var(--gc-ink);width:160px;" placeholder="20100...">
            </div>
            <div>
                <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Cuenta destino</label>
                <select name="cuenta_id" required style="padding:8px 10px;border:1px solid var(--gc-line);border-radius:8px;font-size:13px;color:var(--gc-ink);min-width:260px;">
                    <option value="">Seleccionar…</option>
                    <?php foreach ($tipos as $t): ?>
                    <option value="<?= $t['cuenta_id'] ?>"><?= htmlspecialchars($t['nombre_visible']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" style="background:var(--gc-brand);color:var(--gc-on-brand);border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                + Crear regla
            </button>
        </form>
    </div>
</div>
