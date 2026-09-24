<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/FlujoEfectivoService.php';
require_once ROOT . '/services/PdfReport.php';

class FlujoEfectivoController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pageTitle = 'Estado de Flujo de Efectivo — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/flujo_efectivo.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function pdf(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $fmt = fn($v) => 'S/ ' . number_format((float)$v, 2);
        $pdf = new PdfReport($empresa, 'Estado de Flujo de Efectivo', 'Acumulado hasta ' . Periodo::etiqueta($periodo));

        if (!$resultado) {
            $pdf->alerta('Sin datos para este período. Genera los asientos del Libro Diario primero.', 'warn');
            $pdf->salir('flujo-efectivo');
        }

        if (abs($resultado['descuadre']) > 0.01) {
            $pdf->alerta('El Saldo Final calculado (' . $fmt($resultado['saldo_final_calculado']) . ') no coincide con el saldo real de Caja y Bancos en el Balance ('
                . $fmt($resultado['saldo_real_caja']) . '). Regenera los asientos del Libro Diario o revisa movimientos de Caja sin cuenta asignada.', 'neg');
        } else {
            $pdf->alerta('Saldo Final de Efectivo = saldo real de Caja y Bancos = ' . $fmt($resultado['saldo_final_calculado']), 'pos');
        }
        $pdf->espacio(2);

        $pdf->seccion('ACTIVIDAD DE OPERACIÓN', PdfReport::BRAND_SOFT, [30, 58, 138]);
        $pdf->fila('Cobranza a Clientes', $fmt($resultado['cobranza_clientes']));
        $pdf->fila('(-) Pago a Proveedores', $fmt($resultado['pago_proveedores']));
        $pdf->fila('(-) Pago a Trabajadores', $fmt($resultado['pago_trabajadores']));
        $pdf->fila('Otros cobros/pagos operativos (tributos, ESSALUD, ONP, AFP, etc.)', $fmt($resultado['otros_operacion']));
        $pdf->fila('Flujo de Operación', $fmt($resultado['flujo_operacion']), true, true);
        $pdf->espacio(2);

        $pdf->seccion('ACTIVIDAD DE INVERSIÓN', PdfReport::BRAND_SOFT, [30, 58, 138]);
        $pdf->fila('(-) Compra de Inmueble, Maquinaria y Equipo', $fmt($resultado['compra_activo_fijo']));
        $pdf->fila('Flujo de Inversión', $fmt($resultado['flujo_inversion']), true, true);
        $pdf->espacio(2);

        $pdf->seccion('ACTIVIDAD DE FINANCIAMIENTO', PdfReport::BRAND_SOFT, [30, 58, 138]);
        $pdf->fila('Préstamos Recibidos', $fmt($resultado['prestamos_recibidos']));
        $pdf->fila('(-) Amortización de Obligaciones', $fmt($resultado['amortizacion']));
        $pdf->fila('Flujo de Financiamiento', $fmt($resultado['flujo_financiamiento']), true, true);
        $pdf->espacio(2);

        $pdf->fila('Aumento/Disminución Neta de Efectivo', $fmt($resultado['aumento_neto']), true, true);
        $pdf->fila('Saldo Inicial de Efectivo', $fmt($resultado['saldo_inicial']));
        $pdf->fila('Saldo Final de Efectivo', $fmt($resultado['saldo_final_calculado']), true, true);

        $pdf->salir('flujo-efectivo');
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
            $fechaCorte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
            $resultado  = FlujoEfectivoService::generar($empresaId, (int)$periodoContable['id'], $fechaCorte);
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
