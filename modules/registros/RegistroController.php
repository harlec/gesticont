<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';

class RegistroController
{
    public function ventas(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = $_GET['periodo'] ?? date('Ym', strtotime('-1 month'));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        // Total registros
        $stmtT = $pdo->prepare("
            SELECT COUNT(*) FROM registro_ventas
            WHERE empresa_id = ? AND periodo = ?
        ");
        $stmtT->execute([$empresaId, $periodo]);
        $total = (int)$stmtT->fetchColumn();

        // Registros paginados
        $stmt = $pdo->prepare("
            SELECT * FROM registro_ventas
            WHERE empresa_id = ? AND periodo = ?
            ORDER BY fecha_emision ASC, serie ASC, correlativo ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$empresaId, $periodo, $perPage, $offset]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Resumen del período
        $stmtS = $pdo->prepare("
            SELECT
                COUNT(*) as cant,
                SUM(base_imponible) as base,
                SUM(igv) as igv,
                SUM(exonerado) as exonerado,
                SUM(inafecto) as inafecto,
                SUM(total) as total
            FROM registro_ventas
            WHERE empresa_id = ? AND periodo = ?
        ");
        $stmtS->execute([$empresaId, $periodo]);
        $resumen = $stmtS->fetch(PDO::FETCH_ASSOC);

        // Períodos disponibles
        $stmtP = $pdo->prepare("
            SELECT DISTINCT periodo FROM registro_ventas
            WHERE empresa_id = ? ORDER BY periodo DESC
        ");
        $stmtP->execute([$empresaId]);
        $periodos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

        $totalPages = (int)ceil($total / $perPage);
        $tipo       = 'ventas';
        $pageTitle  = 'Ventas — ' . $empresa['razon_social'];

        ob_start();
        require_once ROOT . '/modules/registros/views/lista.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function compras(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = $_GET['periodo'] ?? date('Ym', strtotime('-1 month'));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 50;
        $offset  = ($page - 1) * $perPage;

        $stmtT = $pdo->prepare("
            SELECT COUNT(*) FROM registro_compras
            WHERE empresa_id = ? AND periodo = ?
        ");
        $stmtT->execute([$empresaId, $periodo]);
        $total = (int)$stmtT->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT * FROM registro_compras
            WHERE empresa_id = ? AND periodo = ?
            ORDER BY fecha_emision ASC, serie ASC, correlativo ASC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$empresaId, $periodo, $perPage, $offset]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtS = $pdo->prepare("
            SELECT
                COUNT(*) as cant,
                SUM(base_imponible) as base,
                SUM(igv) as igv,
                SUM(exonerado) as exonerado,
                SUM(inafecto) as inafecto,
                SUM(total) as total
            FROM registro_compras
            WHERE empresa_id = ? AND periodo = ?
        ");
        $stmtS->execute([$empresaId, $periodo]);
        $resumen = $stmtS->fetch(PDO::FETCH_ASSOC);

        $stmtP = $pdo->prepare("
            SELECT DISTINCT periodo FROM registro_compras
            WHERE empresa_id = ? ORDER BY periodo DESC
        ");
        $stmtP->execute([$empresaId]);
        $periodos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

        $totalPages = (int)ceil($total / $perPage);
        $tipo       = 'compras';
        $pageTitle  = 'Compras — ' . $empresa['razon_social'];

        ob_start();
        require_once ROOT . '/modules/registros/views/lista.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
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
