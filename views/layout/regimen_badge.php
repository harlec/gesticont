<?php
/**
 * Franja informativa con el régimen vigente del período y si el libro de esta pantalla es
 * obligatorio. NO oculta ni bloquea nada. Requiere $empresa y $periodo; $libroClave es una de:
 * 'diario' | 'mayor' | 'balances' | 'caja'.
 */
require_once ROOT . '/services/RegimenService.php';
if (!empty($empresa) && !empty($periodo) && !empty($libroClave)) {
    $rg  = RegimenService::vigente(Model::db(), $empresa, $periodo);
    $rgl = RegimenService::libros($rg['regimen'], $rg['tramo']);
    $nombresLibro = ['diario' => 'El Libro Diario', 'mayor' => 'El Libro Mayor', 'balances' => 'El Libro de Inventarios y Balances', 'caja' => 'El Libro Caja y Bancos'];
    $req = $rgl['requiere'][$libroClave] ?? null;
    ?>
<div class="no-print" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:12px;color:var(--gc-muted);margin-bottom:12px;">
    <a href="/empresas/<?= $empresa['id'] ?>/regimen" style="background:var(--gc-surface-2);color:var(--gc-label);padding:3px 10px;border-radius:99px;font-weight:700;text-decoration:none;">
        <?= RegimenService::CORTOS[$rg['regimen']] ?? $rg['regimen'] ?> · <?= htmlspecialchars($rgl['etiqueta']) ?>
    </a>
    <?php if ($req === false): ?>
    <span style="color:var(--gc-warn);font-weight:600;"><?= $nombresLibro[$libroClave] ?> no es obligatorio en este régimen; se muestra de forma informativa.</span>
    <?php elseif ($req === null && in_array($rg['regimen'], ['mype', 'general'], true)): ?>
    <span>Define el tramo de ingresos para saber si este libro es obligatorio.</span>
    <?php endif; ?>
</div>
<?php } ?>
