<?php
$estadoSol = $tiene['sol_usuario'] && $tiene['sol_clave'];
$estadoApi = $tiene['api_client_id'] && $tiene['api_client_secret'];

$lblStyle = 'display:block;font-size:11px;font-weight:700;color:var(--gc-label-2);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;';

// Etiqueta con indicador: "✓ Ingresado" si el valor ya está guardado (encriptado), nada si falta.
$etiqueta = function (string $texto, bool $lleno) use ($lblStyle): string {
    $badge = $lleno
        ? '<span style="margin-left:8px;background:var(--gc-pos-soft);color:var(--gc-pos);border:1px solid var(--gc-pos-border);border-radius:999px;padding:1px 8px;font-size:10px;letter-spacing:.3px;text-transform:none;">✓ Ingresado</span>'
        : '<span style="margin-left:8px;color:var(--gc-muted);font-size:10px;letter-spacing:.3px;text-transform:none;font-weight:600;">Sin ingresar</span>';
    return '<label style="' . $lblStyle . '">' . $texto . $badge . '</label>';
};

// Campo de texto/clave. Lleno: borde verde, fondo suave y placeholder que lo dice; vacío = conserva.
$campo = function (string $name, string $tipo, bool $lleno, string $vacioPh, bool $obligatorio = false, string $colorFoco = 'var(--gc-brand)', string $extra = '') {
    $borde = $lleno ? 'var(--gc-pos-border)' : 'var(--gc-line)';
    $fondo = $lleno ? 'var(--gc-pos-soft)' : 'var(--gc-surface)';
    $ph    = $lleno ? '•••••••••••• (ya ingresado — déjalo vacío para conservarlo)' : $vacioPh;
    $req   = ($obligatorio && !$lleno) ? 'required' : '';
    return '<input type="' . $tipo . '" name="' . $name . '" ' . $req . ' autocomplete="off" placeholder="' . htmlspecialchars($ph) . '"'
        . ' style="width:100%;padding:10px 14px;border:1.5px solid ' . $borde . ';background:' . $fondo . ';border-radius:8px;font-size:13px;outline:none;color:var(--gc-ink);' . $extra . '"'
        . ' onfocus="this.style.borderColor=\'' . $colorFoco . '\'" onblur="this.style.borderColor=\'' . $borde . '\'"/>';
};

$panelAyuda = 'background:var(--gc-bg);border:1px solid var(--gc-line);border-radius:10px;padding:16px 18px;font-size:13px;color:var(--gc-label);line-height:1.65;';
?>
<style>
    .cert-fila { display:grid; grid-template-columns:minmax(0,1fr) minmax(320px,460px); gap:24px; align-items:start; }
    @media (max-width: 1100px) { .cert-fila { grid-template-columns:1fr; } }
    .cert-permisos { width:100%; border-collapse:collapse; font-size:12px; margin:8px 0 4px; }
    .cert-permisos th { text-align:left; padding:6px 10px; background:var(--gc-surface-2); color:var(--gc-label-2); font-weight:700; }
    .cert-permisos td { padding:7px 10px; border-top:1px solid var(--gc-line); vertical-align:top; }
</style>

<div class="gc-content gc-w-content">

    <?php if (!empty($_SESSION['certificado_error'])): ?>
    <div style="background:var(--gc-neg-soft);color:var(--gc-neg);border:1px solid var(--gc-neg-border);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:13px;font-weight:600;">
        ⚠ <?= htmlspecialchars($_SESSION['certificado_error']) ?>
    </div>
    <?php unset($_SESSION['certificado_error']); endif; ?>

    <?php if ($cert): ?>
    <div style="background:var(--gc-pos-soft);border:1px solid var(--gc-pos-border);border-radius:12px;padding:14px 20px;margin-bottom:20px;font-size:13px;color:var(--gc-pos);">
        <strong>✓ Credenciales configuradas</strong> —
        Ambiente: <?= ucfirst($cert['ambiente'] ?? 'beta') ?> |
        Usuario y clave SOL: <?= $estadoSol ? '✓ Ingresados' : '⚠ Pendiente' ?> |
        API SUNAT: <?= $estadoApi ? '✓ Configurada' : '⚠ Pendiente' ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="/empresas/<?= $empresa['id'] ?>/certificado">

        <!-- BLOQUE 1: Credenciales SOL -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-brand-soft);">
                <div style="font-weight:700;font-size:15px;color:var(--gc-brand);">🔑 Credenciales SOL</div>
                <div style="font-size:12px;color:var(--gc-label);margin-top:3px;">
                    <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= $empresa['ruc'] ?>
                </div>
            </div>
            <div class="cert-fila" style="padding:20px 24px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                    <div>
                        <?= $etiqueta('Usuario SOL *', $tiene['sol_usuario']) ?>
                        <?= $campo('sol_usuario', 'text', $tiene['sol_usuario'], 'Ej: GESTICONT', true, 'var(--gc-brand)', 'font-family:monospace;') ?>
                        <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Solo el usuario, sin el RUC</div>
                    </div>
                    <div>
                        <?= $etiqueta('Clave SOL *', $tiene['sol_clave']) ?>
                        <?= $campo('sol_clave', 'password', $tiene['sol_clave'], '••••••••', true) ?>
                    </div>
                    <div>
                        <label style="<?= $lblStyle ?>">Ambiente SUNAT *</label>
                        <?php $amb = $cert['ambiente'] ?? 'beta'; ?>
                        <select name="ambiente"
                            style="width:100%;padding:10px 14px;border:1.5px solid var(--gc-line);border-radius:8px;font-size:14px;outline:none;font-family:inherit;background:var(--gc-surface);color:var(--gc-ink);">
                            <option value="beta" <?= $amb === 'beta' ? 'selected' : '' ?>>Beta (pruebas)</option>
                            <option value="produccion" <?= $amb === 'produccion' ? 'selected' : '' ?>>Producción</option>
                        </select>
                    </div>
                    <div>
                        <?= $etiqueta('Contraseña certificado .pfx', $tiene['cert_password']) ?>
                        <?= $campo('cert_password', 'password', $tiene['cert_password'], 'Solo si tiene .pfx') ?>
                    </div>
                </div>

                <div style="<?= $panelAyuda ?>">
                    <strong style="color:var(--gc-ink);">Crea un usuario SOL nuevo para GestiCont</strong><br>
                    No uses el usuario principal: crea un <strong>usuario secundario</strong> solo para esta conexión
                    (en el portal SOL con el usuario principal, opción de usuarios secundarios del menú <strong>Empresas</strong>)
                    y actívale estas carpetas:
                    <table class="cert-permisos">
                        <thead><tr><th>Carpeta a activar</th><th>Para qué</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Comprobantes de pago</strong></td><td>Consulta de CPE emitidos y recibidos, y validez</td></tr>
                            <tr><td><strong>Sistema Integrado de Registros Electrónicos</strong></td><td>SIRE: RVIE (ventas) y RCE (compras)</td></tr>
                            <tr><td><strong>Credenciales de API SUNAT</strong></td><td>Sin esta, el token OAuth no te da acceso a los endpoints</td></tr>
                        </tbody>
                    </table>
                    <span style="font-size:12px;color:var(--gc-muted);">Luego escribe aquí ese usuario (sin el RUC) y su clave.</span>
                </div>
            </div>
        </div>

        <!-- BLOQUE 2: Credenciales API SUNAT (para SIRE) -->
        <div style="background:var(--gc-surface);border-radius:12px;border:1px solid var(--gc-line);overflow:hidden;margin-bottom:16px;">
            <div style="padding:16px 24px;border-bottom:1px solid var(--gc-line);background:var(--gc-pos-soft-2);">
                <div style="font-weight:700;font-size:15px;color:var(--gc-pos);">🔐 Credenciales API SUNAT</div>
                <div style="font-size:12px;color:var(--gc-label);margin-top:3px;">
                    Para sincronización automática con el SIRE — se generan en el portal SOL
                </div>
            </div>
            <div class="cert-fila" style="padding:20px 24px;">
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <div>
                        <?= $etiqueta('Client ID (ID de la aplicación)', $tiene['api_client_id']) ?>
                        <?= $campo('api_client_id', 'text', $tiene['api_client_id'], 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', false, 'var(--gc-pos)', 'font-family:monospace;') ?>
                        <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Formato UUID con guiones</div>
                    </div>
                    <div>
                        <?= $etiqueta('Client Secret (Clave de la aplicación)', $tiene['api_client_secret']) ?>
                        <?= $campo('api_client_secret', 'password', $tiene['api_client_secret'], '••••••••••••••••••••', false, 'var(--gc-pos)') ?>
                        <div style="font-size:11px;color:var(--gc-muted);margin-top:4px;">Clave generada por SUNAT</div>
                    </div>

                    <?php if ($estadoApi): ?>
                    <div style="background:var(--gc-pos-soft);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--gc-pos);">
                        ✓ API SUNAT configurada — sincronización con SIRE activa
                    </div>
                    <?php else: ?>
                    <div style="background:var(--gc-warn-soft);border-radius:8px;padding:10px 14px;font-size:13px;color:var(--gc-warn);">
                        ⚠ Sin credenciales API completas — la sincronización automática no funcionará hasta configurarlas
                    </div>
                    <?php endif; ?>
                </div>

                <div style="<?= $panelAyuda ?>">
                    <strong style="color:var(--gc-ink);">¿Cómo obtener estas credenciales?</strong><br>
                    1. Entra al portal SOL con el RUC <strong><?= $empresa['ruc'] ?></strong> y el <strong>usuario principal</strong><br>
                    2. Ve a <strong>Empresas → Credenciales de API SUNAT → Gestión Credenciales</strong><br>
                    3. Edita la aplicación y marca <strong>MIGE RCE y RVIE - SIRE</strong> + Alcance <strong>Desktop</strong><br>
                    4. Copia el <strong>ID</strong> y la <strong>CLAVE</strong> que aparecen y pégalos aquí
                </div>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <div style="font-size:12px;color:var(--gc-muted);">
                Los campos marcados <strong style="color:var(--gc-pos);">✓ Ingresado</strong> se conservan si los dejas vacíos; solo se reemplaza lo que escribas.
            </div>
            <div style="display:flex;gap:12px;">
                <a href="/empresas/<?= $empresa['id'] ?>"
                   style="background:var(--gc-surface-2);color:var(--gc-label);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;text-decoration:none;">
                    Cancelar
                </a>
                <button type="submit"
                    style="background:var(--gc-brand);color:var(--gc-on-brand);padding:11px 28px;border-radius:8px;font-size:14px;font-weight:600;border:none;cursor:pointer;font-family:inherit;">
                    Guardar y encriptar
                </button>
            </div>
        </div>
    </form>
</div>
