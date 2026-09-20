<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>
<?php require ROOT . '/views/layout/empresa_tabs.php'; ?>

<div style="max-width:1100px;">

    <!-- Encabezado -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">👥 Planillas</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
                <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['planilla_error'])): ?>
    <div style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['planilla_error']) ?>
    </div>
    <?php unset($_SESSION['planilla_error']); endif; ?>

    <?php if (!empty($_SESSION['planilla_ok'])): ?>
    <div style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ <?= htmlspecialchars($_SESSION['planilla_ok']) ?>
    </div>
    <?php unset($_SESSION['planilla_ok']); elseif (isset($_GET['ok'])): ?>
    <div style="background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ✓ Trabajador agregado.
    </div>
    <?php endif; ?>

    <!-- Selector de período -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Período:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="?periodo=<?= $p ?>"
               style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;">
                <?= $label ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Formulario nuevo trabajador -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:700;color:#475569;margin-bottom:12px;">+ Agregar trabajador a <?= date('M Y', strtotime(substr($periodo,0,4).'-'.substr($periodo,4,2).'-01')) ?></div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/planillas" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <div style="flex:2;min-width:180px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">TRABAJADOR</label>
                <input type="text" name="trabajador" required placeholder="Nombre completo"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">SUELDO</label>
                <input type="text" inputmode="decimal" name="sueldo" id="p-sueldo" placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">GRATIFICACIÓN</label>
                <input type="text" inputmode="decimal" name="gratificacion" placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">ASIG. FAMILIAR</label>
                <input type="text" inputmode="decimal" name="asignacion_familiar" placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">ESSALUD (9%)</label>
                <input type="text" inputmode="decimal" name="essalud" id="p-essalud" placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">RÉGIMEN</label>
                <select name="regimen_pension" style="width:100%;padding:7px 6px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;">
                    <option value="onp">ONP</option>
                    <option value="afp">AFP</option>
                    <option value="ninguno">Ninguno</option>
                </select>
            </div>
            <div style="width:110px;">
                <label style="font-size:11px;color:#94a3b8;font-weight:700;">RETENCIÓN</label>
                <input type="text" inputmode="decimal" name="retencion_pension" placeholder="0.00"
                       style="width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;text-align:right;">
            </div>
            <button type="submit" style="background:#1e3a8a;color:white;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;height:34px;">
                + Agregar
            </button>
        </form>
        <div style="font-size:11px;color:#94a3b8;margin-top:8px;">
            ESSALUD se sugiere automático al 9% del sueldo (editable). La retención de ONP/AFP se ingresa manual — cada AFP tiene comisión distinta.
        </div>
    </div>

    <!-- Importación masiva por CSV -->
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:18px 20px;margin-bottom:16px;">
        <div style="font-size:13px;font-weight:700;color:#475569;margin-bottom:4px;">📥 Importar varios trabajadores a la vez (CSV)</div>
        <div style="font-size:12px;color:#94a3b8;margin-bottom:12px;">
            No es un lector del archivo oficial de PLAME/T-Registro (ese formato no se pudo verificar con confianza) —
            es una plantilla propia: descárgala, llénala con los datos que ya tengas calculados en tu planillero, y súbela aquí.
        </div>
        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/planillas/importar" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <input type="hidden" name="periodo" value="<?= $periodo ?>">
            <a href="/empresas/<?= $empresa['id'] ?>/planillas/plantilla"
               style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
                ⬇ Descargar plantilla CSV
            </a>
            <input type="file" name="archivo" accept=".csv" required
                   style="font-size:13px;">
            <button type="submit" style="background:#1e3a8a;color:white;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;">
                📥 Importar a <?= date('M Y', strtotime(substr($periodo,0,4).'-'.substr($periodo,4,2).'-01')) ?>
            </button>
        </form>
    </div>

    <?php if (!empty($registros)): ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;">Trabajador</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Sueldo</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Gratif.</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Asig.Fam.</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">ESSALUD</th>
                    <th style="padding:10px 16px;text-align:center;color:#64748b;font-weight:700;">Régimen</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Retención</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Neto</th>
                    <th style="padding:10px 16px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registros as $r):
                $bruto = (float)$r['sueldo'] + (float)$r['gratificacion'] + (float)$r['asignacion_familiar'];
                $neto  = $bruto - (float)$r['retencion_pension'];
            ?>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px 16px;"><?= htmlspecialchars($r['trabajador']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($r['sueldo']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($r['gratificacion']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($r['asignacion_familiar']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($r['essalud']) ?></td>
                    <td style="padding:8px 16px;text-align:center;text-transform:uppercase;font-size:11px;font-weight:700;color:#475569;"><?= $r['regimen_pension'] ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($r['retencion_pension']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($neto) ?></td>
                    <td style="padding:8px 16px;text-align:center;">
                        <form method="POST" action="/empresas/<?= $empresa['id'] ?>/planillas/<?= $r['id'] ?>/eliminar" onsubmit="return confirm('¿Eliminar este registro?')">
                            <input type="hidden" name="periodo" value="<?= $periodo ?>">
                            <button type="submit" style="background:none;border:none;color:#991b1b;cursor:pointer;font-size:13px;">✕</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc;border-top:2px solid #e2e8f0;font-weight:700;">
                    <td style="padding:10px 16px;">Totales (<?= count($registros) ?>)</td>
                    <td colspan="3" style="padding:10px 16px;text-align:right;font-family:monospace;">Bruto: <?= $fmt($totales['bruto']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($totales['essalud']) ?></td>
                    <td></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($totales['retencion']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($totales['neto']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
    </div>
    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;">
        Sin trabajadores registrados en este período todavía.
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('p-sueldo').addEventListener('input', function () {
    const s = parseFloat(this.value.replace(',', '.')) || 0;
    document.getElementById('p-essalud').value = s > 0 ? (s * 0.09).toFixed(2) : '';
});
</script>
