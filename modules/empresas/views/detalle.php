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
<div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:14px 18px;margin-bottom:20px;color:var(--gc-neg);font-size:13px;">
    <strong>Error:</strong> <?= htmlspecialchars($dbError) ?>
</div>
<?php endif; ?>

<!-- Encabezado empresa + botones -->
<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap;">
    <div style="width:52px;height:52px;background:var(--gc-brand);border-radius:12px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:22px;font-weight:700;color:var(--gc-on-brand);flex-shrink:0;">
        <?= strtoupper(substr($empresa['razon_social'], 0, 1)) ?>
    </div>
    <div style="flex:1;min-width:200px;">
        <div style="font-size:20px;font-weight:700;color:var(--gc-ink);"><?= htmlspecialchars($empresa['razon_social']) ?></div>
        <div style="font-size:13px;color:var(--gc-muted);margin-top:2px;">
            RUC: <?= htmlspecialchars($empresa['ruc']) ?> &middot; <?= $regimenes[$empresa['regimen']] ?? $empresa['regimen'] ?>
        </div>
    </div>
    <!-- La navegación entre secciones ahora vive en la barra de pestañas de arriba — aquí solo la acción de editar los datos de la empresa. -->
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="/empresas/<?= $empresa['id'] ?>/editar"
           style="background:var(--gc-surface-2);color:var(--gc-label);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">
            ✏️ Editar
        </a>
    </div>
</div>

<!-- Cards resumen -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
    <div style="background:var(--gc-surface);border-radius:12px;padding:18px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Ventas este mes</div>
        <div style="font-size:26px;font-weight:700;color:var(--gc-brand);font-family:Georgia,serif;">
            S/ <?= number_format((float)($ultimasVentas['total'] ?? 0), 2) ?>
        </div>
        <div style="font-size:12px;color:var(--gc-muted);margin-top:4px;display:flex;justify-content:space-between;align-items:center;">
            <span><?= (int)($ultimasVentas['cant'] ?? 0) ?> comprobantes</span>
            <a href="/empresas/<?= $empresa['id'] ?>/ventas" style="color:var(--gc-brand);text-decoration:none;font-weight:600;">Ver →</a>
        </div>
    </div>
    <div style="background:var(--gc-surface);border-radius:12px;padding:18px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Compras este mes</div>
        <div style="font-size:26px;font-weight:700;color:var(--gc-ink);font-family:Georgia,serif;">
            S/ <?= number_format((float)($ultimasCompras['total'] ?? 0), 2) ?>
        </div>
        <div style="font-size:12px;color:var(--gc-muted);margin-top:4px;display:flex;justify-content:space-between;align-items:center;">
            <span><?= (int)($ultimasCompras['cant'] ?? 0) ?> facturas</span>
            <a href="/empresas/<?= $empresa['id'] ?>/compras" style="color:var(--gc-warn);text-decoration:none;font-weight:600;">Ver →</a>
        </div>
    </div>
    <div style="background:var(--gc-surface);border-radius:12px;padding:18px;border:1px solid var(--gc-line);box-shadow:0 1px 4px rgba(0,0,0,0.05);">
        <div style="font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Certificado digital</div>
        <?php if ($cert): ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-pos);">✓ Credenciales activas</div>
            <div style="font-size:12px;color:var(--gc-muted);margin-top:4px;">
                Ambiente: <?= ucfirst($cert['ambiente'] ?? 'beta') ?>
                <?php if (!empty($cert['api_client_id'])): ?> · API SIRE ✓<?php endif; ?>
            </div>
            <a href="/empresas/<?= $empresa['id'] ?>/certificado" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:4px;display:block;">Gestionar →</a>
        <?php else: ?>
            <div style="font-size:14px;font-weight:700;color:var(--gc-warn);">⚠ Sin credenciales SOL</div>
            <a href="/empresas/<?= $empresa['id'] ?>/certificado" style="font-size:12px;color:var(--gc-link);text-decoration:none;margin-top:4px;display:block;">Configurar ahora →</a>
        <?php endif; ?>
    </div>
</div>

<!-- Historial mensual -->
<?php if (!empty($resumen)): ?>
<div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--gc-line);font-weight:700;font-size:15px;color:var(--gc-ink);">
        Historial mensual
    </div>
    <table style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="background:var(--gc-bg);">
                <th style="padding:10px 20px;text-align:left;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Período</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Ventas</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Compras</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">IGV a pagar</th>
                <th style="padding:10px 20px;text-align:right;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Renta</th>
                <th style="padding:10px 20px;text-align:center;font-size:11px;font-weight:700;color:var(--gc-muted);text-transform:uppercase;letter-spacing:1px;">Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($resumen as $r): ?>
            <?php
            $estados = [
                'generado'           => ['var(--gc-brand-soft)','var(--gc-brand)','Generado'],
                'pendiente_revision' => ['var(--gc-warn-soft)','var(--gc-warn)','Pendiente'],
                'revisado'           => ['var(--gc-pos-soft)','var(--gc-pos)','Revisado'],
                'declarado'          => ['var(--gc-pos-soft)','var(--gc-pos)','Declarado'],
            ];
            $est     = $estados[$r['estado']] ?? ['var(--gc-surface-2)','var(--gc-label-2)','—'];
            $periodo = substr($r['periodo'],0,4) . '/' . substr($r['periodo'],4,2);
            ?>
            <tr style="border-top:1px solid var(--gc-surface-2);"
                onmouseover="this.style.background='var(--gc-bg)'"
                onmouseout="this.style.background='var(--gc-surface)'">
                <td style="padding:12px 20px;font-size:14px;font-weight:600;color:var(--gc-ink);"><?= $periodo ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:var(--gc-ink);">S/ <?= number_format((float)$r['base_ventas'],2) ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:var(--gc-ink);">S/ <?= number_format((float)$r['base_compras'],2) ?></td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;font-weight:600;color:<?= (float)$r['igv_a_pagar'] > 0 ? 'var(--gc-neg)' : 'var(--gc-pos)' ?>;">
                    S/ <?= number_format((float)$r['igv_a_pagar'],2) ?>
                </td>
                <td style="padding:12px 20px;text-align:right;font-size:14px;color:var(--gc-ink);">S/ <?= number_format((float)$r['renta_a_pagar'],2) ?></td>
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
<div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);padding:40px;text-align:center;color:var(--gc-muted);font-size:14px;">
    <div style="font-size:32px;margin-bottom:12px;">📊</div>
    Sin datos mensuales aún.
    <a href="/empresas/<?= $empresa['id'] ?>/sync" style="color:var(--gc-brand);font-weight:600;text-decoration:none;">
        🔄 Sincronizar SIRE
    </a>
    para importar los datos.
</div>
<?php endif; ?>
