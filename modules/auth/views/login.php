<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — GestiCont</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link href="/public/css/theme.css" rel="stylesheet">
<script>tailwind.config={theme:{extend:{colors:{navy:{DEFAULT:'var(--gc-brand)',light:'var(--gc-link)',pale:'var(--gc-brand-soft)'}},fontFamily:{serif:['Lora','Georgia','serif'],sans:['Plus Jakarta Sans','sans-serif']}}}}</script>
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}.input-field{width:100%;padding:11px 14px;border:1.5px solid var(--gc-line);border-radius:10px;font-size:14px;outline:none;transition:border-color .15s,box-shadow .15s;font-family:'Plus Jakarta Sans',sans-serif;background:var(--gc-surface);color:var(--gc-ink);}.input-field:focus{border-color:var(--gc-brand);box-shadow:0 0 0 3px rgba(30,58,138,0.08);}.btn-navy{width:100%;padding:13px;background:var(--gc-brand);color:var(--gc-on-brand);border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;transition:background .15s;font-family:'Plus Jakarta Sans',sans-serif;}.btn-navy:hover{background:var(--gc-link);}@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}.animate-fadeUp{animation:fadeUp .4s ease both;}</style>
</head>
<body class="min-h-screen bg-slate-50 flex">
<div class="hidden lg:flex lg:w-1/2 bg-navy-DEFAULT flex-col justify-between p-12 relative overflow-hidden" style="background:var(--gc-brand);">
  <div class="absolute inset-0 opacity-5"><div class="absolute top-20 left-20 w-64 h-64 border-2 border-white rounded-full"></div><div class="absolute bottom-32 right-16 w-80 h-80 border border-white rounded-full"></div></div>
  <div class="relative flex items-center gap-3">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15);">
      <span style="font-family:Georgia,serif;font-size:22px;font-weight:700;color:var(--gc-on-brand);">G</span>
    </div>
    <div><div style="font-family:Lora,serif;font-size:20px;font-weight:600;color:var(--gc-on-brand);">Gesti<span style="color:var(--gc-accent-light);">Cont</span></div><div style="font-size:11px;color:rgba(147,197,253,0.8);letter-spacing:2px;">SISTEMA CONTABLE</div></div>
  </div>
  <div class="relative">
    <div style="color:rgba(147,197,253,0.8);font-size:12px;font-weight:600;letter-spacing:2px;text-transform:uppercase;margin-bottom:16px;">Para contadores en Perú</div>
    <h1 style="font-family:Lora,serif;font-size:38px;font-weight:600;color:var(--gc-on-brand);line-height:1.3;margin-bottom:20px;">Gestiona tus empresas<br>desde un solo lugar.</h1>
    <p style="color:rgba(147,197,253,0.85);font-size:15px;line-height:1.7;">Sincronización automática con SUNAT · Hoja de trabajo automática · Alertas de vencimientos · Panel multiempresa</p>
  </div>
  <div style="color:rgba(147,197,253,0.5);font-size:12px;">© <?= date('Y') ?> GestiCont · Perú</div>
</div>
<div class="flex-1 flex items-center justify-center p-8">
  <div class="w-full max-w-md animate-fadeUp">
    <div class="bg-white rounded-2xl p-8 border border-slate-100" style="box-shadow:0 4px 6px rgba(0,0,0,0.05),0 20px 40px rgba(30,58,138,0.08);">
      <h2 style="font-family:Lora,serif;font-size:26px;font-weight:600;color:var(--gc-ink);margin-bottom:4px;">Bienvenido</h2>
      <p style="color:var(--gc-muted);font-size:14px;margin-bottom:28px;">Ingresa tus credenciales para continuar</p>
      <?php if (!empty($error)): ?>
      <div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gc-neg);">
        <?php $msgs=['credenciales'=>'Correo o contraseña incorrectos.','campos'=>'Completa todos los campos.','csrf'=>'Sesión expirada. Recarga la página.']; echo $msgs[$error] ?? 'Error al iniciar sesión.'; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($timeout)): ?>
      <div style="background:var(--gc-warn-soft-2);border:1px solid var(--gc-warn-border);border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:var(--gc-warn);">Tu sesión expiró por inactividad.</div>
      <?php endif; ?>
      <form method="POST" action="/login">
        <input type="hidden" name="_token" value="<?= AuthController::csrfToken() ?>">
        <div style="margin-bottom:16px;">
          <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico</label>
          <input type="email" name="email" class="input-field" placeholder="tu@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus/>
        </div>
        <div style="margin-bottom:8px;">
          <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Contraseña</label>
          <div style="position:relative;">
            <input type="password" name="password" id="pwField" class="input-field" style="padding-right:44px;" placeholder="••••••••" required/>
            <button type="button" onclick="togglePw()" style="position:absolute;inset-y:0;right:12px;background:none;border:none;cursor:pointer;color:var(--gc-muted);">
              <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </div>
        <div style="text-align:right;margin-bottom:24px;"><a href="/recuperar" style="font-size:12px;font-weight:600;color:var(--gc-link);">¿Olvidaste tu contraseña?</a></div>
        <button type="submit" class="btn-navy">Ingresar a GestiCont</button>
      </form>
      <div style="background:var(--gc-brand-soft);border-radius:10px;padding:14px;margin-top:20px;font-size:12px;color:var(--gc-brand);line-height:1.5;">
        Si no tienes acceso, solicita tus credenciales al administrador de tu estudio.
      </div>
    </div>
    <p style="text-align:center;font-size:11px;color:var(--gc-muted);margin-top:20px;">© <?= date('Y') ?> GestiCont · Perú</p>
  </div>
</div>
<script>
function togglePw(){const f=document.getElementById('pwField'),i=document.getElementById('eyeIcon');if(f.type==='password'){f.type='text';i.innerHTML='<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>';}else{f.type='password';i.innerHTML='<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';}}
</script>
</body>
</html>
