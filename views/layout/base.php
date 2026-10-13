<?php
/**
 * Estructura común de todas las pantallas (rediseño): menú lateral + encabezado + contenido.
 * Los controladores arman $content (o $viewFile) y, dentro de una empresa, definen $empresa.
 */
$gcZoom = in_array($_COOKIE['gc_letra'] ?? '', ['1', '1.12', '1.25'], true) ? $_COOKIE['gc_letra'] : '1';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'GestiCont') ?> — GestiCont</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;500;600;700&family=Atkinson+Hyperlegible+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="/public/css/theme.css" rel="stylesheet">
<link href="/public/css/ui.css" rel="stylesheet">
</head>
<body>
<div class="gc-app" id="gc-app" style="--gc-zoom:<?= $gcZoom ?>">
  <?php require_once ROOT . '/views/layout/sidebar.php'; ?>
  <main class="gc-main">
    <?php require_once ROOT . '/views/layout/header.php'; ?>
    <div class="gc-content<?= !empty($gcAncho) ? ' gc-ancho' : '' ?>">
      <?php if (isset($content)) echo $content; ?>
      <?php if (isset($viewFile) && file_exists($viewFile)) require $viewFile; ?>
    </div>
  </main>
</div>

<!-- Selector de empresa (Ctrl/Cmd + K) -->
<div class="gc-modal-back" id="gc-switcher" role="dialog" aria-modal="true" aria-label="Cambiar de empresa">
  <div class="gc-modal">
    <div class="gc-row between" style="margin-bottom:14px;">
      <h2>Cambiar de empresa</h2>
      <button type="button" class="gc-btn" onclick="gcCerrarSelector()">Cerrar</button>
    </div>
    <input id="gc-sw-q" class="gc-input gc-input-lg" type="text" autocomplete="off" placeholder="Escribe el nombre o RUC">
    <div class="gc-row" style="margin:14px 0 8px;gap:8px;">
      <button type="button" class="gc-chipbtn" data-f="todas">Todas (<i>0</i>)</button>
      <button type="button" class="gc-chipbtn" data-f="vencidos">Con vencidos (<i>0</i>)</button>
      <button type="button" class="gc-chipbtn" data-f="clasificar">Por clasificar (<i>0</i>)</button>
    </div>
    <div id="gc-sw-list" style="max-height:52vh;overflow-y:auto;"></div>
    <div class="gc-row between" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--gc-line);font-size:15px;color:var(--gc-label-2);">
      <span>↑ ↓ para moverte · Enter para abrir · Esc para cerrar</span>
      <a href="/empresas">Ver todas mis empresas →</a>
    </div>
  </div>
</div>

<div id="gc-toast" role="status" aria-live="polite"></div>
<script>
window.GC_EMPRESA_ACTUAL = <?= (int)($empresa['id'] ?? 0) ?>;
window.GC_EMPRESAS = <?= json_encode(array_map(function ($e) {
    $p = $e['proximo'];
    return ['id' => $e['id'], 'nombre' => $e['nombre'], 'ruc' => $e['ruc'], 'por_clasificar' => $e['por_clasificar'], 'vencidos' => $e['vencidos'],
            'tono' => $p ? VencimientosService::tono($p['dias'], $p['estado']) : 'ok',
            'texto' => $p ? VencimientosService::textoDias($p['dias'], $p['estado']) : ($e['sin_obligaciones'] ? 'Sin declaraciones' : 'Todo presentado')];
}, $cartera ?? []), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
</script>
<script src="/public/js/app.js"></script>
</body>
</html>
