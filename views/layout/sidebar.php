<aside style="width:240px;background:white;border-right:2px solid #e2e8f0;display:flex;flex-direction:column;position:fixed;inset-y:0;left:0;z-index:50;">
  <div style="padding:20px;border-bottom:2px solid #e2e8f0;background:#1e3a8a;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;background:rgba(255,255,255,0.15);border-radius:10px;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:20px;font-weight:700;color:white;">G</div>
      <div><div style="font-family:Lora,serif;font-size:17px;font-weight:600;color:white;">Gesti<span style="color:#93c5fd;">Cont</span></div><div style="font-size:10px;color:rgba(147,197,253,0.75);letter-spacing:2px;">SISTEMA CONTABLE</div></div>
    </div>
  </div>
  <nav style="flex:1;padding:16px 12px;overflow-y:auto;">
    <?php $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>
    <?php function navItem($href, $label, $icon, $badge = null) { $active = strpos($_SERVER['REQUEST_URI'], $href) === 0 && $href !== '/dashboard' || $_SERVER['REQUEST_URI'] === $href; $bg = $active ? 'background:#eff6ff;color:#1e3a8a;border-left:3px solid #1e3a8a;' : 'color:#475569;'; echo "<a href='$href' style='display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;margin-bottom:3px;font-size:14px;font-weight:500;text-decoration:none;transition:all .15s;$bg'><span style='width:18px;flex-shrink:0;'>$icon</span>$label" . ($badge ? "<span style='margin-left:auto;background:#ef4444;color:white;font-size:11px;font-weight:700;padding:1px 7px;border-radius:20px;'>$badge</span>" : '') . "</a>"; } ?>
    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;padding:10px 12px 6px;">Principal</div>
    <?php navItem('/dashboard', 'Inicio', '▤') ?>
    <?php navItem('/empresas', 'Mis empresas', '◉') ?>
    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;padding:10px 12px 6px;margin-top:8px;">Comprobantes</div>
    <?php navItem('/comprobantes', 'Facturas', '◧') ?>
    <?php navItem('/guias', 'Guías de remisión', '⊡') ?>
    <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:2px;padding:10px 12px 6px;margin-top:8px;">Gestión</div>
    <?php navItem('/declaraciones', 'Declaraciones', '⊞') ?>
    <?php navItem('/caja', 'Caja', '◎') ?>
    <?php navItem('/alertas', 'Vencimientos', '◈') ?>
    <?php navItem('/usuarios', 'Usuarios', '◒') ?>
  </nav>
  <div style="padding:14px 16px;border-top:2px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;gap:10px;">
    <div style="width:34px;height:34px;border-radius:50%;background:#1e3a8a;display:flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:700;flex-shrink:0;">
      <?= strtoupper(substr(\Auth::user()['nombre'] ?? 'U', 0, 2)) ?>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars(\Auth::user()['nombre'] ?? '') ?></div>
      <div style="font-size:11px;color:#94a3b8;"><?= htmlspecialchars(\Auth::user()['rol'] ?? '') ?></div>
    </div>
    <a href="/logout" style="color:#94a3b8;font-size:12px;text-decoration:none;" title="Cerrar sesión">✕</a>
  </div>
</aside>
