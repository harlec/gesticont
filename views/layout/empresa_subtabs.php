<?php
/** Pestañas de "Datos de la empresa". Requiere $empresa y $empTab ('editar'|'certificado'|'regimen'|'depurar'). */
$__t = [['editar', 'Datos'], ['certificado', 'Certificado y clave SOL'], ['regimen', 'Régimen tributario']];
if (Auth::isSuperadmin()) $__t[] = ['depurar', 'Depurar'];
?>
<nav class="gc-tabs no-print" aria-label="Datos de la empresa">
    <?php foreach ($__t as [$__k, $__l]): ?><a class="gc-tab <?= $__k === ($empTab ?? '') ? 'is-active' : '' ?>" href="/empresas/<?= (int)$empresa['id'] ?>/<?= $__k ?>"><?= $__l ?></a><?php endforeach; ?>
</nav>
