<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/BalanceGeneralService.php';
require_once ROOT . '/services/PdfReport.php';

class BalanceGeneralController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pageTitle = 'Balance general — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/general.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function pdf(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $fmt = fn($v) => 'S/ ' . number_format((float)$v, 2);
        $pdf = new PdfReport($empresa, 'Balance General', 'Al cierre de ' . Periodo::etiqueta($periodo));

        if (!$resultado) {
            $pdf->alerta('Sin datos para este período. Genera los asientos del Libro Diario primero.', 'warn');
            $pdf->salir('balance-general');
        }

        $S = fn($v) => 'S/ ' . number_format(abs((float)$v), 2);
        if (abs($resultado['descuadre']) > 0.01) {
            $pdf->alerta('El balance no cuadra: Activo ' . $S($resultado['total_activo']) . ' contra Pasivo + Patrimonio ' . $S($resultado['total_pasivo_patrimonio']) . ' (diferencia ' . $S($resultado['descuadre']) . '). Revisar el Libro Diario.', 'neg');
        } else {
            $pdf->alerta('Activo = Pasivo + Patrimonio = ' . $S($resultado['total_activo']), 'pos');
        }
        if (!$resultado['ir_registrado'] && $resultado['impuesto_renta_estimado'] > 0) {
            $pdf->alerta('El impuesto a la renta (estimado ' . $S($resultado['impuesto_renta_estimado']) . ') aun no esta registrado: el resultado es ANTES de impuestos.', 'warn');
        }
        $pdf->espacio(2);

        $lista = function (string $titulo, array $items, float $total) use ($pdf, $S) {
            $pdf->fila($titulo, $S($total), true, true);
            foreach ($items as $it) $pdf->fila((($it['resta'] ?? false) ? '(-) ' : '') . $it['nombre'], $S($it['monto']), false, false, 'Cuenta ' . $it['codigo']);
        };
        $d = $resultado['detalle'];
        $pdf->seccion('ACTIVO', PdfReport::BRAND_SOFT, [30, 58, 138]);
        $lista('Activo corriente', $d['activo_corriente'], $resultado['activo_corriente']);
        $lista('Activo no corriente', $d['activo_no_corriente'], $resultado['activo_no_corriente']);
        $pdf->fila('TOTAL ACTIVO', $S($resultado['total_activo']), true, true);
        $pdf->espacio();

        $pdf->seccion('PASIVO Y PATRIMONIO', PdfReport::WARN_SOFT, [146, 64, 14]);
        $lista('Pasivo corriente', $d['pasivo_corriente'], $resultado['pasivo_corriente']);
        $lista('Pasivo no corriente', $d['pasivo_no_corriente'], $resultado['pasivo_no_corriente']);
        $pdf->fila('Total pasivo', $S($resultado['total_pasivo']), true, true);
        $pdf->fila('Patrimonio', $S($resultado['patrimonio']), true, true);
        $pdf->fila('Capital social', $S($resultado['capital_social']));
        $pdf->fila('Resultados acumulados', $S($resultado['resultados_acumulados']));
        $pdf->fila('Resultado del ejercicio (' . ($resultado['ir_registrado'] ? 'despues' : 'antes') . ' de impuestos)', ($resultado['resultado_ejercicio'] < 0 ? '(-) ' : '') . $S($resultado['resultado_ejercicio']));
        $pdf->fila('TOTAL PASIVO Y PATRIMONIO', $S($resultado['total_pasivo_patrimonio']), true, true);

        $pdf->salir('balance-general');
    }

    /** @return array{0: ?array, 1: string, 2: ?array} [$empresa, $periodo, $resultado] */
    private function _datos(int $empresaId): array
    {
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) return [null, '', null];

        $pdo     = Model::db();
        $periodo = Periodo::resolver($empresaId);
        $anio    = (int)substr($periodo, 0, 4);

        $stmtPer = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        $resultado = null;
        if ($periodoContable) {
            $stmtParam = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
            $stmtParam->execute([$empresaId, (int)$periodoContable['id']]);
            $parametros = $stmtParam->fetch(PDO::FETCH_ASSOC);

            if ($parametros) {
                $fechaCorte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
                $resultado  = BalanceGeneralService::generar($empresaId, (int)$periodoContable['id'], $fechaCorte, $parametros);
            }
        }

        return [$empresa, $periodo, $resultado];
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
