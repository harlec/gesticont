<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/BalanceService.php';
require_once ROOT . '/services/PdfReport.php';

class BalanceController
{
    public function comprobacion(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $balance] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pageTitle = 'Balance de Comprobación — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/comprobacion.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function pdf(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $balance] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '-';
        // Horizontal — 9 columnas no caben legibles en vertical.
        $pdf = new PdfReport($empresa, 'Balance de Comprobación', 'Acumulado hasta ' . Periodo::etiqueta($periodo), 'L');

        if (empty($balance['filas'])) {
            $pdf->alerta('Sin movimientos acumulados hasta este período. Genera los asientos del Libro Diario primero.', 'warn');
            $pdf->salir('balance-comprobacion');
        }

        $descuadre = round($balance['totales']['debe'] - $balance['totales']['haber'], 2);
        if ($descuadre != 0) {
            $pdf->alerta('El Diario no cuadra: Debe menos Haber = S/ ' . number_format($descuadre, 2) . '. Revisar los asientos del período.', 'neg');
        } else {
            $pdf->alerta('El Diario cuadra: suma Debe = suma Haber = S/ ' . number_format($balance['totales']['debe'], 2), 'pos');
        }
        $pdf->espacio(2);

        $cols = ['Cuenta', 'Debe', 'Haber', 'Deudor', 'Acreedor', 'Activo', 'Pasivo', 'Perdidas', 'Ganancias'];
        $anchos = [0.22, 0.0975, 0.0975, 0.0975, 0.0975, 0.0975, 0.0975, 0.0975, 0.0975];
        $align = ['L', 'R', 'R', 'R', 'R', 'R', 'R', 'R', 'R'];
        $filas = [];
        foreach ($balance['filas'] as $f) {
            $filas[] = [
                $f['codigo'] . ' ' . mb_strimwidth($f['nombre'], 0, 26, '...'),
                $fmt($f['debe']), $fmt($f['haber']), $fmt($f['deudor']), $fmt($f['acreedor']),
                $fmt($f['activo']), $fmt($f['pasivo']), $fmt($f['perdidas']), $fmt($f['ganancias']),
            ];
        }
        $pdf->tabla($cols, $anchos, $filas, $align);

        $t  = $balance['totales'];
        $cr = BalanceService::cierreResultado($t);
        $vacio = ['', '', '', ''];
        $pdf->filaTabla(array_merge(['TOTALES'], array_map($fmt, [$t['debe'], $t['haber'], $t['deudor'], $t['acreedor'], $t['activo'], $t['pasivo'], $t['perdidas'], $t['ganancias']])), $anchos, $align, true, true);
        $pdf->filaTabla(array_merge([$cr['tipo'] === 'perdida' ? 'Perdida del ejercicio' : 'Utilidad del ejercicio'], $vacio,
            array_map($fmt, [$cr['ajuste']['activo'], $cr['ajuste']['pasivo'], $cr['ajuste']['perdidas'], $cr['ajuste']['ganancias']])), $anchos, $align, true);
        $pdf->filaTabla(array_merge(['SUMAS IGUALES'], $vacio,
            array_map($fmt, [$cr['iguales']['activo'], $cr['iguales']['pasivo'], $cr['iguales']['perdidas'], $cr['iguales']['ganancias']])), $anchos, $align, true, true);

        $pdf->espacio(3);
        $pdf->alerta(
            $cr['tipo'] === 'nulo'
                ? 'Resultado del periodo: cero (Ganancias = Perdidas).'
                : 'Resultado del ejercicio: ' . ($cr['tipo'] === 'utilidad' ? 'UTILIDAD' : 'PERDIDA') . ' de S/ ' . number_format($cr['monto'], 2)
                    . ' = Ganancias S/ ' . number_format($t['ganancias'], 2) . ' - Perdidas S/ ' . number_format($t['perdidas'], 2)
                    . '. Antes de Impuesto a la Renta y Reserva Legal.',
            $cr['coincide'] ? ($cr['tipo'] === 'perdida' ? 'neg' : 'pos') : 'neg'
        );

        $pdf->salir('balance-comprobacion');
    }

    /** @return array{0: ?array, 1: string, 2: array} */
    private function _datos(int $empresaId): array
    {
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) return [null, '', ['filas' => [], 'totales' => []]];

        $pdo     = Model::db();
        $periodo = Periodo::resolver($empresaId);
        $anio    = (int)substr($periodo, 0, 4);

        $stmtPer = $pdo->prepare("SELECT id, estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        $balance = ['filas' => [], 'totales' => []];
        if ($periodoContable) {
            $fechaCorte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
            $balance = BalanceService::comprobacion($empresaId, (int)$periodoContable['id'], $fechaCorte);
        }

        return [$empresa, $periodo, $balance];
    }

    private function _getEmpresa(int $id): ?array
    {
        $pdo = Model::db();
        if (Auth::isSuperadmin()) {
            $stmt = $pdo->prepare("SELECT * FROM empresas WHERE id = ? AND activo = 1");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT e.* FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ?
            ");
            $stmt->execute([$id, Auth::id()]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
