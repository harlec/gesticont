<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';

/**
 * Caja y Bancos por empresa (spec 2.5) — distinto del CajaController
 * genérico existente (/caja, sin scope de empresa, todavía sin
 * funcionalidad real). Este es el que alimenta el asiento de Caja (2.7).
 */
class CajaMovimientoController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = $_GET['periodo'] ?? date('Ym', strtotime('-1 month'));

        $stmt = $pdo->prepare("
            SELECT cm.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
            FROM caja_movimientos cm
            LEFT JOIN cuentas_contables c ON c.id = cm.cuenta_id
            WHERE cm.empresa_id = ? AND DATE_FORMAT(cm.fecha, '%Y%m') = ?
            ORDER BY cm.fecha, cm.id
        ");
        $stmt->execute([$empresaId, $periodo]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totales = ['ingreso' => 0, 'egreso' => 0];
        foreach ($registros as $r) $totales[$r['tipo']] += (float)$r['monto'];

        // Cuentas relevantes para elegir contrapartida, agrupadas por tipo.
        $cuentas = $pdo->query("
            SELECT id, codigo, nombre, tipo FROM cuentas_contables
            WHERE empresa_id IS NULL AND nivel >= 3 AND tipo IN ('activo','pasivo','gasto')
            ORDER BY tipo, codigo
        ")->fetchAll(PDO::FETCH_ASSOC);
        $cuentasPorTipo = ['activo' => [], 'pasivo' => [], 'gasto' => []];
        foreach ($cuentas as $c) $cuentasPorTipo[$c['tipo']][] = $c;

        $pageTitle = 'Caja y Bancos — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/caja/views/movimientos.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function store(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/caja"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo    = $_POST['periodo'] ?? date('Ym');
        $tipo       = $_POST['tipo'] ?? '';
        $descripcion = trim($_POST['descripcion'] ?? '');
        $monto      = (float)($_POST['monto'] ?? 0);
        $fecha      = $_POST['fecha'] ?? date('Y-m-d');
        $cuentaId   = (int)($_POST['cuenta_id'] ?? 0);

        if (!in_array($tipo, ['ingreso', 'egreso'], true) || empty($descripcion) || $monto <= 0 || !$cuentaId) {
            $_SESSION['caja_error'] = 'Datos incompletos.';
            header("Location: /empresas/{$empresaId}/caja?periodo={$periodo}"); exit;
        }

        Model::db()->prepare("
            INSERT INTO caja_movimientos (empresa_id, tipo, cuenta_id, descripcion, monto, fecha, registrado_por)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([$empresaId, $tipo, $cuentaId, $descripcion, $monto, $fecha, Auth::id()]);

        header("Location: /empresas/{$empresaId}/caja?periodo={$periodo}&ok=1"); exit;
    }

    public function eliminar(int $empresaId, int $id): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/caja"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_POST['periodo'] ?? date('Ym');
        Model::db()->prepare("DELETE FROM caja_movimientos WHERE id = ? AND empresa_id = ?")
            ->execute([$id, $empresaId]);

        header("Location: /empresas/{$empresaId}/caja?periodo={$periodo}"); exit;
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
