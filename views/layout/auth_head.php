<?php
/** Cabecera común de las pantallas sin sesión (login, recuperar acceso, errores). Define $tituloAuth. */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tituloAuth ?? 'GestiCont') ?> — GestiCont</title>
<link rel="icon" href="data:,">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;500;600;700&family=Atkinson+Hyperlegible+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="/public/css/theme.css?v=<?= @filemtime(ROOT . '/public/css/theme.css') ?>" rel="stylesheet">
<link href="/public/css/ui.css?v=<?= @filemtime(ROOT . '/public/css/ui.css') ?>" rel="stylesheet">
<style>
  .gc-auth { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
  .gc-auth-box { width: 100%; max-width: 460px; display: flex; flex-direction: column; gap: 20px; }
  .gc-auth-card { background: var(--gc-surface); border: 1px solid var(--gc-line); border-radius: 18px; padding: 32px 32px 28px; }
  .gc-auth-logo { display: flex; align-items: center; justify-content: center; gap: 12px; }
  .gc-auth-logo .gc-logo-mark { width: 44px; height: 44px; font-size: 24px; border-radius: 11px; }
  .gc-auth-logo .gc-logo-text { font-size: 26px; }
  .gc-auth h1 { margin: 0 0 6px; font-size: 26px; font-weight: 700; letter-spacing: -0.01em; }
  .gc-auth .lead { margin: 0 0 24px; color: var(--gc-label-2); font-size: 17px; }
  .gc-auth form { display: flex; flex-direction: column; gap: 18px; }
  .gc-pw { position: relative; }
  .gc-pw .gc-input { padding-right: 112px; }
  .gc-pw button { position: absolute; right: 6px; top: 6px; bottom: 6px; min-width: 96px; border: 1px solid var(--gc-line-2); border-radius: 8px; background: #fff; font-size: 15px; font-weight: 600; cursor: pointer; color: var(--gc-label); }
  .gc-pw button:hover { border-color: var(--gc-brand); }
  .gc-auth .pie { text-align: center; color: var(--gc-label-2); font-size: 15px; }
</style>
</head>
