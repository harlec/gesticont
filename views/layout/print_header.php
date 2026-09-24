<?php
/**
 * Encabezado + botón de exportación para pantallas con reporte en PDF
 * (Balance de Comprobación, Balance General, Estado de Resultados,
 * Cambios en el Patrimonio, Flujo de Efectivo, Apertura). El PDF se
 * genera en el servidor con FPDF (services/PdfReport.php) — reemplaza al
 * "Imprimir" nativo del navegador, que dependía de cómo cada navegador
 * rasteriza la página para verse bien.
 *
 * Variables esperadas antes de este require:
 *   $tituloReporte     string — "Balance General", "Apertura (Inventario Inicial)", etc.
 *   $subtituloReporte  string — "Al cierre de Ago 2025", "Año 2025", etc.
 *   $pdfUrl            string — ruta al endpoint /pdf de este reporte
 */
?>
<div class="no-print" style="display:flex;justify-content:flex-end;margin-bottom:12px;">
    <a href="<?= htmlspecialchars($pdfUrl) ?>" target="_blank" rel="noopener"
       style="background:var(--gc-surface-2);color:var(--gc-label);border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;text-decoration:none;">
        📄 Descargar PDF
    </a>
</div>
