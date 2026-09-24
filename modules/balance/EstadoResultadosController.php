<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/EstadoResultadosService.php';
require_once ROOT . '/services/PdfReport.php';

class EstadoResultadosController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pageTitle = 'Estado de Resultados - ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/resultados.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function pdf(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $fmt = fn($v) => 'S/ ' . number_format((float)$v, 2);
        $pdf = new PdfReport($empresa, 'Estado de Resultados', 'Acumulado hasta ' . Periodo::etiqueta($periodo));

        if (!$resultado) {
            $pdf->alerta('Sin datos para este período. Genera los asientos del Libro Diario primero.', 'warn');
            $pdf->salir('estado-resultados');
        }

        if ($resultado['costo_ventas'] == 0) {
            $pdf->alerta('El Costo de Ventas aparece en S/ 0.00 - todavía no existe el módulo de Inventario Final/Kardex, así que la Utilidad Bruta por ahora coincide con los Ingresos.', 'warn');
            $pdf->espacio(2);
        }

        $linea = fn($label, $val, $bold = false, $resta = false, $sub = null) =>
            $pdf->fila(($resta ? '(-) ' : '') . $label, $fmt($val), $bold, $bold, $sub);

        $linea('Ingresos por Ventas', $resultado['ingresos_ventas'], false, false, 'Cuentas 701-704');
        $linea('Costo de Ventas', $resultado['costo_ventas'], false, true, 'Cuenta 691');
        $linea('Utilidad Bruta', $resultado['utilidad_bruta'], true);
        $pdf->espacio(1);

        $linea('Costo de Producción', $resultado['costo_produccion'], false, true, 'Cuenta 92');
        $linea('Gastos de Administración', $resultado['gastos_admin'], false, true, 'Cuenta 94');
        $linea('Gastos de Venta', $resultado['gastos_venta'], false, true, 'Cuenta 95');
        $linea('Gastos Financieros', $resultado['gastos_financieros'], false, true, 'Cuenta 96');
        $linea('Utilidad Operativa', $resultado['utilidad_operativa'], true);
        $pdf->espacio(1);

        $linea('Ingresos Financieros', $resultado['ingresos_financieros'], false, false, 'Cuenta 779');
        $linea('Utilidad Antes de Impuestos', $resultado['utilidad_antes_impuestos'], true);
        $pdf->espacio(1);

        $linea('Impuesto a la Renta (' . number_format($resultado['tasa_ir'], 2) . '%)', $resultado['impuesto_renta'], false, true);
        $linea('Utilidad del Ejercicio', $resultado['utilidad_ejercicio'], true);
        $pdf->espacio(1);

        $linea('Reserva Legal (' . number_format($resultado['pct_reserva_legal'], 2) . '%)', $resultado['reserva_legal'], false, true);
        $linea('Utilidad Antes de Repartición', $resultado['utilidad_antes_reparticion'], true);

        $pdf->salir('estado-resultados');
    }

    /** @return array{0: ?array, 1: string, 2: ?array} */
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
                $resultado  = EstadoResultadosService::generar($empresaId, (int)$periodoContable['id'], $fechaCorte, $parametros);
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
