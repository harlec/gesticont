<?php
/**
 * Inicio de la empresa (rediseño). Variables: $empresa, $dash (DashboardEmpresaService), $cert, $periodoActivo,
 * $obligaciones (VencimientosService), $regimenVigente.
 */
$id = (int)$empresa['id'];
$S = fn($v) => 'S/ ' . number_format((float)$v, 2);
$fecha = fn(?string $f) => $f ? date('d/m/Y', strtotime($f)) : null;
$mesTxt = Periodo::etiqueta($periodoActivo, true);
$grupo = VencimientosService::grupoDeRuc($empresa['ruc']);
$sinPresentar = array_values(array_filter($obligaciones, fn($o) => $o['estado'] !== 'presentado'));
$vencida = array_values(array_filter($sinPresentar, fn($o) => $o['dias'] !== null && $o['dias'] < 0));
$prox = VencimientosService::proxima($obligaciones);
$esRus = $regimenVigente['regimen'] === 'rus';
if ($vencida)            { $titulo = 'Tienes una declaración vencida'; }
elseif ($prox)           { $titulo = 'Próximo: ' . $fecha($prox['fecha']); }
elseif ($obligaciones)   { $titulo = 'Todo presentado'; }
else                     { $titulo = $esRus ? 'Sin declaraciones mensuales' : 'Sin obligaciones'; }
$tonoBloque = $prox ? VencimientosService::tono($prox['dias'], $prox['estado']) : 'ok';
$colorEstado = ['presentado' => 'var(--gc-pos)', 'listo' => 'var(--gc-pos)', 'bloqueado' => 'var(--gc-neg)', 'datos' => 'var(--gc-warn)', 'espera' => 'var(--gc-label-2)'];
$certTxt = !$cert ? ['neg', 'Falta subir'] : (($cert['cert_hasta'] ?? null) && $cert['cert_hasta'] < date('Y-m-d') ? ['neg', 'Vencido'] : ['ok', 'Activo' . (($cert['cert_hasta'] ?? null) ? ' hasta ' . $fecha($cert['cert_hasta']) : '')]);
$hayDatosMes = ($dash['mes_n_ventas'] + $dash['mes_n_compras']) > 0;
?>
<?php foreach (['obl_ok' => 'ok', 'obl_error' => 'neg'] as $k => $t): if (empty($_SESSION[$k])) continue; ?>
<div class="gc-alert <?= $t ?>" role="status"><span class="msg"><?= htmlspecialchars($_SESSION[$k]) ?></span></div>
<?php unset($_SESSION[$k]); endforeach; ?>

<div class="gc-row" style="align-items:stretch;gap:24px;">

  <!-- Vencimientos SUNAT -->
  <section class="gc-card clip" style="flex:2 1 620px;min-width:0;">
    <div class="head" style="padding:24px 28px;">
      <div class="gc-stack" style="gap:4px;">
        <span class="gc-sub" style="font-weight:600;">Vencimientos SUNAT</span>
        <span style="font-size:26px;font-weight:700;line-height:1.2;"><?= htmlspecialchars($titulo) ?></span>
        <span class="gc-sub"><?= $sinPresentar ? count($sinPresentar) . ' obligación' . (count($sinPresentar) === 1 ? '' : 'es') . ' pendiente' . (count($sinPresentar) === 1 ? '' : 's') : ($obligaciones ? 'No tienes nada pendiente en ' . $mesTxt : '') ?></span>
      </div>
      <?php if ($prox): $d = $prox['dias']; ?>
      <div class="gc-chip <?= $tonoBloque ?>" style="flex-direction:column;gap:0;border-radius:14px;padding:12px 22px;text-align:center;">
        <span style="font-size:42px;font-weight:700;line-height:1;"><?= $d === null ? '—' : abs($d) ?></span>
        <span style="font-size:15px;font-weight:600;"><?= $d === null ? 'sin fecha' : ($d < 0 ? 'días de atraso' : ($d === 0 ? 'vence hoy' : ($d === 1 ? 'día' : 'días'))) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($esRus): ?>
      <div class="body"><span class="gc-sub">El Nuevo RUS no presenta el IGV-Renta mensual: paga una cuota mensual. Si cambió de régimen, actualízalo en <a href="/empresas/<?= $id ?>/regimen">Régimen tributario</a>.</span></div>
    <?php elseif (!$obligaciones): ?>
      <div class="body"><span class="gc-sub">No hay obligaciones para este mes.</span></div>
    <?php else: foreach ($obligaciones as $o):
        $tono = VencimientosService::tono($o['dias'], $o['estado']); ?>
      <div style="padding:18px 28px;border-top:1px solid var(--gc-surface-2);display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        <div class="gc-grow" style="min-width:260px;">
          <div style="font-size:18px;font-weight:700;"><?= htmlspecialchars($o['nombre']) ?></div>
          <div class="gc-sub" style="font-size:15px;">Período <?= htmlspecialchars($o['periodo_txt']) ?> · <?= $o['fecha'] ? 'vence el ' . $fecha($o['fecha']) : 'fecha por cargar' ?></div>
          <div style="font-size:16px;margin-top:4px;color:<?= $colorEstado[$o['estado']] ?? 'var(--gc-ink)' ?>;font-weight:600;"><span style="font-size:12px;">●</span> <?= htmlspecialchars($o['texto']) ?></div>
        </div>
        <span class="gc-chip <?= $tono ?>"><?= htmlspecialchars(VencimientosService::textoDias($o['dias'], $o['estado'])) ?></span>
        <div style="min-width:170px;text-align:right;">
          <?php if ($o['estado'] === 'presentado'): ?>
            <form method="POST" action="/empresas/<?= $id ?>/obligaciones/marcar" style="margin:0;">
              <input type="hidden" name="tipo" value="<?= $o['tipo'] ?>"><input type="hidden" name="periodo" value="<?= $o['periodo'] ?>"><input type="hidden" name="presentado" value="0">
              <button class="gc-btn" type="submit" title="Quitar la marca de presentado">Deshacer</button>
            </form>
          <?php elseif ($o['accion'] && $o['accion']['tipo'] === 'ir'): ?>
            <a href="<?= htmlspecialchars($o['accion']['url']) ?>" class="gc-btn <?= $o['estado'] === 'bloqueado' ? 'gc-btn-soft' : '' ?>"><?= htmlspecialchars($o['accion']['etiqueta']) ?></a>
          <?php elseif ($o['accion'] && $o['accion']['tipo'] === 'marcar'): ?>
            <form method="POST" action="/empresas/<?= $id ?>/obligaciones/marcar" style="margin:0;" onsubmit="return confirm('¿Ya presentaste esta declaración ante SUNAT? GestiCont solo lo registra como presentado.');">
              <input type="hidden" name="tipo" value="<?= $o['tipo'] ?>"><input type="hidden" name="periodo" value="<?= $o['periodo'] ?>"><input type="hidden" name="presentado" value="1">
              <button class="gc-btn gc-btn-primary" type="submit"><?= htmlspecialchars($o['accion']['etiqueta']) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
    <div style="padding:14px 28px;border-top:1px solid var(--gc-line);background:var(--gc-bg);font-size:15px;color:var(--gc-label-2);">
      Fechas según el cronograma SUNAT para RUC terminado en <?= (int)substr($empresa['ruc'], -1) ?>. Los buenos contribuyentes y los principales contribuyentes tienen otras fechas.
    </div>
  </section>

  <!-- Columna derecha -->
  <div class="gc-stack" style="flex:1 1 320px;min-width:0;gap:24px;">
    <section class="gc-card pad">
      <div class="gc-sub" style="font-weight:600;">Por clasificar</div>
      <?php if ($dash['pendientes'] > 0): ?>
        <div style="font-size:44px;font-weight:700;line-height:1.1;color:var(--gc-warn);margin:6px 0;"><?= number_format($dash['pendientes']) ?></div>
        <div class="gc-sub" style="margin-bottom:16px;"><?= number_format($dash['pendientes_ventas']) ?> ventas y <?= number_format($dash['pendientes_compras']) ?> compras esperan una cuenta. Sin clasificar no se pueden presentar los registros.</div>
        <a href="/empresas/<?= $id ?>/imputacion" class="gc-btn gc-btn-primary gc-btn-lg" style="width:100%;">Clasificar ahora</a>
      <?php else: ?>
        <div style="font-size:26px;font-weight:700;color:var(--gc-pos);margin:6px 0;">Todo clasificado</div>
        <div class="gc-sub">No hay comprobantes esperando una cuenta.</div>
      <?php endif; ?>
    </section>
    <section class="gc-card pad">
      <div class="gc-sub" style="font-weight:600;margin-bottom:10px;">Estado de la empresa</div>
      <div class="gc-stack" style="gap:12px;font-size:16px;">
        <div class="gc-row between"><span>Balance</span>
          <?php if ($dash['balance_cuadra'] === null): ?><span class="gc-chip neutral">Sin asientos</span>
          <?php elseif ($dash['balance_cuadra']): ?><span class="gc-chip ok">Cuadra</span>
          <?php else: ?><a href="/empresas/<?= $id ?>/balance" class="gc-chip neg">No cuadra</a><?php endif; ?></div>
        <div class="gc-row between"><span>Certificado digital</span><a href="/empresas/<?= $id ?>/certificado" class="gc-chip <?= $certTxt[0] ?>"><?= htmlspecialchars($certTxt[1]) ?></a></div>
        <div class="gc-row between"><span>Última sincronización</span><span style="font-weight:600;"><?= $dash['ultima_sync'] ? date('d/m/Y', strtotime($dash['ultima_sync'])) : 'Nunca' ?></span></div>
        <div class="gc-row between"><span>Régimen</span><a href="/empresas/<?= $id ?>/regimen" style="font-weight:600;"><?= htmlspecialchars(RegimenService::CORTOS[$regimenVigente['regimen']] ?? $regimenVigente['regimen']) ?></a></div>
      </div>
    </section>
  </div>
</div>

<!-- Resumen del mes -->
<section class="gc-stack" style="gap:14px;">
  <h2 class="gc-h2">Resumen de <?= $mesTxt ?></h2>
  <?php if (!$hayDatosMes): ?>
    <div class="gc-card"><div class="gc-empty"><b>Aún no hay comprobantes de <?= $mesTxt ?></b>Descarga las ventas y compras de ese mes desde SUNAT.<br><br><a href="/empresas/<?= $id ?>/sync" class="gc-btn gc-btn-primary">Sincronizar con SUNAT</a></div></div>
  <?php else: $favor = $dash['mes_igv'] < 0; ?>
  <div class="gc-kpis">
    <a class="gc-kpi" href="/empresas/<?= $id ?>/ventas"><span class="k">Ventas</span><span class="v"><?= $S($dash['mes_ventas_total']) ?></span><span class="s"><?= number_format($dash['mes_n_ventas']) ?> comprobantes</span></a>
    <a class="gc-kpi" href="/empresas/<?= $id ?>/compras"><span class="k">Compras</span><span class="v"><?= $S($dash['mes_compras_total']) ?></span><span class="s"><?= number_format($dash['mes_n_compras']) ?> comprobantes</span></a>
    <div class="gc-kpi <?= $favor ? 'ok' : '' ?>"><span class="k"><?= $favor ? 'Saldo a favor de IGV' : 'IGV por pagar' ?></span><span class="v"><?= $S(abs($dash['mes_igv'])) ?></span><span class="s"><?= $favor ? 'Las compras superan a las ventas: queda a favor para el mes siguiente' : 'IGV de ventas menos IGV de compras' ?></span></div>
    <div class="gc-kpi <?= $dash['mes_resultado'] < 0 ? 'neg' : '' ?>"><span class="k">Resultado estimado</span><span class="v"><?= $dash['mes_resultado'] < 0 ? '(−) ' : '' ?><?= $S(abs($dash['mes_resultado'])) ?></span><span class="s">Ventas menos compras, sin IGV</span></div>
  </div>
  <?php endif; ?>
</section>

<!-- Año + saldos pendientes -->
<div class="gc-row" style="align-items:stretch;gap:24px;">
  <section class="gc-card pad" style="flex:2 1 620px;min-width:0;">
    <div class="gc-row between" style="margin-bottom:12px;">
      <h2 class="gc-h2">Año <?= $dash['anio'] ?></h2>
      <span class="gc-row" style="gap:16px;font-size:15px;color:var(--gc-label-2);">
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--gc-brand);vertical-align:-1px;"></span> Ventas</span>
        <span><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:var(--gc-series-compras);vertical-align:-1px;"></span> Compras</span>
      </span>
    </div>
    <?php
      $max = max(1, ...array_map(fn($m) => max($m['ventas'], $m['compras']), $dash['meses']));
      $mesesCorto = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'];
      $W = 720; $H = 230; $top = 14; $bot = 34; $gw = $W / 12; $alto = $H - $top - $bot;
      $urlPer = fn(string $p) => "/empresas/{$id}/periodo?periodo={$p}&volver=" . rawurlencode("/empresas/{$id}");
    ?>
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="Ventas y compras por mes de <?= $dash['anio'] ?>" style="display:block;">
      <?php foreach ($dash['meses'] as $i => $m):
        $per = $dash['anio'] . $m['mes']; $x = $i * $gw;
        $permitido = Periodo::permitido($per) && $per <= Periodo::ultimoCerrado();
        $hv = round($m['ventas'] / $max * $alto, 1); $hc = round($m['compras'] / $max * $alto, 1);
        $actual = $per === $periodoActivo;
      ?>
      <?php if ($permitido): ?><a href="<?= htmlspecialchars($urlPer($per)) ?>"><?php endif; ?>
        <rect x="<?= $x + 2 ?>" y="0" width="<?= $gw - 4 ?>" height="<?= $H ?>" rx="8" fill="<?= $actual ? 'var(--gc-brand-soft)' : 'transparent' ?>"><title><?= $mesesCorto[$i] . ' ' . $dash['anio'] . ': ventas ' . $S($m['ventas']) . ', compras ' . $S($m['compras']) ?></title></rect>
        <rect x="<?= $x + $gw/2 - 18 ?>" y="<?= $top + $alto - $hv ?>" width="16" height="<?= $hv ?>" rx="3" fill="var(--gc-brand)"/>
        <rect x="<?= $x + $gw/2 + 2 ?>" y="<?= $top + $alto - $hc ?>" width="16" height="<?= $hc ?>" rx="3" fill="var(--gc-series-compras)"/>
        <text x="<?= $x + $gw/2 ?>" y="<?= $H - 10 ?>" text-anchor="middle" font-size="14" fill="<?= $permitido ? 'var(--gc-ink)' : 'var(--gc-muted)' ?>" font-weight="<?= $actual ? '700' : '400' ?>"><?= $mesesCorto[$i] ?></text>
      <?php if ($permitido): ?></a><?php endif; ?>
      <?php endforeach; ?>
      <line x1="0" x2="<?= $W ?>" y1="<?= $top + $alto ?>" y2="<?= $top + $alto ?>" stroke="var(--gc-line)"/>
    </svg>
    <div style="overflow-x:auto;margin-top:10px;">
      <table class="gc-table" style="width:100%;font-size:14px;">
        <thead><tr><th>Mes</th><th class="r">Ventas (base)</th><th class="r">IGV ventas</th><th class="r">Total ventas</th><th class="r">Compras (base)</th><th class="r">IGV compras</th><th class="r">Total compras</th></tr></thead>
        <tbody>
        <?php foreach ($dash['meses'] as $i => $m): ?>
          <tr><td><?= $mesesCorto[$i] ?></td><td class="r num"><?= number_format($m['ventas_base'], 2) ?></td><td class="r num"><?= number_format($m['ventas_igv'], 2) ?></td><td class="r num"><b><?= number_format($m['ventas'], 2) ?></b></td><td class="r num"><?= number_format($m['compras_base'], 2) ?></td><td class="r num"><?= number_format($m['compras_igv'], 2) ?></td><td class="r num"><b><?= number_format($m['compras'], 2) ?></b></td></tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Total <?= $dash['anio'] ?></td>
          <td class="r num"><?= number_format(array_sum(array_column($dash['meses'], 'ventas_base')), 2) ?></td><td class="r num"><?= number_format(array_sum(array_column($dash['meses'], 'ventas_igv')), 2) ?></td><td class="r num"><?= number_format($dash['ventas_anio'], 2) ?></td>
          <td class="r num"><?= number_format(array_sum(array_column($dash['meses'], 'compras_base')), 2) ?></td><td class="r num"><?= number_format(array_sum(array_column($dash['meses'], 'compras_igv')), 2) ?></td><td class="r num"><?= number_format($dash['compras_anio'], 2) ?></td></tr></tfoot>
      </table>
    </div>
    <div class="gc-sub" style="font-size:15px;margin-top:6px;">Toca un mes para trabajar en él. Total del año: ventas <?= $S($dash['ventas_anio']) ?> · compras <?= $S($dash['compras_anio']) ?>.</div>
  </section>

  <section class="gc-card pad" style="flex:1 1 320px;min-width:0;">
    <h2 class="gc-h2" style="margin-bottom:14px;">Saldos pendientes</h2>
    <div class="gc-stack" style="gap:14px;">
      <a href="/empresas/<?= $id ?>/cuentas-por-cobrar" class="gc-kpi" style="gap:2px;"><span class="k">Por cobrar</span><span class="v"><?= $S($dash['cxc_monto']) ?></span><span class="s"><?= number_format($dash['cxc_cant']) ?> comprobante<?= $dash['cxc_cant'] === 1 ? '' : 's' ?> con saldo</span></a>
      <a href="/empresas/<?= $id ?>/cuentas-por-pagar" class="gc-kpi" style="gap:2px;"><span class="k">Por pagar</span><span class="v"><?= $S($dash['cxp_monto']) ?></span><span class="s"><?= number_format($dash['cxp_cant']) ?> comprobante<?= $dash['cxp_cant'] === 1 ? '' : 's' ?> con saldo</span></a>
    </div>
  </section>
</div>
