<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:800px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div>
            <div style="font-size:20px;font-weight:700;color:#1e293b;">📈 Estado de Cambios en el Patrimonio</div>
            <div style="font-size:13px;color:#94a3b8;margin-top:2px;"><?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?></div>
        </div>
        <a href="/empresas/<?= $empresa['id'] ?>" style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">← Empresa</a>
    </div>

    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:#475569;">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="/empresas/<?= $empresa['id'] ?>/cambios-patrimonio?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? '#1e3a8a' : '#f1f5f9' ?>;color:<?= $activo ? 'white' : '#475569' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                    <th style="padding:10px 16px;text-align:left;color:#64748b;font-weight:700;"></th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Capital</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Reservas Legales</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Resultados Acumulados</th>
                    <th style="padding:10px 16px;text-align:right;color:#64748b;font-weight:700;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px 16px;color:#475569;">Saldo Inicial</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['capital_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['reservas_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['resultados_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($resultado['total_inicial']) ?></td>
                </tr>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px 16px;color:#475569;">(+) Utilidad Neta del Ejercicio</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['utilidad_neta']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($resultado['utilidad_neta']) ?></td>
                </tr>
                <tr style="border-top:1px solid #f1f5f9;">
                    <td style="padding:8px 16px;color:#475569;">(→) Traslado a Reserva Legal</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">+<?= $fmt($resultado['reserva_legal']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">−<?= $fmt($resultado['reserva_legal']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;">0.00</td>
                </tr>
                <tr style="border-top:2px solid #e2e8f0;font-weight:700;background:#f8fafc;">
                    <td style="padding:10px 16px;">Saldo Final</td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['capital_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['reservas_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['resultados_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['total_final']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div style="font-size:11px;color:#94a3b8;margin-top:10px;">
        No hay módulo de aportes/retiros de capital todavía — el Capital se mantiene fijo entre el saldo inicial y el final.
    </div>
    <?php else: ?>
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:60px;text-align:center;color:#94a3b8;">
        <div style="font-size:40px;margin-bottom:16px;">📈</div>
        <div style="font-size:16px;font-weight:600;color:#475569;margin-bottom:8px;">Sin datos para este período</div>
        <div style="font-size:13px;">Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:#1e3a8a;text-decoration:underline;">Libro Diario</a> primero.</div>
    </div>
    <?php endif; ?>
</div>
