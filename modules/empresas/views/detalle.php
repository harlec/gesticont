<?php
$cert           = null;
$resumen        = [];
$ultimasVentas  = null;
$ultimasCompras = null;

try {
    $db = Model::db();

    $stmtCert = $db->prepare("SELECT * FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1");
    $stmtCert->execute([$empresa['id']]);
    $cert = $stmtCert->fetch(PDO::FETCH_ASSOC);

    $stmtRes = $db->prepare("SELECT * FROM resumen_mensual WHERE empresa_id = ? ORDER BY periodo DESC LIMIT 6");
    $stmtRes->execute([$empresa['id']]);
    $resumen = $stmtRes->fetchAll(PDO::FETCH_ASSOC);

    $stmtV = $db->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total),0) as total FROM registro_ventas WHERE empresa_id = ? AND periodo = DATE_FORMAT(CURDATE(),'%Y%m')");
    $stmtV->execute([$empresa['id']]);
    $ultimasVentas = $stmtV->fetch(PDO::FETCH_ASSOC);

    $stmtC = $db->prepare("SELECT COUNT(*) as cant, COALESCE(SUM(total),0) as total FROM registro_compras WHERE empresa_id = ? AND periodo = DATE_FORMAT(CURDATE(),'%Y%m')");
    $stmtC->execute([$empresa['id']]);
    $ultimasCompras = $stmtC->fetch(PDO::FETCH_ASSOC);

} catch (Exception $ex) {
    $dbError = $ex->getMessage();
}

$regimenes = ['general'=>'Régimen General','mype'=>'MYPE Tributario','especial'=>'Régimen Especial','rus'=>'Nuevo RUS'];
?>

<?php if (isset($dbError)): ?>
<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;color:#991b1b;font-size:13px;">
    <strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<!-- Encabezado empresa + botones -->
<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
    <div style="width:52px;height:52px;background:#1e3a8a;border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:22px;font-weight:700;color:white;flex-shrink:0;">
        <?= strtoupper(substr($empresa['razon_social'], 0, 1)) ?>
    </div>
    <div style="flex:1;min-width:200px;">
        <div style="font-size:20px;font-weight:700;color:#1e293b;"><?= htmlspecialchars($empresa['razon_social']) ?></div>
        <div style="font-size:13px;color:#94a3b8;margin-top:2px;">
            RUC: <?= htmlspecialchars($empresa['ruc']) ?> &middot; <?= $regimenes[$empresa['regimen']] ?? $empresa['regimen'] ?>
        </div>
    </div>
    <!-- BOTONES -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/empresas/<?= $empresa['id'] ?>/editar"
           style="background:#f1f5f9;color:#475569;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            ✏️ Editar
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/certificado"
           style="background:#fef3c7;color:#92400e;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            🔑 SOL
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/ventas"
           style="background:#dcfce7;color:#166534;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            📄 Ventas
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/compras"
           style="background:#fef3c7;color:#92400e;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            🧾 Compras
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/sync"
           style="background:#1e3a8a;color:white;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            🔄 Sincronizar
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/imputacion"
           style="background:#ede9fe;color:#5b21b6;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            🏷️ Clasificar
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/diario"
           style="background:#e0e7ff;color:#3730a3;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            📖 Diario
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/balance"
           style="background:#fce7f3;color:#9d174d;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            ⚖️ Balance
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/resultados"
           style="background:#dbeafe;color:#1e40af;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            📊 Resultados
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/balance-general"
           style="background:#e0f2fe;color:#075985;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            🏛️ Balance General
        </a>
        <a href="/empresas/<?= $empresa['id'] ?>/apertura"
           style="background:#f3f4f6;color:#374151;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            📋 Inventario Inicial
        </a>
    </div>
</div>

<!-- Cards resumen -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div style="background:white;border-radius:12px;padding:18px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Ventas este mes</div>
        <div style="font-size:26px;font-weight:700;color:#1e3a8a;font-family:Georgia,serif;">
            S/ <?= number_format((float)($ultimasVentas['total'] ?? 0), 2) ?>
        </div>
        <div style="font-size:12px;color:#94a3b8;margin-top:4px;display:flex;justify-content:space-between;align-items:center;">
            <span><?= (int)($ultimasVentas['cant'] ?? 0) ?> comprobantes</span>
            <a href="/empresas/<?= $empresa['id'] ?>/ventas" style="color:#1e3a8a;text-decoration:none;font-weight:600;">Ver →</a>
        </div>
    </div>
    <div style="background:white;border-radius:12px;padding:18px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Compras este mes</div>
        <div style="font-size:26px;font-weight:700;color:#1e293b;font-family:Georgia,serif;">
            S/ <?= number_format((float)($ultimasCompras['total'] ?? 0), 2) ?>
        </div>
        <div style="font-size:12px;color:#94a3b8;margin-top:4px;display:flex;justify-content:space-between;align-items:center;">
            <span><?= (int)($ultimasCompras['cant'] ?? 0) ?> facturas</span>
            <a href="/empresas/<?= $empresa['id'] ?>/compras" style="color:#92400e;text-decoration:none;font-weight:600;">Ver →</a>
        </div>
    </div>
    <div style="background:white;border-radius:12px;padding:18px;border:1px solid #e2e8f0;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Certificado digital</div>
        <?php if ($cert): ?>
            <div style="font-size:14px;font-weight:700;color:#166534;">✓ Credenciales activas</div>
            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                Ambiente: <?= ucfirst($cert['ambiente'] ?? 'beta') ?>
                <?php if (!empty($cert['api_client_id'])): ?> · API SIRE ✓<?php endif; ?>
            </div>
        <?php else: ?>
            <div style="font-size:14px;font-weight:700;color:#92400e;">⚠ Sin credenciales SOL</div>
            <a href="/empresas/<?= $empresa['id'] ?>/certificado" style="font-size:12px;color:#2563eb;text-decoration:none;margin-top:4px;display:block;">Configurar ahora →</a>
        <?php endif; ?>
    </div>
</div>

<!-- Historial mensual -->
<?php if (!empty($resumen)): ?>
<div style="background:white;border-radius:12px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div style="padding:16px 20px;border-bottom:1px solid #e2e8f0;font-weight:700;font-size:15px;color:#1e293b;">
        Historial mensual
    </div>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:#f8fafc;">
                <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Período</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Ventas</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Compras</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">IGV a pagar</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Renta</th>
                <th style="padding:10px 20px;text-align:center;font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;">Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($resumen as $r): ?>
            <?php
            $estados = [
                'generado'           => ['#eff6ff','#1e3a8a','Generado'],
                'pendiente_revision' => ['#fef3c7','#92400e','Pendiente'],
                'revisado'           => ['#dcfce7','#166534','Revisado'],
                'declarado'          => ['#dcfce7','#166534','Declarado'],
            ];
            $est     = $estados[$r['estado']] ?? ['#f1f5f9','#64748b','—'];
            $periodo = substr($r['periodo'],0,4) . '/' . substr($r['periodo'],4,2);
            ?>
            <tr style="border-top:1px solid #f1f5f9;"
                onmouseover="this.style.background='#f8fafc'"
                onmouseout="this.style.background='white'">
                <td style="padding:12px 20px;font-size:14px;font-weight:600;color:#1e293b;"><?= $periodo ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:#1e293b;">S/ <?= number_format((float)$r['base_ventas'],2) ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:#1e293b;">S/ <?= number_format((float)$r['base_compras'],2) ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;font-weight:600;color:<?= (float)$r['igv_a_pagar'] > 0 ? '#991b1b' : '#166534' ?>;">
                    S/ <?= number_format((float)$r['igv_a_pagar'],2) ?>
                </td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:#1e293b;">S/ <?= number_format((float)$r['renta_a_pagar'],2) ?></td>
                <td style="padding:12px 20px;text-align:center;">
                    <span style="background:<?= $est[0] ?>;color:<?= $est[1] ?>;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;">
                        <?= $est[2] ?>
                    </span>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div style="background:white;border-radius:12px;border:1px solid #e2e8f0;padding:40px;text-align:center;color:#94a3b8;font-size:14px;">
    <div style="font-size:32px;margin-bottom:12px;">📊</div>
    Sin datos mensuales aún.
    <a href="/empresas/<?= $empresa['id'] ?>/sync" style="color:#1e3a8a;font-weight:600;text-decoration:none;">
        🔄 Sincronizar SIRE
    </a>
    para importar los datos.
</div>
<?php endif; ?>
