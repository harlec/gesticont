<?php
/**
 * Navegación única y horizontal de GestiCont — reemplaza el sidebar fijo
 * (views/layout/sidebar.php) y la barra superior (views/layout/topbar.php).
 * Dos franjas: identidad (logo, empresa activa, usuario) y menú (enlaces
 * y desplegables). Se decide automáticamente si el usuario está "dentro"
 * de una empresa mirando si $empresa fue definido por el controlador antes
 * de incluir base.php — no requiere que cada vista la solicite aparte.
 *
 * Los grupos reflejan solo módulos que existen hoy (ver core/App.php). Se
 * dejaron fuera a propósito ítems de referencias de diseño (Archivo/Plan
 * contable editable, Centro de Costos, Recibos por Honorarios, Boletas de
 * pago aparte, Transferencias) porque no hay pantalla real detrás todavía.
 */
$dentroDeEmpresa = isset($empresa) && $empresa;
$navPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';

if ($dentroDeEmpresa) {
    $base = '/empresas/' . $empresa['id'];
    $items = [
        ['label' => 'Panel', 'href' => '/dashboard'],
        ['label' => 'Empresa', 'hijos' => [
            [$base, 'Resumen'],
            [$base . '/editar', 'Editar datos'],
            [$base . '/certificado', 'Certificado / Credenciales SUNAT'],
        ]],
        ['label' => 'Comprobantes', 'hijos' => [
            [$base . '/ventas', 'Ventas'],
            [$base . '/compras', 'Compras'],
            [$base . '/sync', 'Sincronizar SIRE'],
            [$base . '/imputacion', 'Clasificar'],
        ]],
        ['label' => 'Apertura', 'href' => $base . '/apertura'],
        ['label' => 'Planillas', 'href' => $base . '/planillas'],
        ['label' => 'Caja', 'href' => $base . '/caja'],
        ['label' => 'Contabilidad', 'hijos' => [
            [$base . '/diario', 'Libro Diario'],
            [$base . '/balance', 'Balance de Comprobación'],
            [$base . '/balance-general', 'Balance General'],
            [$base . '/resultados', 'Estado de Resultados'],
            [$base . '/cambios-patrimonio', 'Cambios en el Patrimonio'],
            [$base . '/flujo-efectivo', 'Flujo de Efectivo'],
            [$base . '/cierre', 'Cierre de Período'],
        ]],
        ['label' => '← Todas mis empresas', 'href' => '/empresas'],
    ];
} else {
    $items = [
        ['label' => 'Panel', 'href' => '/dashboard'],
        ['label' => 'Empresas', 'hijos' => [
            ['/empresas', 'Todas mis empresas'],
            ['/empresas/crear', '+ Registrar nueva empresa'],
        ]],
    ];
    if (\Auth::isContador()) $items[] = ['label' => 'Usuarios', 'href' => '/usuarios'];
    $items[] = ['label' => 'Alertas', 'href' => '/alertas'];
}

// Un href hace match por prefijo (para cubrir sub-acciones como
// .../planillas/plantilla o .../certificado vía POST) salvo que sea, a su
// vez, prefijo literal de OTRO href del menú (p.ej. "/empresas" de "Todas
// mis empresas" es prefijo de "/empresas/5"; el Resumen de una empresa,
// "/empresas/5", es prefijo de "/empresas/5/ventas") — en ese caso exige
// coincidencia exacta para no marcar dos ítems activos a la vez.
$todosLosHrefs = [];
foreach ($items as $item) {
    if (!empty($item['hijos'])) { foreach ($item['hijos'] as [$h, ]) $todosLosHrefs[] = $h; }
    else $todosLosHrefs[] = $item['href'];
}
$navCoincide = function (string $href) use ($navPath, $todosLosHrefs): bool {
    if ($navPath === $href) return true;
    foreach ($todosLosHrefs as $otro) { if ($otro !== $href && strpos($otro, $href . '/') === 0) return false; }
    return strpos($navPath, $href . '/') === 0;
};

$user = \Auth::user() ?? ['nombre' => '', 'rol' => ''];
?>
<div style="position:sticky;top:0;z-index:40;">
    <!-- Barra de identidad -->
    <div style="background:#1e3a8a;padding:9px 24px;display:flex;align-items:center;gap:14px;">
        <a href="/dashboard" style="display:flex;align-items:center;gap:9px;text-decoration:none;">
            <div style="width:30px;height:30px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:16px;font-weight:700;color:white;">G</div>
            <div style="font-family:Lora,serif;font-size:15px;font-weight:600;color:white;">Gesti<span style="color:#93c5fd;">Cont</span></div>
        </a>
        <?php if ($dentroDeEmpresa): ?>
        <div style="width:1px;height:22px;background:rgba(255,255,255,0.18);"></div>
        <a href="/empresas/<?= $empresa['id'] ?>" style="display:flex;flex-direction:column;text-decoration:none;padding:4px 10px;border-radius:8px;background:rgba(255,255,255,0.1);">
            <span style="font-size:12.5px;font-weight:700;color:white;line-height:1.3;"><?= htmlspecialchars($empresa['razon_social']) ?></span>
            <span style="font-size:9.5px;color:rgba(255,255,255,0.65);font-family:monospace;">RUC <?= htmlspecialchars($empresa['ruc']) ?></span>
        </a>
        <?php endif; ?>
        <div style="flex:1;"></div>
        <span style="font-size:11px;color:rgba(255,255,255,0.65);font-family:monospace;"><?= date('d/m/Y') ?></span>
        <a href="/alertas" title="Alertas" style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px;">🔔</a>
        <div style="width:1px;height:26px;background:rgba(255,255,255,0.18);"></div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:700;flex-shrink:0;">
                <?= strtoupper(substr($user['nombre'] ?: 'U', 0, 2)) ?>
            </div>
            <div style="line-height:1.25;">
                <div style="font-size:12px;font-weight:600;color:white;white-space:nowrap;"><?= htmlspecialchars($user['nombre'] ?? '') ?></div>
                <div style="font-size:9.5px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:.5px;"><?= htmlspecialchars($user['rol'] ?? '') ?></div>
            </div>
            <a href="/logout" title="Cerrar sesión" style="color:rgba(255,255,255,0.7);font-size:15px;text-decoration:none;margin-left:2px;">↪</a>
        </div>
    </div>

    <!-- Barra de menú -->
    <div style="background:white;border-bottom:1px solid #e2e8f0;padding:0 20px;overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch;">
        <div style="display:inline-flex;gap:2px;" id="gc-menu-bar">
        <?php foreach ($items as $i => $item):
            $tieneHijos = !empty($item['hijos']);
            $activo = false;
            if ($tieneHijos) {
                foreach ($item['hijos'] as [$href, ]) { if ($navCoincide($href)) { $activo = true; break; } }
            } else {
                $activo = $navCoincide($item['href']);
            }
            $colorActivo = $activo ? '#1e3a8a' : '#64748b';
            $borderActivo = $activo ? '#1e3a8a' : 'transparent';
        ?>
            <?php if ($tieneHijos): ?>
            <div style="position:relative;display:inline-block;">
                <button type="button" class="gc-menu-trigger" data-menu="menu-<?= $i ?>"
                        style="display:inline-flex;align-items:center;gap:5px;padding:12px 12px;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;font-family:inherit;
                               border-bottom:2px solid <?= $borderActivo ?>;color:<?= $colorActivo ?>;">
                    <?= htmlspecialchars($item['label']) ?> <span style="font-size:9px;">▾</span>
                </button>
                <div class="gc-dropdown" id="menu-<?= $i ?>" style="display:none;position:absolute;top:100%;left:0;min-width:250px;background:white;border:1px solid #e2e8f0;border-radius:0 0 10px 10px;box-shadow:0 16px 34px -18px rgba(22,41,79,0.35);padding:6px 0;z-index:50;white-space:nowrap;">
                    <?php foreach ($item['hijos'] as [$href, $label]):
                        $itemActivo = $navCoincide($href);
                    ?>
                    <a href="<?= $href ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 16px;font-size:13px;text-decoration:none;color:<?= $itemActivo ? '#1e3a8a' : '#1e293b' ?>;font-weight:<?= $itemActivo ? '700' : '500' ?>;background:<?= $itemActivo ? '#eff6ff' : 'transparent' ?>;">
                        <?= htmlspecialchars($label) ?>
                        <?php if ($itemActivo): ?><span>✓</span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <a href="<?= $item['href'] ?>"
               style="display:inline-flex;align-items:center;padding:12px 12px;font-size:12px;font-weight:600;text-decoration:none;
                      border-bottom:2px solid <?= $borderActivo ?>;color:<?= $colorActivo ?>;">
                <?= htmlspecialchars($item['label']) ?>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<script>
(function () {
    var abierto = null;
    function cerrar() {
        if (!abierto) return;
        var d = document.getElementById(abierto);
        if (d) d.style.display = 'none';
        abierto = null;
    }
    document.querySelectorAll('.gc-menu-trigger').forEach(function (btn) {
        var id = btn.getAttribute('data-menu');
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (abierto === id) { cerrar(); return; }
            cerrar();
            var d = document.getElementById(id);
            if (d) { d.style.display = 'block'; abierto = id; }
        });
        btn.addEventListener('mouseenter', function () {
            if (abierto && abierto !== id) {
                cerrar();
                var d = document.getElementById(id);
                if (d) { d.style.display = 'block'; abierto = id; }
            }
        });
    });
    document.addEventListener('click', cerrar);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });
})();
</script>
