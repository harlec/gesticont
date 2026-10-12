<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/RegimenService.php';

/** Régimen tributario de la empresa, con historial y vigencia (informativo; ver RegimenService). */
class RegimenController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo       = Model::db();
        $periodo   = Periodo::resolver($empresaId);
        $vigente   = RegimenService::vigente($pdo, $empresa, $periodo);
        $libros    = RegimenService::libros($vigente['regimen'], $vigente['tramo']);
        $historial = RegimenService::historial($pdo, $empresaId);

        $pageTitle = 'Régimen tributario — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/regimen/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function cambiar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/regimen"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $r = RegimenService::cambiar(
            Model::db(), $empresaId,
            (string)($_POST['regimen'] ?? ''), (string)($_POST['mes'] ?? ''),
            $_POST['tramo'] ?? null, (string)($_POST['nota'] ?? ''), Auth::id()
        );
        if ($r['ok']) $_SESSION['regimen_ok'] = 'Régimen actualizado. Rige desde el mes indicado.';
        else          $_SESSION['regimen_error'] = $r['error'];
        header("Location: /empresas/{$empresaId}/regimen"); exit;
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
