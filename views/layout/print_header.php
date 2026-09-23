<?php
/**
 * Encabezado + botón de impresión para pantallas exportables a PDF
 * (Balance de Comprobación, Balance General, Estado de Resultados,
 * Cambios en el Patrimonio, Flujo de Efectivo, Apertura). Usa el
 * "Imprimir" nativo del navegador — la persona elige "Guardar como PDF"
 * en el diálogo — así no hace falta ninguna librería nueva en el
 * servidor. Ver la regla @media print en public/css/nav.css: oculta la
 * barra de navegación y todo lo marcado .no-print, y muestra esto (que
 * en pantalla está oculto) porque sin el nav no queda ningún indicio de
 * qué empresa o reporte es.
 *
 * Variables esperadas antes de este require:
 *   $tituloReporte     string — "Balance General", "Apertura (Inventario Inicial)", etc.
 *   $subtituloReporte  string — "Al cierre de Ago 2025", "Año 2025", etc.
 */
?>
<div class="print-only" style="margin-bottom:20px;">
    <div style="font-family:Lora,Georgia,serif;font-size:20px;font-weight:700;color:#000;"><?= htmlspecialchars($tituloReporte) ?></div>
    <div style="font-size:13px;color:#333;margin-top:2px;">
        <?= htmlspecialchars($empresa['razon_social']) ?> · RUC: <?= htmlspecialchars($empresa['ruc']) ?>
    </div>
    <div style="font-size:12px;color:#555;margin-top:2px;"><?= htmlspecialchars($subtituloReporte) ?></div>
</div>
<div class="no-print" style="display:flex;justify-content:flex-end;margin-bottom:12px;">
    <button type="button" onclick="window.print()"
            style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;">
        🖨️ Imprimir / Guardar PDF
    </button>
</div>
