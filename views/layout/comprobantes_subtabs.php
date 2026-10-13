<?php
/**
 * Pestañas de Comprobantes (rediseño): Ventas · Compras · Clasificar · Cobros y pagos · Sincronizar SIRE,
 * con contadores. Clasificar y Sincronizar se ven en ámbar cuando hay pendientes.
 * Requiere $empresa y $subtabActiva: 'ventas'|'compras'|'imputacion'|'cobranzas'|'sync'.
 */
require_once ROOT . '/core/Periodo.php';
$__pdo = Model::db(); $__id = (int)$empresa['id']; $__per = Periodo::resolver($__id);
$__n = function (string $sql, array $p) use ($__pdo) { $s = $__pdo->prepare($sql); $s->execute($p); return (int)$s->fetchColumn(); };
$__cV = $__n("SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id = ? AND periodo = ? AND estado_sunat = '1'", [$__id, $__per]);
$__cC = $__n("SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND periodo = ? AND estado_sunat = '1'", [$__id, $__per]);
$__pend = $__n("SELECT (SELECT COUNT(*) FROM registro_ventas WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1' AND periodo >= ?) + (SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1' AND periodo >= ?)", [$__id, Periodo::minimo(), $__id, Periodo::minimo()]);
$__sinBajar = 0;
foreach (Periodo::opciones(12, 1) as $__p) {
    if (!$__n("SELECT (SELECT COUNT(*) FROM registro_ventas WHERE empresa_id = ? AND periodo = ?) + (SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND periodo = ?)", [$__id, $__p, $__id, $__p])) $__sinBajar++;
}
$__tabs = [
    ['ventas',     'Ventas',            $__cV,       false],
    ['compras',    'Compras',           $__cC,       false],
    ['imputacion', 'Clasificar',        $__pend,     $__pend > 0],
    ['cobranzas',  'Cobros y pagos',    null,        false],
    ['sync',       'Sincronizar SIRE',  $__sinBajar, $__sinBajar > 0],
];
?>
<nav class="gc-tabs no-print" aria-label="Comprobantes">
    <?php foreach ($__tabs as [$__ruta, $__lbl, $__cnt, $__warn]): ?>
    <a class="gc-tab <?= $__ruta === ($subtabActiva ?? '') ? 'is-active' : '' ?>" href="/empresas/<?= $__id ?>/<?= $__ruta ?>">
        <?= $__lbl ?><?php if ($__cnt !== null && ($__cnt > 0 || in_array($__ruta, ['ventas', 'compras'], true))): ?> <span class="cnt <?= $__warn ? 'warn' : '' ?>"><?= number_format($__cnt) ?></span><?php endif; ?>
    </a>
    <?php endforeach; ?>
</nav>
