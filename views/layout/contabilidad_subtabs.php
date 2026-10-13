<?php
/**
 * Barra de subsecciones de Contabilidad (rediseño): cuatro grupos rotulados — Libros, Estados financieros, Saldos, Cierre.
 * Cada botón muestra un chip "Revisar" si esa pantalla tiene un problema. Requiere $empresa y $contActiva
 * (diario|mayor|asientos|balance|balance-general|resultados|cambios-patrimonio|flujo-efectivo|cuentas-por-cobrar|cuentas-por-pagar|cierre).
 */
require_once ROOT . '/services/ContabilidadService.php';
$__id = (int)$empresa['id'];
$__rev = ContabilidadService::revisiones($__id, Periodo::resolver($__id));
$__grupos = [
    'Libros'              => [['diario', 'Libro Diario'], ['mayor', 'Libro Mayor'], ['asientos', 'Asientos manuales']],
    'Estados financieros' => [['balance', 'Balance de comprobación'], ['balance-general', 'Balance general'], ['resultados', 'Estado de resultados'], ['cambios-patrimonio', 'Cambios en el patrimonio'], ['flujo-efectivo', 'Flujo de efectivo']],
    'Saldos'              => [['cuentas-por-cobrar', 'Cuentas por cobrar'], ['cuentas-por-pagar', 'Cuentas por pagar']],
    'Cierre'              => [['cierre', 'Cierre del año']],
];
?>
<nav class="gc-card gc-subnav no-print" aria-label="Contabilidad">
    <?php foreach ($__grupos as $__rotulo => $__items): ?>
    <div class="grupo">
        <span class="rotulo"><?= $__rotulo ?></span>
        <?php foreach ($__items as [$__ruta, $__lbl]):
            $__r = $__rev[$__ruta] ?? null;
            $__activo = ($contActiva ?? '') === $__ruta; ?>
        <a href="/empresas/<?= $__id ?>/<?= $__ruta ?>" class="<?= $__activo ? 'is-active' : '' ?>">
            <?= $__lbl ?>
            <?php if ($__r === true): ?><span class="gc-chip warn" style="padding:0 8px;">Revisar</span>
            <?php elseif (is_int($__r) && $__r > 0): ?><span class="gc-chip warn" style="padding:0 8px;"><?= $__r ?> pendiente<?= $__r === 1 ? '' : 's' ?></span>
            <?php elseif ($__r === 'cerrado'): ?><span class="gc-chip ok" style="padding:0 8px;">Cerrado</span><?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</nav>
