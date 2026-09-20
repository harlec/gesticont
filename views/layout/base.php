<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'GestiCont') ?> — GestiCont</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link href="/public/css/theme.css" rel="stylesheet">
<link href="/public/css/nav.css" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;overflow-x:hidden;}a{text-decoration:none;}</style>
<script>
(function () {
    try {
        var t = localStorage.getItem('gc_theme');
        var a = localStorage.getItem('gc_accent');
        if (t) document.documentElement.setAttribute('data-theme', t);
        if (a) document.documentElement.setAttribute('data-accent', a);
    } catch (e) {}
})();
</script>
</head>
<body style="background:var(--gc-bg);color:var(--gc-ink);min-height:100vh;">
<?php require_once ROOT . '/views/layout/nav.php'; ?>
<main style="min-height:100vh;display:flex;flex-direction:column;">
  <?php if (!isset($empresa)): ?>
  <!-- Dentro de una empresa, la sección actual ya se muestra junto al
       nombre de la empresa en la barra de identidad (nav.php) — repetirla
       aquí era el "tanto breadcrumbs" que hacía perder espacio vertical
       en cada pantalla. Fuera de una empresa (Panel, Mis empresas,
       Usuarios, Alertas) sigue siendo la única fuente del título. -->
  <div style="background:var(--gc-surface);border-bottom:1px solid var(--gc-line);padding:14px 28px 12px;">
    <nav style="font-size:11px;color:var(--gc-muted);margin-bottom:2px;">GestiCont / <?= htmlspecialchars($pageTitle ?? '') ?></nav>
    <h1 style="font-family:Lora,Georgia,serif;font-size:19px;font-weight:600;color:var(--gc-ink);"><?= htmlspecialchars($pageTitle ?? 'Panel') ?></h1>
  </div>
  <?php endif; ?>
  <div style="padding:28px;">
    <?php if (isset($content)) echo $content; ?>
    <?php if (isset($viewFile) && file_exists($viewFile)) require $viewFile; ?>
  </div>
</main>
<div id="gc-toast" style="position:fixed;bottom:24px;right:24px;background:var(--gc-brand);color:var(--gc-on-brand);padding:14px 20px;border-radius:10px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;z-index:9999;transform:translateY(80px);opacity:0;transition:all .3s;pointer-events:none;">
  <span id="gc-toast-msg"></span>
</div>
<script src="/public/js/app.js"></script>
</body>
</html>
