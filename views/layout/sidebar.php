<?php
/**
 * Menú lateral fijo (rediseño): logo, tarjeta de la empresa actual con el atajo Ctrl+K, menú de
 * solo texto, y abajo "Mis empresas" con su alerta y el usuario. Se decide si el usuario está
 * "dentro" de una empresa mirando si el controlador definió $empresa antes de incluir base.php.
 * Variables que deja para header.php: $dentroDeEmpresa, $base, $navPath, $cartera.
 */
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CarteraService.php';

$dentroDeEmpresa = isset($empresa) && $empresa;
$navPath = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
$user = \Auth::user() ?? ['nombre' => '', 'rol' => ''];
$base = $dentroDeEmpresa ? '/empresas/' . $empresa['id'] : null;

$cartera = [];
try { $cartera = CarteraService::resumen(Model::db(), \Auth::isSuperadmin(), \Auth::id()); } catch (Throwable $e) { $cartera = []; }
$totCartera = CarteraService::totales($cartera);

// Cada ítem: [etiqueta, destino, prefijos de ruta que lo marcan como activo]
if ($dentroDeEmpresa) {
    $items = [
        ['Inicio', $base, [$base]],
        ['Comprobantes', $base . '/ventas', [$base . '/ventas', $base . '/compras', $base . '/sync', $base . '/imputacion', $base . '/cobranzas']],
        ['Caja', $base . '/caja', [$base . '/caja', $base . '/prestamos']],
        ['Contabilidad', $base . '/diario', [$base . '/diario', $base . '/asientos', $base . '/mayor', $base . '/balance', $base . '/balance-general', $base . '/resultados', $base . '/cambios-patrimonio', $base . '/flujo-efectivo', $base . '/cuentas-por-pagar', $base . '/cuentas-por-cobrar', $base . '/cierre']],
        ['Planillas', $base . '/planillas', [$base . '/planillas']],
        ['Honorarios', $base . '/honorarios', [$base . '/honorarios']],
        ['Apertura', $base . '/apertura', [$base . '/apertura']],
        ['Datos de la empresa', $base . '/editar', [$base . '/editar', $base . '/certificado', $base . '/regimen', $base . '/depurar']],
    ];
} else {
    $items = [['Mis empresas', '/empresas', ['/empresas', '/dashboard']]];
    if (\Auth::isContador()) $items[] = ['Usuarios', '/usuarios', ['/usuarios']];
    $items[] = ['Alertas', '/alertas', ['/alertas']];
}
$activo = function (array $prefijos) use ($navPath, $base): bool {
    foreach ($prefijos as $p) {
        if ($p === $base) { if ($navPath === $p) return true; continue; }          // "Inicio" solo en su ruta exacta
        if ($navPath === $p || strpos($navPath, $p . '/') === 0) return true;
    }
    return false;
};
$iniciales = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($user['nombre'] ?: 'U')), 0, 2))));
$rolTxt = ['superadmin' => 'Superadmin', 'contador' => 'Contador', 'operador' => 'Operador', 'cliente' => 'Cliente'][$user['rol'] ?? ''] ?? ($user['rol'] ?? '');
?>
<aside class="gc-sidebar">
    <a href="/empresas" class="gc-logo"><span class="gc-logo-mark">G</span><span class="gc-logo-text">GestiCont</span></a>

    <?php if ($dentroDeEmpresa): ?>
    <button type="button" class="gc-empresa-card" onclick="gcAbrirSelector()">
        <span class="k">Empresa actual</span>
        <span class="n"><?= htmlspecialchars($empresa['razon_social']) ?></span>
        <span class="r mono">RUC <?= htmlspecialchars($empresa['ruc']) ?></span>
        <span class="cambiar"><b>Cambiar empresa</b><span class="gc-kbd">Ctrl + K</span></span>
    </button>
    <?php endif; ?>

    <nav class="gc-menu" aria-label="Menú principal">
        <?php foreach ($items as [$etq, $href, $pref]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="<?= $activo($pref) ? 'is-active' : '' ?>"><?= htmlspecialchars($etq) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="gc-side-foot">
        <?php if ($dentroDeEmpresa): ?>
        <a href="/empresas" class="gc-side-empresas">
            <b>Mis empresas</b>
            <?php if ($totCartera['con_vencidos'] > 0): ?><small><?= $totCartera['con_vencidos'] ?> empresa<?= $totCartera['con_vencidos'] === 1 ? '' : 's' ?> con vencidos</small>
            <?php else: ?><small class="ok">Todo al día</small><?php endif; ?>
        </a>
        <?php endif; ?>
        <div class="gc-user">
            <div class="gc-avatar"><?= htmlspecialchars($iniciales) ?></div>
            <div class="who"><b><?= htmlspecialchars($user['nombre']) ?></b><small><?= htmlspecialchars($rolTxt) ?></small></div>
            <a href="/logout" class="salir">Salir</a>
        </div>
    </div>
</aside>
