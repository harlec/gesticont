<?php
/**
 * GestiCont — base para los reportes exportables a PDF (Apertura, Balance
 * de Comprobación, Balance General, Estado de Resultados, Cambios en el
 * Patrimonio, Flujo de Efectivo). Usa FPDF (setasign/fpdf, ya instalado
 * vía Composer) en vez del "Imprimir" del navegador — control total del
 * diseño, sin depender de cómo cada navegador rasteriza la página.
 *
 * FPDF trabaja en Latin-1 (ISO-8859-1), no UTF-8 — cualquier texto con
 * tildes o "ñ" tiene que pasar por t() antes de dibujarse, o sale
 * corrupto. Es la causa más común de "se ve raro" con esta librería.
 */
class PdfReport extends FPDF
{
    private array $empresa;
    private string $titulo;
    private string $subtitulo;

    private const BRAND      = [30, 58, 138];
    private const ON_BRAND   = [255, 255, 255];
    private const INK        = [30, 41, 59];
    private const LABEL      = [71, 85, 105];
    private const MUTED      = [148, 163, 184];
    private const LINE       = [226, 232, 240];
    private const SURFACE_2  = [241, 245, 249];
    public const POS         = [22, 101, 52];
    public const POS_SOFT    = [220, 252, 231];
    public const NEG         = [153, 27, 27];
    public const NEG_SOFT    = [254, 242, 242];
    public const WARN        = [146, 64, 14];
    public const WARN_SOFT   = [254, 243, 199];
    public const BRAND_SOFT  = [239, 246, 255];

    public function __construct(array $empresa, string $titulo, string $subtitulo, string $orientacion = 'P')
    {
        parent::__construct($orientacion, 'mm', 'A4');
        $this->empresa   = $empresa;
        $this->titulo    = $titulo;
        $this->subtitulo = $subtitulo;
        $this->SetAutoPageBreak(true, 18);
        $this->SetMargins(14, 32, 14);
        $this->AddPage();
    }

    public function t(string $texto): string
    {
        return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
    }

    public function anchoUtil(): float
    {
        return $this->GetPageWidth() - $this->GetX() * 2;
    }

    public function Header(): void
    {
        $this->SetFillColor(...self::BRAND);
        $this->Rect(0, 0, $this->GetPageWidth(), 20, 'F');
        $this->SetTextColor(...self::ON_BRAND);
        $this->SetFont('Helvetica', 'B', 13);
        $this->SetXY(14, 5);
        $this->Cell(0, 6, $this->t('GestiCont'), 0, 1);
        $this->SetFont('Helvetica', '', 9);
        $this->SetXY(14, 12);
        $this->Cell(0, 5, $this->t($this->empresa['razon_social'] . ' · RUC: ' . $this->empresa['ruc']), 0, 1);

        $this->SetXY(14, 24);
        $this->SetTextColor(...self::INK);
        $this->SetFont('Times', 'B', 15);
        $this->Cell(0, 7, $this->t($this->titulo), 0, 1);
        $this->SetX(14);
        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(...self::LABEL);
        $this->Cell(0, 5, $this->t($this->subtitulo), 0, 1);
        $this->SetTextColor(...self::INK);
        $this->SetY(38);
    }

    public function Footer(): void
    {
        $this->SetY(-14);
        $this->SetDrawColor(...self::LINE);
        $this->Line(14, $this->GetY(), $this->GetPageWidth() - 14, $this->GetY());
        $this->SetY(-11);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::MUTED);
        $this->Cell(0, 8, $this->t('Generado por GestiCont · ' . date('d/m/Y H:i') . ' · Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }

    /** Barra de encabezado de sección — "ACTIVO", "PASIVO Y PATRIMONIO", etc. */
    public function seccion(string $texto, array $bg, array $fg): void
    {
        $this->SetFillColor(...$bg);
        $this->SetTextColor(...$fg);
        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell($this->anchoUtil(), 8, '  ' . $this->t($texto), 0, 1, 'L', true);
        $this->SetTextColor(...self::INK);
        $this->Ln(1);
    }

    /** Fila de dos columnas: etiqueta a la izquierda, monto alineado a la derecha. */
    public function fila(string $label, string $valor, bool $bold = false, bool $raya = false, ?string $sub = null): void
    {
        $w = $this->anchoUtil();
        if ($raya) {
            $this->SetDrawColor(...self::LINE);
            $this->Line($this->GetX(), $this->GetY(), $this->GetX() + $w, $this->GetY());
        }
        $this->SetFont('Helvetica', $bold ? 'B' : '', 9.5);
        $this->SetTextColor(...($bold ? self::INK : self::LABEL));
        $this->Cell($w * 0.62, 6.5, '  ' . $this->t($label), 0, 0, 'L');
        $this->SetFont('Helvetica', $bold ? 'B' : '', 9.5);
        $this->SetTextColor(...self::INK);
        $this->Cell($w * 0.38 - 2, 6.5, $this->t($valor) . '  ', 0, 1, 'R');
        if ($sub) {
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(...self::MUTED);
            $this->Cell($w, 4.5, '  ' . $this->t($sub), 0, 1, 'L');
        }
    }

    public function alerta(string $texto, string $tipo = 'warn'): void
    {
        $bg = $tipo === 'pos' ? self::POS_SOFT : ($tipo === 'neg' ? self::NEG_SOFT : self::WARN_SOFT);
        $fg = $tipo === 'pos' ? self::POS : ($tipo === 'neg' ? self::NEG : self::WARN);
        $this->SetFillColor(...$bg);
        $this->SetTextColor(...$fg);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->MultiCell($this->anchoUtil(), 6, '  ' . $this->t($texto), 0, 'L', true);
        $this->SetTextColor(...self::INK);
        $this->Ln(3);
    }

    /** Tabla genérica: cabecera con fondo gris + filas con montos alineados a la derecha. */
    public function tabla(array $columnas, array $anchos, array $filas, array $alineacion = []): void
    {
        $w = $this->anchoUtil();
        $this->SetFillColor(...self::SURFACE_2);
        $this->SetTextColor(...self::LABEL);
        $this->SetFont('Helvetica', 'B', 8);
        foreach ($columnas as $i => $col) {
            $this->Cell($w * $anchos[$i], 7, $this->t($col), 0, 0, $alineacion[$i] ?? 'L', true);
        }
        $this->Ln();
        $this->SetTextColor(...self::INK);
        $this->SetFont('Helvetica', '', 8.5);
        foreach ($filas as $fila) {
            foreach ($fila as $i => $val) {
                $this->Cell($w * $anchos[$i], 6, $this->t((string)$val), 0, 0, $alineacion[$i] ?? 'L');
            }
            $this->Ln();
        }
    }

    public function espacio(float $h = 4): void { $this->Ln($h); }

    public function salir(string $nombreArchivo): void
    {
        $this->AliasNbPages();
        $this->Output('I', $nombreArchivo . '.pdf');
        exit;
    }
}
