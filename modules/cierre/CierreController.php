<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CierreService.php';
require_once ROOT . '/services/BalanceService.php';

class CierreController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo  = Model::db();
        $anio = Periodo::anio($empresaId);

        $stmtPer = $pdo->prepare("SELECT * FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        $pendientes = ['ventas' => 0, 'compras' => 0];
        $pendienteInventariable = 0.0;
        $yaCerrado = false;

        if ($periodoContable) {
            $likeAnio = $anio . '%';
            $stmtPend = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente') AS ventas,
                    (SELECT COUNT(*) FROM registro_compras WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente') AS compras
            ");
            $stmtPend->execute([$empresaId, $likeAnio, $empresaId, $likeAnio]);
            $pendientes = $stmtPend->fetch(PDO::FETCH_ASSOC);

            $balance = BalanceService::comprobacion($empresaId, (int)$periodoContable['id'], "{$anio}-12-31");
            foreach ($balance['filas'] as $f) {
                if ($f['es_inventariable']) $pendienteInventariable += ($f['deudor'] - $f['acreedor']);
            }
            $pendienteInventariable = round($pendienteInventariable, 2);

            $stmtCierre = $pdo->prepare("SELECT * FROM cierres_periodo WHERE empresa_id = ? AND periodo_id = ?");
            $stmtCierre->execute([$empresaId, (int)$periodoContable['id']]);
            $cierrePrevio = $stmtCierre->fetch(PDO::FETCH_ASSOC);
            $yaCerrado = (bool)$cierrePrevio;
        }

        $puedeCerrar = $periodoContable
            && $periodoContable['estado'] === 'abierto'
            && !$yaCerrado
            && (int)$pendientes['ventas'] === 0
            && (int)$pendientes['compras'] === 0
            && abs($pendienteInventariable) < 0.01;

        $pageTitle = 'Cierre de Período — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/cierre/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function cerrar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/cierre"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $anio = (int)($_POST['anio'] ?? date('Y'));
        $resultado = CierreService::cerrarPeriodo($empresaId, $anio, Auth::id());

        if (isset($resultado['error'])) {
            $_SESSION['cierre_error'] = $resultado['error'];
        } else {
            $_SESSION['cierre_ok'] = sprintf(
                'Período %d cerrado. Utilidad neta: S/ %s. Saldos de apertura de %d generados.',
                $anio, number_format($resultado['utilidad_neta'], 2), $resultado['anio_siguiente']
            );
        }

        header("Location: /empresas/{$empresaId}/cierre?anio={$anio}"); exit;
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
