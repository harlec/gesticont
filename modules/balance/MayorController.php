<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/LibroMayorService.php';
require_once ROOT . '/services/PdfReport.php';

class MayorController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $periodo = Periodo::resolver($empresaId);
        $cuentas = $this->_cuentas($empresaId, $periodo);

        $pageTitle = 'Libro Mayor — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/mayor.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    private function _cuentas(int $empresaId, string $periodo): array
    {
        $pdo  = Model::db();
        $anio = (int)substr($periodo, 0, 4);
        $stmtPer = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);
        if (!$periodoContable) return [];
        $fechaCorte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        return LibroMayorService::generar($empresaId, (int)$periodoContable['id'], $fechaCorte);
    }

    /** Libro Mayor en PDF — un bloque por cuenta, con saldo acumulado línea a línea. */
    public function pdf(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $periodo = Periodo::resolver($empresaId);
        $cuentas = $this->_cuentas($empresaId, $periodo);
        $pdf = new PdfReport($empresa, 'Libro Mayor', 'Acumulado hasta ' . Periodo::etiqueta($periodo, true) . ' · Expresado en soles');

        if (empty($cuentas)) {
            $pdf->alerta('Sin movimientos acumulados hasta este período.', 'warn');
            $pdf->salir('libro-mayor-' . $periodo);
        }

        $origen = ['compra' => 'Compra', 'venta' => 'Venta', 'planilla' => 'Planilla', 'caja' => 'Caja', 'manual' => 'Manual', 'cierre' => 'Cierre'];
        $fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '';
        $pdf->libroCabecera(
            ['Fecha', 'Glosa', 'Origen', 'Debe', 'Haber', 'Saldo'],
            [0.11, 0.37, 0.10, 0.14, 0.14, 0.14],
            ['L', 'L', 'L', 'R', 'R', 'R']
        );
        foreach ($cuentas as $c) {
            $pdf->reservar(24);
            $pdf->libroFila([$c['codigo'], $c['nombre'], '', '', '', ''], 'titulo');
            $saldo = 0.0;
            if ($c['saldo_apertura']) {
                $saldo = (float)$c['saldo_apertura']['debe'] - (float)$c['saldo_apertura']['haber'];
                $pdf->libroFila(['', 'Saldo de apertura', '', $fmt($c['saldo_apertura']['debe']), $fmt($c['saldo_apertura']['haber']), number_format($saldo, 2)], 'nota');
            }
            foreach ($c['movimientos'] as $m) {
                $saldo += (float)$m['debe'] - (float)$m['haber'];
                $pdf->libroFila([date('d/m/Y', strtotime($m['fecha'])), $m['glosa'], $origen[$m['origen']] ?? $m['origen'],
                    $fmt($m['debe']), $fmt($m['haber']), number_format($saldo, 2)]);
            }
            $pdf->libroFila(['', 'Totales · saldo ' . ($c['saldo'] >= 0 ? 'deudor' : 'acreedor'), '',
                number_format($c['total_debe'], 2), number_format($c['total_haber'], 2), number_format(abs($c['saldo']), 2)], 'total');
            $pdf->espacio(2);
        }
        $pdf->salir('libro-mayor-' . $periodo);
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
