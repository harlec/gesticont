<?php
/**
 * Lector del reporte R01 del PDT Planilla Electrónica (PLAME):
 * "Trabajadores - Datos de Ingresos, Tributos y Aportes". Lo exporta SUNAT
 * como XML de Excel 2003 (SpreadsheetML), un archivo por período, p. ej.
 * 20114100731_202602_r01.xml.
 *
 * Columnas por trabajador (posición fija):
 *   1 tipo doc · 2 número · 3 ap. paterno · 4 ap. materno · 5 nombres · 6 situación
 *   7 ingresos devengado · 8 ingresos pagado · 9 descuentos
 *   10 tributos y aportes del TRABAJADOR (ONP/AFP/5ta) · 11 neto a pagar
 *   12 tributos y aportes del EMPLEADOR (EsSalud)
 *
 * El reporte no separa sueldo / gratificación / asignación familiar ni dice
 * si el trabajador está en ONP o AFP; eso lo resuelve quien importa.
 */
class PlameR01Parser
{
    private const NS = 'urn:schemas-microsoft-com:office:spreadsheet';

    /**
     * @return array{ruc:string,empleador:string,periodo:string,trabajadores:array<int,array>}
     * @throws RuntimeException si el archivo no es un R01 legible
     */
    public static function parsear(string $contenido): array
    {
        // El XML no declara codificación: si no es UTF-8 válido viene en
        // Windows-1252 (ñ, tildes) y se convierte para no perder el nombre.
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $ok = $dom->loadXML($contenido, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) throw new RuntimeException('el archivo no es un XML válido');

        $filas = [];
        foreach ($dom->getElementsByTagNameNS(self::NS, 'Row') as $row) {
            $celdas = [];
            $col = 0;
            foreach ($row->getElementsByTagNameNS(self::NS, 'Cell') as $cell) {
                if ($cell->parentNode !== $row) continue;
                $idx = $cell->getAttributeNS(self::NS, 'Index');
                $col = $idx !== '' ? (int)$idx : $col + 1;
                $data = $cell->getElementsByTagNameNS(self::NS, 'Data')->item(0);
                if ($data !== null) $celdas[$col] = trim($data->textContent);
                $col += (int)$cell->getAttributeNS(self::NS, 'MergeAcross');
            }
            if ($celdas) $filas[] = $celdas;
        }

        $ruc = $empleador = $periodo = '';
        foreach ($filas as $f) {
            $t = $f[1] ?? '';
            if ($ruc === '' && preg_match('/^RUC\s*:\s*(\d{11})/i', $t, $m)) $ruc = $m[1];
            if ($empleador === '' && preg_match('/^Empleador\s*:\s*(.+)$/i', $t, $m)) $empleador = trim($m[1]);
            if ($periodo === '' && preg_match('/^Periodo\s*:\s*(\d{2})\/(\d{4})/i', $t, $m)) $periodo = $m[2] . $m[1];
        }
        if ($ruc === '' || $periodo === '') {
            throw new RuntimeException('no parece un reporte R01 de PLAME (falta el RUC o el período)');
        }

        $trabajadores = [];
        foreach ($filas as $f) {
            // Fila de trabajador: tipo de documento de 2 dígitos + devengado numérico.
            if (!isset($f[1], $f[2], $f[7]) || !preg_match('/^\d{2}$/', $f[1]) || !is_numeric($f[7])) continue;

            $nombre = trim(($f[5] ?? '') . ' ' . ($f[3] ?? '') . ' ' . ($f[4] ?? ''));
            $trabajadores[] = [
                'tipo_doc'    => $f[1],
                'documento'   => $f[2],
                'nombre'      => mb_convert_case(preg_replace('/\s+/', ' ', $nombre), MB_CASE_TITLE, 'UTF-8'),
                'situacion'   => $f[6] ?? '',
                'devengado'   => round((float)$f[7], 2),
                'pagado'      => round((float)($f[8] ?? 0), 2),
                'descuentos'  => round((float)($f[9] ?? 0), 2),
                'retencion'   => round((float)($f[10] ?? 0), 2),
                'neto'        => round((float)($f[11] ?? 0), 2),
                'essalud'     => round((float)($f[12] ?? 0), 2),
            ];
        }

        return ['ruc' => $ruc, 'empleador' => $empleador, 'periodo' => $periodo, 'trabajadores' => $trabajadores];
    }

    /**
     * El R01 no dice ONP o AFP, solo el monto retenido. ONP es exactamente
     * el 13 % de la base, así que con una base "redonda" (múltiplo de 10)
     * es ONP; con AFP (aporte + comisión + seguro, ~11-13 %) la base
     * implícita casi nunca es redonda. Es una estimación: se informa y se
     * puede corregir por trabajador en la pantalla de Planillas.
     */
    public static function inferirRegimen(float $retencion): string
    {
        if ($retencion <= 0.0) return 'ninguno';
        $base = $retencion / 0.13;
        return abs($base - round($base / 10) * 10) < 0.5 ? 'onp' : 'afp';
    }
}
