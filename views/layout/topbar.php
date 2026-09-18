<header style="background:white;border-bottom:2px solid #e2e8f0;padding:16px 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:40;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
  <div>
    <nav style="font-size:11px;color:#94a3b8;margin-bottom:2px;">GestiCont / <?= htmlspecialchars($pageTitle ?? '') ?></nav>
    <h1 style="font-family:Lora,Georgia,serif;font-size:20px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($pageTitle ?? 'Panel') ?></h1>
  </div>
  <div style="display:flex;gap:10px;align-items:center;">
    <span style="font-size:12px;color:#94a3b8;"><?= date('d/m/Y') ?></span>
    <a href="/alertas" style="width:36px;height:36px;border-radius:8px;border:2px solid #e2e8f0;background:white;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:16px;">🔔</a>
    <a href="/empresas/crear" style="background:#1e3a8a;color:white;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">+ Nueva empresa</a>
  </div>
</header>
