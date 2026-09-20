<?php
$periodos = [];
for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));
$fmt = fn($v) => number_format((float)$v, 2);
?>

<div style="max-width:1200px;margin:0 auto;">

    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:16px 20px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:700;color:var(--gc-label);">Acumulado hasta:</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach ($periodos as $p): $activo = $p === $periodo; $label = date('M Y', strtotime(substr($p,0,4).'-'.substr($p,4,2).'-01')); ?>
            <a href="/empresas/<?= $empresa['id'] ?>/cambios-patrimonio?periodo=<?= $p ?>" style="padding:6px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;background:<?= $activo ? 'var(--gc-brand)' : 'var(--gc-surface-2)' ?>;color:<?= $activo ? 'var(--gc-on-brand)' : 'var(--gc-label)' ?>;"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($resultado): ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--gc-bg);border-bottom:2px solid var(--gc-line);">
                    <th style="padding:10px 16px;text-align:left;color:var(--gc-label-2);font-weight:700;"></th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;">Capital</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;">Reservas Legales</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;">Resultados Acumulados</th>
                    <th style="padding:10px 16px;text-align:right;color:var(--gc-label-2);font-weight:700;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 16px;color:var(--gc-label);">Saldo Inicial</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['capital_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['reservas_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['resultados_inicial']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($resultado['total_inicial']) ?></td>
                </tr>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 16px;color:var(--gc-label);">(+) Utilidad Neta del Ejercicio</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['utilidad_neta']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;"><?= $fmt($resultado['utilidad_neta']) ?></td>
                </tr>
                <tr style="border-top:1px solid var(--gc-surface-2);">
                    <td style="padding:8px 16px;color:var(--gc-label);">(→) Traslado a Reserva Legal</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">—</td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">+<?= $fmt($resultado['reserva_legal']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;">−<?= $fmt($resultado['reserva_legal']) ?></td>
                    <td style="padding:8px 16px;text-align:right;font-family:monospace;font-weight:700;">0.00</td>
                </tr>
                <tr style="border-top:2px solid var(--gc-line);font-weight:700;background:var(--gc-bg);">
                    <td style="padding:10px 16px;">Saldo Final</td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['capital_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['reservas_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['resultados_final']) ?></td>
                    <td style="padding:10px 16px;text-align:right;font-family:monospace;"><?= $fmt($resultado['total_final']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <div style="font-size:11px;color:var(--gc-muted);margin-top:10px;">
        No hay módulo de aportes/retiros de capital todavía — el Capital se mantiene fijo entre el saldo inicial y el final.
    </div>
    <?php else: ?>
    <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:60px;text-align:center;color:var(--gc-muted);">
        <div style="font-size:40px;margin-bottom:16px;">📈</div>
        <div style="font-size:16px;font-weight:600;color:var(--gc-label);margin-bottom:8px;">Sin datos para este período</div>
        <div style="font-size:13px;">Genera los asientos del <a href="/empresas/<?= $empresa['id'] ?>/diario" style="color:var(--gc-brand);text-decoration:underline;">Libro Diario</a> primero.</div>
    </div>
    <?php endif; ?>
</div>
