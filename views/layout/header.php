<?php
/**
 * Encabezado fijo: título de la pantalla · período de trabajo (único selector de mes de todo
 * el sistema) · tamaño de letra · fecha de hoy. Requiere las variables que deja sidebar.php.
 */
$meses = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'setiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
$dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$hoyTxt = $dias[(int)date('w')] . ' ' . (int)date('j') . ' de ' . $meses[(int)date('n')];

if ($dentroDeEmpresa) {
    $titulo = isset($pageTitle) ? (strpos($pageTitle, ' — ') !== false ? explode(' — ', $pageTitle)[0] : $pageTitle) : 'Inicio';
    if ($titulo === 'Resumen' || $titulo === $empresa['razon_social']) $titulo = 'Inicio';
    $perAct = Periodo::resolver((int)$empresa['id']);
    $perPrev = Periodo::anterior($perAct); $perNext = Periodo::siguiente($perAct);
    $hayPrev = Periodo::permitido($perPrev); $hayNext = $perNext <= Periodo::ultimoCerrado();
    // al cambiar de período se vuelve a la misma pantalla, sin su propio ?periodo=
    parse_str((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $q); unset($q['periodo']);
    $volver = $navPath . ($q ? '?' . http_build_query($q) : '');
    $urlPer = fn(string $p) => $base . '/periodo?periodo=' . $p . '&volver=' . rawurlencode($volver);
} else {
    $titulo = $pageTitle ?? 'Mis empresas';
}
$zoomActual = in_array($_COOKIE['gc_letra'] ?? '', ['1', '1.12', '1.25'], true) ? $_COOKIE['gc_letra'] : '1';
?>
<header class="gc-header">
    <h1><?= htmlspecialchars($titulo) ?></h1>
    <?php if ($dentroDeEmpresa): ?>
    <div class="grupo no-print">
        <span>Período</span>
        <a class="gc-periodo-btn <?= $hayPrev ? '' : 'is-off' ?>" href="<?= $hayPrev ? htmlspecialchars($urlPer($perPrev)) : '#' ?>" aria-label="Mes anterior">‹</a>
        <div class="gc-periodo-lbl"><?= Periodo::etiqueta($perAct, true) ?></div>
        <a class="gc-periodo-btn <?= $hayNext ? '' : 'is-off' ?>" href="<?= $hayNext ? htmlspecialchars($urlPer($perNext)) : '#' ?>" aria-label="Mes siguiente">›</a>
    </div>
    <?php endif; ?>
    <div class="grupo no-print">
        <span>Letra</span>
        <div class="gc-letra">
            <button type="button" data-z="1"    onclick="gcLetra(1)"    class="<?= $zoomActual === '1' ? 'is-active' : '' ?>"    aria-label="Letra normal"       style="font-size:14px">A</button>
            <button type="button" data-z="1.12" onclick="gcLetra(1.12)" class="<?= $zoomActual === '1.12' ? 'is-active' : '' ?>" aria-label="Letra grande"        style="font-size:17px">A</button>
            <button type="button" data-z="1.25" onclick="gcLetra(1.25)" class="<?= $zoomActual === '1.25' ? 'is-active' : '' ?>" aria-label="Letra muy grande"    style="font-size:21px">A</button>
        </div>
    </div>
    <div class="gc-hoy">Hoy: <?= $hoyTxt ?></div>
</header>
