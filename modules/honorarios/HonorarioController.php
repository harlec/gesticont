<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/HonorarioService.php';

/** Honorarios por recibo (4ta categoría): provisión 632 / 424 / 4017 y pago desde Caja. */
class HonorarioController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = Periodo::resolver($empresaId);
        $anio    = substr($periodo, 0, 4);

        $stmt = $pdo->prepare("SELECT * FROM honorarios WHERE empresa_id = ? AND periodo = ? ORDER BY fecha, id");
        $stmt->execute([$empresaId, $periodo]);
        $recibos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tot = ['monto' => 0.0, 'retencion' => 0.0, 'neto' => 0.0, 'pendiente' => 0.0];
        foreach ($recibos as $r) {
            $neto = HonorarioService::neto($r);
            $tot['monto'] += (float)$r['monto']; $tot['retencion'] += (float)$r['retencion']; $tot['neto'] += $neto;
            if (!$r['pagado']) $tot['pendiente'] += $neto;
        }

        // Resumen del año, como la hoja HONORARIO del Excel: mes / monto / retención / total.
        $stmtA = $pdo->prepare("
            SELECT periodo, SUM(monto) AS monto, SUM(retencion) AS retencion, COUNT(*) AS n
            FROM honorarios WHERE empresa_id = ? AND periodo LIKE ? GROUP BY periodo ORDER BY periodo
        ");
        $stmtA->execute([$empresaId, $anio . '%']);
        $resumenAnual = array_column($stmtA->fetchAll(PDO::FETCH_ASSOC), null, 'periodo');

        $pageTitle = 'Honorarios — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/honorarios/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function crear(int $empresaId): void
    {
        $this->_post($empresaId);
        $r = HonorarioService::crear($empresaId, $_POST, !empty($_POST['pagado']), Auth::id());
        if (!$r['ok']) $_SESSION['honorario_error'] = $r['error'];
        else {
            $_SESSION['honorario_ok'] = 'Recibo registrado.' . (!empty($r['error']) ? ' (' . $r['error'] . ')' : '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha'] ?? '')) $_POST['periodo'] = substr($_POST['fecha'], 0, 4) . substr($_POST['fecha'], 5, 2);
        }
        $this->_volver($empresaId);
    }

    public function pagar(int $empresaId, int $id): void
    {
        $this->_post($empresaId);
        $r = HonorarioService::pagar($empresaId, $id, !empty($_POST['fecha']) ? $_POST['fecha'] : null, Auth::id());
        $r['ok'] ? $_SESSION['honorario_ok'] = 'Marcado como pagado (movimiento de Caja creado).' : $_SESSION['honorario_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    public function pendiente(int $empresaId, int $id): void
    {
        $this->_post($empresaId);
        $r = HonorarioService::pasarAPendiente($empresaId, $id);
        $r['ok'] ? $_SESSION['honorario_ok'] = 'Pasado a pendiente: se quitó su pago de Caja.' : $_SESSION['honorario_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    public function eliminar(int $empresaId, int $id): void
    {
        $this->_post($empresaId);
        $r = HonorarioService::eliminar($empresaId, $id);
        $r['ok'] ? $_SESSION['honorario_ok'] = 'Recibo eliminado.' : $_SESSION['honorario_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    private function _post(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/honorarios"); exit; }
        if (!$this->_getEmpresa($empresaId)) { http_response_code(403); die('Sin acceso'); }
    }

    private function _volver(int $empresaId): void
    {
        $p = preg_match('/^\d{6}$/', $_POST['periodo'] ?? '') ? $_POST['periodo'] : Periodo::resolver($empresaId);
        header("Location: /empresas/{$empresaId}/honorarios?periodo={$p}"); exit;
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
