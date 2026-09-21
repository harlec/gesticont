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
            [$base . '/mayor', 'Libro Mayor'],
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

// Sección actual, para mostrarla junto a la empresa en la barra de
// identidad — libera el espacio que antes ocupaba el bloque de breadcrumb
// + título repetido en base.php (ver ahí: solo se muestra para pantallas
// fuera de una empresa, donde no hay otro lugar que lo indique).
$seccionActual = null;
if ($dentroDeEmpresa && isset($pageTitle)) {
    $seccionActual = strpos($pageTitle, ' — ') !== false ? explode(' — ', $pageTitle)[0] : 'Resumen';
}
?>
<div style="position:sticky;top:0;z-index:40;width:100%;">
    <!-- Barra de identidad — dos bloques atómicos (izquierda/derecha) que
         se envuelven completos a una segunda línea si no caben juntos, en
         vez de que cada ítem intente encogerse por su cuenta (eso es lo
         que rompía el diseño en pantallas angostas: unos ítems no podían
         encoger y el resto no alcanzaba a envolver a tiempo). -->
    <div class="gc-identity-bar" style="background:var(--gc-brand);padding:9px 24px;">
        <div style="display:flex;align-items:center;gap:14px;min-width:0;flex:1 1 auto;">
            <a href="/dashboard" style="display:flex;align-items:center;gap:9px;text-decoration:none;flex-shrink:0;">
                <div style="width:30px;height:30px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:16px;font-weight:700;color:var(--gc-on-brand);">G</div>
                <div style="font-family:Lora,serif;font-size:15px;font-weight:600;color:var(--gc-on-brand);">Gesti<span style="color:var(--gc-accent-light);">Cont</span></div>
            </a>
            <?php if ($dentroDeEmpresa): ?>
            <div class="gc-hide-narrow" style="width:1px;height:22px;background:rgba(255,255,255,0.18);flex-shrink:0;"></div>
            <a href="/empresas/<?= $empresa['id'] ?>" class="gc-empresa-pill" style="display:flex;flex-direction:column;text-decoration:none;padding:4px 10px;border-radius:8px;background:rgba(255,255,255,0.1);min-width:0;overflow:hidden;">
                <span style="font-size:12.5px;font-weight:700;color:var(--gc-on-brand);line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($empresa['razon_social']) ?></span>
                <span style="font-size:9.5px;color:rgba(255,255,255,0.65);font-family:monospace;white-space:nowrap;">RUC <?= htmlspecialchars($empresa['ruc']) ?></span>
            </a>
            <?php endif; ?>
            <?php if ($seccionActual): ?>
            <div class="gc-hide-narrow" style="width:1px;height:22px;background:rgba(255,255,255,0.18);flex-shrink:0;"></div>
            <span class="gc-hide-narrow" style="font-size:13px;font-weight:600;color:rgba(255,255,255,0.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($seccionActual) ?></span>
            <?php endif; ?>
        </div>
        <div style="display:flex;align-items:center;gap:14px;flex-shrink:0;">
            <div style="position:relative;display:inline-block;">
                <button type="button" class="gc-menu-trigger" data-menu="menu-tema" title="Tema"
                        style="display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:999px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.14);color:var(--gc-on-brand);font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;">
                    <span id="gc-tema-dot" style="width:11px;height:11px;border-radius:50%;background:var(--gc-brand);border:1px solid rgba(255,255,255,0.4);"></span>
                    <span id="gc-tema-label" class="gc-hide-narrow">Claro</span> <span style="font-size:8px;">▾</span>
                </button>
                <div class="gc-dropdown" id="menu-tema" style="display:none;position:absolute;top:100%;right:0;left:auto;min-width:190px;background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:0 0 10px 10px;box-shadow:0 16px 34px -18px rgba(22,41,79,0.35);padding:6px 0;z-index:50;">
                    <div style="padding:6px 14px 3px;font-size:9px;font-weight:700;letter-spacing:.14em;color:var(--gc-muted);text-transform:uppercase;">Tema</div>
                    <?php foreach ([
                        ['claro', 'Claro', '#1e3a8a'],
                        ['menta', 'Menta', '#1f5136'],
                        ['lavanda', 'Lavanda', '#3f3566'],
                        ['durazno', 'Durazno', '#6d3125'],
                        ['oscuro', 'Oscuro', '#23262e'],
                    ] as [$val, $lbl, $dot]): ?>
                    <button type="button" class="gc-tema-opt" data-tema="<?= $val ?>" style="display:flex;align-items:center;gap:9px;width:100%;padding:7px 14px;font-size:13px;color:var(--gc-ink);background:none;border:none;cursor:pointer;text-align:left;font-family:inherit;">
                        <span style="width:12px;height:12px;border-radius:50%;background:<?= $dot ?>;flex-shrink:0;"></span>
                        <?= $lbl ?>
                    </button>
                    <?php endforeach; ?>
                    <div style="padding:8px 14px 3px;font-size:9px;font-weight:700;letter-spacing:.14em;color:var(--gc-muted);text-transform:uppercase;border-top:1px solid var(--gc-line);margin-top:4px;">Acento</div>
                    <div style="display:flex;gap:8px;padding:8px 14px 4px;">
                        <?php foreach ([
                            ['azul', '#1e3a8a'], ['verde', '#1f5136'], ['morado', '#5a2b5c'], ['marron', '#7a3b18'],
                        ] as [$val, $dot]): ?>
                        <button type="button" class="gc-acento-opt" data-acento="<?= $val ?>" title="<?= ucfirst($val) ?>"
                                style="width:22px;height:22px;border-radius:50%;background:<?= $dot ?>;border:2px solid transparent;cursor:pointer;padding:0;"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <span class="gc-hide-narrow" style="font-size:11px;color:rgba(255,255,255,0.65);font-family:monospace;"><?= date('d/m/Y') ?></span>
            <a href="/alertas" title="Alertas" style="width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,0.1);display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px;flex-shrink:0;">🔔</a>
            <div class="gc-hide-narrow" style="width:1px;height:26px;background:rgba(255,255,255,0.18);"></div>
            <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                <div style="width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;color:var(--gc-on-brand);font-size:12px;font-weight:700;flex-shrink:0;">
                    <?= strtoupper(substr($user['nombre'] ?: 'U', 0, 2)) ?>
                </div>
                <div class="gc-hide-narrow" style="line-height:1.25;">
                    <div style="font-size:12px;font-weight:600;color:var(--gc-on-brand);white-space:nowrap;"><?= htmlspecialchars($user['nombre'] ?? '') ?></div>
                    <div style="font-size:9.5px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:.5px;"><?= htmlspecialchars($user['rol'] ?? '') ?></div>
                </div>
                <a href="/logout" title="Cerrar sesión" style="color:rgba(255,255,255,0.7);font-size:15px;text-decoration:none;margin-left:2px;">↪</a>
            </div>
        </div>
    </div>

    <!-- Barra de menú -->
    <div style="background:var(--gc-surface);border-bottom:1px solid var(--gc-line);padding:0 20px;">
        <div class="gc-menu-bar" id="gc-menu-bar">
        <?php foreach ($items as $i => $item):
            $tieneHijos = !empty($item['hijos']);
            $activo = false;
            if ($tieneHijos) {
                foreach ($item['hijos'] as [$href, ]) { if ($navCoincide($href)) { $activo = true; break; } }
            } else {
                $activo = $navCoincide($item['href']);
            }
            $colorActivo = $activo ? 'var(--gc-brand)' : 'var(--gc-label-2)';
            $borderActivo = $activo ? 'var(--gc-brand)' : 'transparent';
        ?>
            <?php if ($tieneHijos): ?>
            <div style="position:relative;display:inline-block;">
                <button type="button" class="gc-menu-trigger" data-menu="menu-<?= $i ?>"
                        style="display:inline-flex;align-items:center;gap:5px;padding:12px 12px;font-size:12px;font-weight:600;background:none;border:none;cursor:pointer;font-family:inherit;
                               border-bottom:2px solid <?= $borderActivo ?>;color:<?= $colorActivo ?>;">
                    <?= htmlspecialchars($item['label']) ?> <span style="font-size:9px;">▾</span>
                </button>
                <div class="gc-dropdown" id="menu-<?= $i ?>" style="display:none;position:absolute;top:100%;left:0;min-width:250px;background:var(--gc-surface);border:1px solid var(--gc-line);border-radius:0 0 10px 10px;box-shadow:0 16px 34px -18px rgba(22,41,79,0.35);padding:6px 0;z-index:50;white-space:nowrap;">
                    <?php foreach ($item['hijos'] as [$href, $label]):
                        $itemActivo = $navCoincide($href);
                    ?>
                    <a href="<?= $href ?>" style="display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 16px;font-size:13px;text-decoration:none;color:<?= $itemActivo ? 'var(--gc-brand)' : 'var(--gc-ink)' ?>;font-weight:<?= $itemActivo ? '700' : '500' ?>;background:<?= $itemActivo ? 'var(--gc-brand-soft)' : 'transparent' ?>;">
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

    var temaLabels = { claro: 'Claro', menta: 'Menta', lavanda: 'Lavanda', durazno: 'Durazno', oscuro: 'Oscuro' };
    var temaDots = { claro: '#1e3a8a', menta: '#1f5136', lavanda: '#3f3566', durazno: '#6d3125', oscuro: '#23262e' };
    function pintarPildora() {
        var tema = document.documentElement.getAttribute('data-theme') || 'claro';
        var dot = document.getElementById('gc-tema-dot');
        var lbl = document.getElementById('gc-tema-label');
        if (dot) dot.style.background = temaDots[tema] || temaDots.claro;
        if (lbl) lbl.textContent = temaLabels[tema] || 'Claro';
    }
    pintarPildora();
    document.querySelectorAll('.gc-tema-opt').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var tema = btn.getAttribute('data-tema');
            document.documentElement.setAttribute('data-theme', tema);
            try { localStorage.setItem('gc_theme', tema); } catch (err) {}
            pintarPildora();
            cerrar();
        });
    });
    document.querySelectorAll('.gc-acento-opt').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var acento = btn.getAttribute('data-acento');
            document.documentElement.setAttribute('data-accent', acento);
            try { localStorage.setItem('gc_accent', acento); } catch (err) {}
            cerrar();
        });
    });
})();
</script>
