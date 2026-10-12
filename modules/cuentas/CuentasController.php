<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CuentasPendientesService.php';
require_once ROOT . '/services/PdfReport.php';

/** Cuentas por pagar / por cobrar: saldo pendiente por proveedor o cliente, con antigüedad. */
class CuentasController
{
    public function porPagar(int $empresaId): void  { $this->_index($empresaId, 'compra'); }
    public function porCobrar(int $empresaId): void { $this->_index($empresaId, 'venta'); }
    public function porPagarPdf(int $empresaId): void  { $this->_pdf($empresaId, 'compra'); }
    public function porCobrarPdf(int $empresaId): void { $this->_pdf($empresaId, 'venta'); }

    private function _datos(int $empresaId, string $origen): array
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }
        $periodo = Periodo::resolver($empresaId);
        return [$empresa, $periodo, CuentasPendientesService::generar($empresaId, $origen, $periodo)];
    }

    private function _index(int $empresaId, string $origen): void
    {
        [$empresa, $periodo, $r] = $this->_datos($empresaId, $origen);
        $esVenta   = $origen === 'venta';
        $titulo    = $esVenta ? 'Cuentas por cobrar' : 'Cuentas por pagar';
        $tercero   = $esVenta ? 'Cliente' : 'Proveedor';
        $rutaBase  = '/empresas/' . $empresa['id'] . ($esVenta ? '/cuentas-por-cobrar' : '/cuentas-por-pagar');

        $pageTitle = $titulo . ' — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/cuentas/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    private function _pdf(int $empresaId, string $origen): void
    {
        [$empresa, $periodo, $r] = $this->_datos($empresaId, $origen);
        $esVenta = $origen === 'venta';
        $pdf = new PdfReport($empresa, $esVenta ? 'Cuentas por cobrar' : 'Cuentas por pagar', 'Saldo pendiente al ' . date('d/m/Y', strtotime($r['corte'])) . ' · Expresado en soles');

        if (empty($r['terceros'])) {
            $pdf->alerta('Sin saldos pendientes a esta fecha.', 'pos');
            $pdf->salir($esVenta ? 'cuentas-por-cobrar' : 'cuentas-por-pagar');
        }

        $fmt = fn($v) => number_format((float)$v, 2);
        $pdf->libroCabecera(['Documento', 'Fecha', 'Días', 'Total', 'Pagado', 'Saldo'], [0.30, 0.14, 0.08, 0.16, 0.16, 0.16], ['L', 'L', 'R', 'R', 'R', 'R']);
        foreach ($r['terceros'] as $t) {
            $pdf->reservar(24);
            $pdf->libroFila([trim($t['doc'] . ' ' . $t['nombre']), '', '', '', '', $fmt($t['saldo'])], 'titulo');
            foreach ($t['docs'] as $d) {
                $pdf->libroFila([$d['tipo'] . ' ' . $d['numero'], date('d/m/Y', strtotime($d['fecha'])), $d['dias'], $fmt($d['total']), $fmt($d['pagado']), $fmt($d['saldo'])]);
            }
        }
        $pdf->libroFila(['TOTAL CON SALDO', '', '', '', '', $fmt($r['total'])], 'total');
        $pdf->espacio(3);
        $partes = [];
        foreach ($r['tramos'] as $nom => $v) $partes[] = $nom . ' días: ' . $fmt($v);
        $pdf->alerta('Antigüedad: ' . implode(' · ', $partes), 'warn');
        if ($r['libro'] !== null) {
            $pdf->alerta(sprintf('Saldo de la cuenta %s en el Mayor: S/ %s. Diferencia sin comprobante (apertura u otros asientos): S/ %s.', $r['cuenta'], $fmt($r['libro']), $fmt($r['sin_detalle'])),
                abs($r['sin_detalle']) < 0.02 ? 'pos' : 'warn');
        }
        $pdf->salir($esVenta ? 'cuentas-por-cobrar' : 'cuentas-por-pagar');
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
                WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ? AND eu.activo = 1
            ");
            $stmt->execute([$id, Auth::id()]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
