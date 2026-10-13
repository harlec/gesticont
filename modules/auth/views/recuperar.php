<?php $tituloAuth = 'Recuperar acceso'; require ROOT . '/views/layout/auth_head.php'; ?>
<body>
<div class="gc-auth"><div class="gc-auth-box">
  <div class="gc-auth-logo"><span class="gc-logo-mark">G</span><span class="gc-logo-text">GestiCont</span></div>
  <div class="gc-auth-card">
    <?php if ($msg === 'enviado'): ?>
      <h1>Revisa tu correo</h1>
      <p class="lead">Si el correo está registrado, te enviaremos las instrucciones para crear una contraseña nueva.</p>
      <a href="/login" class="gc-btn gc-btn-primary gc-btn-lg" style="width:100%;">Volver a ingresar</a>
    <?php else: ?>
      <h1>Recuperar acceso</h1>
      <p class="lead">Escribe tu correo y te enviamos las instrucciones.</p>
      <?php if ($msg === 'error'): ?><div class="gc-alert neg" style="margin-bottom:18px;" role="alert"><span class="msg">Escribe un correo válido.</span></div><?php endif; ?>
      <form method="POST" action="/recuperar">
        <input type="hidden" name="_token" value="<?= AuthController::csrfToken() ?>">
        <div class="gc-field">
          <label class="gc-label" for="email">Correo electrónico</label>
          <input id="email" type="email" name="email" class="gc-input gc-input-lg" placeholder="tu@correo.com" required autofocus>
        </div>
        <button type="submit" class="gc-btn gc-btn-primary gc-btn-lg" style="width:100%;">Enviar instrucciones</button>
        <div class="pie"><a href="/login">← Volver a ingresar</a></div>
      </form>
    <?php endif; ?>
  </div>
</div></div>
</body></html>
