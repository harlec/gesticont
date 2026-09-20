<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'GestiCont') ?> — GestiCont</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}a{text-decoration:none;}</style>
</head>
<body style="background:#f8fafc;color:#1e293b;min-height:100vh;">
<?php require_once ROOT . '/views/layout/nav.php'; ?>
<main style="min-height:100vh;display:flex;flex-direction:column;">
  <div style="background:white;border-bottom:1px solid #e2e8f0;padding:14px 28px 12px;">
    <nav style="font-size:11px;color:#94a3b8;margin-bottom:2px;">GestiCont / <?= htmlspecialchars($pageTitle ?? '') ?></nav>
    <h1 style="font-family:Lora,Georgia,serif;font-size:19px;font-weight:600;color:#1e293b;"><?= htmlspecialchars($pageTitle ?? 'Panel') ?></h1>
  </div>
  <div style="padding:28px;">
    <?php if (isset($content)) echo $content; ?>
    <?php if (isset($viewFile) && file_exists($viewFile)) require $viewFile; ?>
  </div>
</main>
<div id="gc-toast" style="position:fixed;bottom:24px;right:24px;background:#1e3a8a;color:white;padding:14px 20px;border-radius:10px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:8px;z-index:9999;transform:translateY(80px);opacity:0;transition:all .3s;pointer-events:none;">
  <span id="gc-toast-msg"></span>
</div>
<script src="/public/js/app.js"></script>
</body>
</html>
