<?php $tituloAuth = 'Iniciar sesión'; require ROOT . '/views/layout/auth_head.php'; ?>
<body>
<div class="gc-auth"><div class="gc-auth-box">
  <div class="gc-auth-logo"><span class="gc-logo-mark">G</span><span class="gc-logo-text">GestiCont</span></div>
  <div class="gc-auth-card">
    <h1>Bienvenido</h1>
    <p class="lead">Ingresa con tu correo y tu contraseña.</p>

    <?php if (!empty($error)): ?>
    <div class="gc-alert neg" style="margin-bottom:18px;" role="alert"><span class="msg">
      <?php $msgs = ['credenciales' => 'El correo o la contraseña no son correctos.', 'campos' => 'Completa el correo y la contraseña.', 'csrf' => 'La sesión caducó. Recarga la página e inténtalo de nuevo.']; echo htmlspecialchars($msgs[$error] ?? 'No se pudo iniciar sesión.'); ?>
    </span></div>
    <?php endif; ?>
    <?php if (!empty($timeout)): ?>
    <div class="gc-alert warn" style="margin-bottom:18px;"><span class="msg">Tu sesión se cerró por inactividad. Vuelve a ingresar.</span></div>
    <?php endif; ?>

    <form method="POST" action="/login">
      <input type="hidden" name="_token" value="<?= AuthController::csrfToken() ?>">
      <div class="gc-field">
        <label class="gc-label" for="email">Correo electrónico</label>
        <input id="email" type="email" name="email" class="gc-input gc-input-lg" placeholder="tu@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
      </div>
      <div class="gc-field">
        <label class="gc-label" for="pwField">Contraseña</label>
        <div class="gc-pw">
          <input id="pwField" type="password" name="password" class="gc-input gc-input-lg" required autocomplete="current-password">
          <button type="button" id="pwBtn" onclick="var f=document.getElementById('pwField');var v=f.type==='password';f.type=v?'text':'password';this.textContent=v?'Ocultar':'Mostrar'">Mostrar</button>
        </div>
      </div>
      <button type="submit" class="gc-btn gc-btn-primary gc-btn-lg" style="width:100%;">Ingresar</button>
      <div class="pie"><a href="/recuperar">¿Olvidaste tu contraseña?</a></div>
    </form>
  </div>
  <p class="pie">Si no tienes acceso, pide tus datos al administrador de tu estudio.<br>© <?= date('Y') ?> GestiCont · Perú</p>
</div></div>
</body></html>
