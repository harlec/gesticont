<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';

class PlanillaController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = $_GET['periodo'] ?? date('Ym', strtotime('-1 month'));

        $stmt = $pdo->prepare("
            SELECT * FROM planillas WHERE empresa_id = ? AND periodo = ? ORDER BY trabajador
        ");
        $stmt->execute([$empresaId, $periodo]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totales = ['bruto' => 0, 'essalud' => 0, 'retencion' => 0, 'neto' => 0];
        foreach ($registros as $r) {
            $bruto = (float)$r['sueldo'] + (float)$r['gratificacion'] + (float)$r['asignacion_familiar'];
            $totales['bruto']     += $bruto;
            $totales['essalud']   += (float)$r['essalud'];
            $totales['retencion'] += (float)$r['retencion_pension'];
            $totales['neto']      += $bruto - (float)$r['retencion_pension'];
        }

        $pageTitle = 'Planillas — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/planillas/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function store(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo   = $_POST['periodo'] ?? date('Ym');
        $trabajador = trim($_POST['trabajador'] ?? '');
        $sueldo     = (float)($_POST['sueldo'] ?? 0);
        $gratif     = (float)($_POST['gratificacion'] ?? 0);
        $asigFam    = (float)($_POST['asignacion_familiar'] ?? 0);
        $essalud    = (float)($_POST['essalud'] ?? 0);
        $regimen    = $_POST['regimen_pension'] ?? 'onp';
        $retencion  = (float)($_POST['retencion_pension'] ?? 0);

        if (empty($trabajador) || !preg_match('/^\d{6}$/', $periodo) || !in_array($regimen, ['onp', 'afp', 'ninguno'], true)) {
            $_SESSION['planilla_error'] = 'Datos incompletos o período inválido.';
            header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
        }

        Model::db()->prepare("
            INSERT INTO planillas
                (empresa_id, periodo, trabajador, sueldo, gratificacion, asignacion_familiar,
                 essalud, regimen_pension, retencion_pension, origen, usuario_id)
            VALUES (?,?,?,?,?,?,?,?,?, 'manual', ?)
        ")->execute([
            $empresaId, $periodo, $trabajador, $sueldo, $gratif, $asigFam,
            $essalud, $regimen, $retencion, Auth::id(),
        ]);

        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}&ok=1"); exit;
    }

    public function eliminar(int $empresaId, int $id): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_POST['periodo'] ?? date('Ym');
        Model::db()->prepare("DELETE FROM planillas WHERE id = ? AND empresa_id = ?")
            ->execute([$id, $empresaId]);

        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
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
