<?php
$totalEmpresas = 0;
$totalAlertas  = 0;
$empresas      = [];
$alertas       = [];

try {
    $db = Model::db();

    $totalEmpresas = (int)$db->query("SELECT COUNT(*) FROM empresas WHERE activo = 1")->fetchColumn();
    $totalAlertas  = (int)$db->query("SELECT COUNT(*) FROM alertas WHERE resuelta = 0")->fetchColumn();

    $stmtEmp = $db->prepare("
        SELECT e.id, e.ruc, e.razon_social, e.regimen,
               ec.cert_hasta,
               DATEDIFF(ec.cert_hasta, CURDATE()) as dias_cert,
               CASE
                   WHEN ec.cert_hasta IS NULL THEN 'sin_cert'
                   WHEN ec.cert_hasta < CURDATE() THEN 'vencido'
                   WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7 THEN 'critico'
                   WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 30 THEN 'por_vencer'
                   ELSE 'vigente'
               END as estado_cert
        FROM empresas e
        LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
        WHERE e.activo = 1
        ORDER BY e.razon_social
        LIMIT 10
    ");
    $stmtEmp->execute();
    $empresas = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

    $stmtAl = $db->prepare("
        SELECT a.*, e.razon_social
        FROM alertas a
        JOIN empresas e ON e.id = a.empresa_id
        WHERE a.resuelta = 0
        ORDER BY a.fecha_vence ASC
        LIMIT 5
    ");
    $stmtAl->execute();
    $alertas = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $ex) {
    $dbError = $ex->getMessage();
}
?>

<?php if (isset($dbError)): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;color:#991b1b;font-size:13px;">
    <strong>Error BD:</strong> <?= htmlspecialchars($dbError) ?>
    <br><small>Verifica que ejecutaste gesticont_db.sql en phpMyAdmin</small>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div style="background:white;border-radius:12px;padding:20px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Empresas activas</div>
        <div style="font-size:36px;font-weight:700;color:#1e3a8a;font-family:Georgia,serif;"><?= $totalEmpresas ?></div>
        <a href="/empresas" style="font-size:12px;color:#2563eb;text-decoration:none;margin-top:8px;display:inline-block;">Ver todas →</a>
    </div>
    <div style="background:white;border-radius:12px;padding:20px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Alertas pendientes</div>
        <div style="font-size:36px;font-weight:700;font-family:Georgia,serif;color:<?= $totalAlertas > 0 ? '#ef4444' : '#22c55e' ?>;"><?= $totalAlertas ?></div>
        <a href="/alertas" style="font-size:12px;color:#2563eb;text-decoration:none;margin-top:8px;display:inline-block;">Ver alertas →</a>
    </div>
    <div style="background:white;border-radius:12px;padding:20px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Acciones rápidas</div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px;">
            <a href="/empresas/crear" style="background:#1e3a8a;color:white;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;text-align:center;">+ Nueva empresa</a>
            <a href="/usuarios" style="background:#eff6ff;color:#1e3a8a;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;text-align:center;">+ Nuevo usuario</a>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;">
    <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
        <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:700;font-size:15px;color:#1e293b;">Mis empresas</span>
            <a href="/empresas" style="font-size:13px;color:#2563eb;text-decoration:none;">Ver todas →</a>
        </div>
        <?php if (empty($empresas)): ?>
        <div style="padding:50px;text-align:center;color:#94a3b8;">
            <div style="font-size:40px;margin-bottom:12px;">🏢</div>
            <div style="font-size:14px;margin-bottom:16px;">No hay empresas registradas aún.</div>
            <a href="/empresas/crear" style="background:#1e3a8a;color:white;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">+ Agregar primera empresa</a>
        </div>
        <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Empresa</th>
                    <th style="padding:10px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Certificado</th>
                    <th style="padding:10px 20px;"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($empresas as $emp):
                $certMap = [
                    'vigente'    => ['#dcfce7','#166534','Al día'],
                    'por_vencer' => ['#fef3c7','#92400e','Por vencer'],
                    'critico'    => ['#fee2e2','#991b1b','Crítico'],
                    'vencido'    => ['#fee2e2','#991b1b','Vencido'],
                    'sin_cert'   => ['#f1f5f9','#64748b','Sin cert.'],
                ];
                $cert = $certMap[$emp['estado_cert']] ?? $certMap['sin_cert'];
            ?>
                <tr style="border-top:1px solid #f1f5f9;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                    <td style="padding:14px 20px;">
                        <div style="font-size:14px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($emp['razon_social']) ?></div>
                        <div style="font-size:12px;color:#94a3b8;margin-top:2px;">RUC: <?= htmlspecialchars($emp['ruc']) ?></div>
                    </td>
                    <td style="padding:14px 20px;text-align:center;">
                        <span style="background:<?= $cert[0] ?>;color:<?= $cert[1] ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;"><?= $cert[2] ?></span>
                    </td>
                    <td style="padding:14px 20px;text-align:right;">
                        <a href="/empresas/<?= $emp['id'] ?>" style="color:#2563eb;font-size:13px;text-decoration:none;font-weight:600;">Ver →</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:white;border-radius:12px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:15px;color:#1e293b;">⚠ Alertas activas</div>
            <?php if (empty($alertas)): ?>
            <div style="padding:30px;text-align:center;color:#94a3b8;font-size:14px;">✅ Sin alertas pendientes</div>
            <?php else: ?>
            <?php foreach ($alertas as $al): ?>
            <div style="padding:14px 20px;border-top:1px solid #f1f5f9;">
                <div style="font-size:13px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($al['razon_social']) ?></div>
                <div style="font-size:12px;color:#64748b;margin-top:2px;"><?= htmlspecialchars($al['titulo']) ?></div>
                <div style="font-size:11px;color:#ef4444;margin-top:2px;">Vence: <?= date('d/m/Y', strtotime($al['fecha_vence'])) ?></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="background:#1e3a8a;border-radius:12px;padding:20px;">
            <div style="color:rgba(255,255,255,0.6);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Estado del sistema</div>
            <div style="color:white;font-size:13px;margin-bottom:6px;">✓ GestiCont v1.0 activo</div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;margin-bottom:4px;">PHP <?= phpversion() ?></div>
            <div style="color:rgba(255,255,255,0.6);font-size:12px;">gesticont.harlec.com.pe</div>
        </div>
    </div>
</div>
