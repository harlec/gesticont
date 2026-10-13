<?php
http_response_code(404);
$tituloAuth = '404';
require ROOT . '/views/layout/auth_head.php';
?>
<body>
<div class="gc-auth"><div class="gc-auth-box" style="text-align:center;">
  <div class="gc-auth-logo"><span class="gc-logo-mark">G</span><span class="gc-logo-text">GestiCont</span></div>
  <div class="gc-auth-card">
    <div style="font-size:64px;font-weight:700;color:var(--gc-line-2);line-height:1;margin-bottom:12px;">404</div>
    <h1>Página no encontrada</h1>
    <p class="lead">La página que buscas no existe o fue movida.</p>
    <a href="/empresas" class="gc-btn gc-btn-primary gc-btn-lg" style="width:100%;">Volver al inicio</a>
  </div>
</div></div>
</body></html>
