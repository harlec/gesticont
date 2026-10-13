<?php
/** Cierre del año (rediseño). Variables: $empresa, $anio, $periodoContable, $yaCerrado, $pasos, $faltan, $puedeCerrar. */
$id = (int)$empresa['id'];
$contActiva = 'cierre'; require ROOT . '/views/layout/contabilidad_subtabs.php';
$contTitulo = 'Cierre del año ' . $anio;
$contDesc = 'Antes de cerrar se revisa que todo esté listo. Al cerrar, el año queda bloqueado y se calculan los saldos de apertura del siguiente.';
require ROOT . '/views/layout/contabilidad_head.php';
$anioTermino = date('Y-m-d') >= "{$anio}-12-31";
?>
<?php foreach (['cierre_error' => 'neg', 'cierre_ok' => 'ok'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach; ?>

<?php if (!$periodoContable): ?>
<div class="gc-card"><div class="gc-empty"><b>Aún no hay datos contables de <?= $anio ?></b>Genera al menos un asiento en el <a href="/empresas/<?= $id ?>/diario">Libro Diario</a> primero.</div></div>

<?php elseif ($yaCerrado): ?>
<section class="gc-card pad gc-stack" style="gap:14px;border-color:var(--gc-pos-border);background:var(--gc-pos-soft-2);">
    <div style="font-size:22px;font-weight:700;color:var(--gc-pos);">El año <?= $anio ?> está cerrado</div>
    <div>Sus asientos no se pueden modificar y los saldos de apertura de <?= $anio + 1 ?> ya están calculados.</div>
    <div class="gc-row"><button type="button" class="gc-btn" onclick="openModal('cierre-reabrir')">Reabrir el año</button></div>
</section>
<div class="gc-modal-back" id="cierre-reabrir" onmousedown="if(event.target===this)closeModal('cierre-reabrir')">
  <form class="gc-modal sm" method="POST" action="/empresas/<?= $id ?>/cierre/reabrir">
    <h2>¿Reabrir <?= $anio ?>?</h2>
    <p style="margin:12px 0;">Se borra el asiento de cierre y el año vuelve a poder modificarse. Los saldos de apertura de <?= $anio + 1 ?> se vuelven a calcular cuando lo cierres otra vez.</p>
    <input type="hidden" name="anio" value="<?= $anio ?>">
    <div class="gc-row" style="justify-content:flex-end;margin-top:18px;"><button type="button" class="gc-btn" onclick="closeModal('cierre-reabrir')">Cancelar</button><button type="submit" class="gc-btn gc-btn-danger">Sí, reabrir <?= $anio ?></button></div>
  </form>
</div>

<?php else: ?>
<?php if (!$anioTermino): ?><div class="gc-alert info"><span class="msg">El año <?= $anio ?> aún no termina. Puedes revisar los pasos desde ahora; cierra cuando hayas registrado todos tus ajustes.</span></div><?php endif; ?>
<section class="gc-card clip">
    <?php foreach ($pasos as [$clave, $titulo, $ok, $detalle, $url, $boton]): ?>
    <div class="gc-row between" style="padding:18px 24px;border-top:1px solid var(--gc-surface-2);gap:16px;">
        <div class="gc-row" style="gap:16px;flex-wrap:nowrap;">
            <span class="gc-chip <?= $ok ? 'ok' : 'warn' ?>" style="min-width:92px;justify-content:center;"><?= $ok ? 'Listo' : 'Falta' ?></span>
            <div><div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($titulo) ?></div><div class="gc-sub"><?= htmlspecialchars($detalle) ?></div></div>
        </div>
        <?php if (!$ok): ?><a class="gc-btn" href="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($boton) ?></a><?php endif; ?>
    </div>
    <?php endforeach; ?>
</section>
<div class="gc-row" style="justify-content:flex-end;">
    <?php if ($puedeCerrar): ?>
    <button type="button" class="gc-btn gc-btn-danger gc-btn-lg" onclick="openModal('cierre-modal')">Cerrar el año <?= $anio ?></button>
    <?php else: ?>
    <button type="button" class="gc-btn gc-btn-lg" disabled>Faltan <?= $faltan ?> paso<?= $faltan === 1 ? '' : 's' ?> para poder cerrar</button>
    <?php endif; ?>
</div>

<div class="gc-modal-back" id="cierre-modal" onmousedown="if(event.target===this)closeModal('cierre-modal')">
  <form class="gc-modal sm" method="POST" action="/empresas/<?= $id ?>/cierre/cerrar">
    <h2>¿Cerrar el año <?= $anio ?>?</h2>
    <ul style="margin:14px 0 14px 20px;padding:0;line-height:1.6;">
        <li>Se cierran las cuentas de resultados y la utilidad pasa a la cuenta 5911 (o la pérdida a la 5912).</li>
        <li>Si el impuesto a la renta no está registrado, se provisiona en 4017.</li>
        <li>Se calculan los saldos de apertura de <?= $anio + 1 ?>.</li>
        <li>Los asientos de <?= $anio ?> quedan bloqueados.</li>
    </ul>
    <input type="hidden" name="anio" value="<?= $anio ?>">
    <label class="gc-check" style="align-items:flex-start;"><input type="checkbox" id="cierre-ok" onchange="document.getElementById('cierre-si').disabled=!this.checked"> <span>Entiendo que después no podré modificar los asientos de <?= $anio ?>.</span></label>
    <div class="gc-row" style="justify-content:flex-end;margin-top:20px;"><button type="button" class="gc-btn" onclick="closeModal('cierre-modal')">Cancelar</button><button type="submit" id="cierre-si" class="gc-btn gc-btn-danger" disabled>Sí, cerrar <?= $anio ?></button></div>
  </form>
</div>
<?php endif; ?>
