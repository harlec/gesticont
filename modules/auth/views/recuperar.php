<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><title>Recuperar acceso — GestiCont</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Lora:wght@400;600&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>body{font-family:'Plus Jakarta Sans',sans-serif;}.input-field{width:100%;padding:11px 14px;border:1.5px solid var(--gc-line);border-radius:10px;font-size:14px;outline:none;transition:border-color .15s;}.input-field:focus{border-color:var(--gc-brand);}</style>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
<div class="w-full max-w-md">
  <div class="flex items-center justify-center gap-3 mb-8">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:var(--gc-brand);"><span style="font-family:Georgia,serif;font-size:20px;font-weight:700;color:var(--gc-on-brand);">G</span></div>
    <div style="font-family:Lora,serif;font-size:18px;font-weight:600;color:var(--gc-brand);">Gesti<span style="color:var(--gc-link);">Cont</span></div>
  </div>
  <div class="bg-white rounded-2xl p-8 border border-slate-100" style="box-shadow:0 4px 6px rgba(0,0,0,0.05),0 20px 40px rgba(30,58,138,0.08);">
    <?php if ($msg === 'enviado'): ?>
    <div class="text-center py-4">
      <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" style="background:var(--gc-pos-soft);"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--gc-pos)" stroke-width="2"><polyline points="20,6 9,17 4,12"/></svg></div>
      <h2 style="font-family:Lora,serif;font-size:22px;font-weight:600;color:var(--gc-ink);margin-bottom:8px;">Correo enviado</h2>
      <p style="color:var(--gc-label-2);font-size:14px;margin-bottom:24px;">Si el correo está registrado recibirás las instrucciones pronto.</p>
      <a href="/login" style="color:var(--gc-link);font-size:14px;font-weight:600;">← Volver al login</a>
    </div>
    <?php else: ?>
    <h2 style="font-family:Lora,serif;font-size:24px;font-weight:600;color:var(--gc-ink);margin-bottom:6px;">Recuperar acceso</h2>
    <p style="color:var(--gc-muted);font-size:14px;margin-bottom:24px;">Ingresa tu correo y te enviamos las instrucciones.</p>
    <?php if ($msg === 'error'): ?><div style="background:var(--gc-neg-soft);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px;margin-bottom:16px;font-size:13px;color:var(--gc-neg);">Ingresa un correo válido.</div><?php endif; ?>
    <form method="POST" action="/recuperar">
      <input type="hidden" name="_token" value="<?= AuthController::csrfToken() ?>">
      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Correo electrónico</label>
        <input type="email" name="email" class="input-field" placeholder="tu@correo.com" required autofocus/>
      </div>
      <button type="submit" style="width:100%;padding:13px;background:var(--gc-brand);color:var(--gc-on-brand);border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;font-family:'Plus Jakarta Sans',sans-serif;">Enviar instrucciones</button>
    </form>
    <div style="text-align:center;margin-top:20px;"><a href="/login" style="font-size:12px;color:var(--gc-muted);font-weight:600;">← Volver al login</a></div>
    <?php endif; ?>
  </div>
</div>
</body></html>
