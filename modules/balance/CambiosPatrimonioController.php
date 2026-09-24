<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CambiosPatrimonioService.php';
require_once ROOT . '/services/PdfReport.php';

class CambiosPatrimonioController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pageTitle = 'Estado de Cambios en el Patrimonio — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/balance/views/cambios_patrimonio.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function pdf(int $empresaId): void
    {
        Auth::require();
        [$empresa, $periodo, $resultado] = $this->_datos($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $fmt = fn($v) => number_format((float)$v, 2);
        $pdf = new PdfReport($empresa, 'Estado de Cambios en el Patrimonio', 'Acumulado hasta ' . Periodo::etiqueta($periodo), 'L');

        if (!$resultado) {
            $pdf->alerta('Sin datos para este período. Genera los asientos del Libro Diario primero.', 'warn');
            $pdf->salir('cambios-patrimonio');
        }

        $cols   = ['', 'Capital', 'Reservas Legales', 'Resultados Acumulados', 'Total'];
        $anchos = [0.28, 0.18, 0.18, 0.18, 0.18];
        $align  = ['L', 'R', 'R', 'R', 'R'];
        $filas = [
            ['Saldo Inicial', $fmt($resultado['capital_inicial']), $fmt($resultado['reservas_inicial']), $fmt($resultado['resultados_inicial']), $fmt($resultado['total_inicial'])],
            ['(+) Utilidad Neta del Ejercicio', '-', '-', $fmt($resultado['utilidad_neta']), $fmt($resultado['utilidad_neta'])],
            ['(Traslado a) Reserva Legal', '-', '+' . $fmt($resultado['reserva_legal']), '-' . $fmt($resultado['reserva_legal']), '0.00'],
            ['Saldo Final', $fmt($resultado['capital_final']), $fmt($resultado['reservas_final']), $fmt($resultado['resultados_final']), $fmt($resultado['total_final'])],
        ];
        $pdf->tabla($cols, $anchos, $filas, $align);
        $pdf->espacio(3);
        $pdf->alerta('No hay módulo de aportes/retiros de capital todavía - el Capital se mantiene fijo entre el saldo inicial y el final.', 'warn');

        $pdf->salir('cambios-patrimonio');
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
                $resultado  = CambiosPatrimonioService::generar($empresaId, (int)$periodoContable['id'], $fechaCorte, $parametros);
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
