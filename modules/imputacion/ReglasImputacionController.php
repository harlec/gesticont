<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/ReglaImputacionService.php';

/**
 * Configuración de las reglas de clasificación por proveedor/cliente
 * (Fase 2 de reglas_imputacion) — vive colgada de Imputación en vez de
 * Configuración de empresa porque son reglas operativas que se ajustan
 * seguido (a diferencia del perfil comercial, que se define una vez).
 */
class ReglasImputacionController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $reglas     = ReglaImputacionService::listar($empresaId);
        $candidatas = ReglaImputacionService::escanearContrapartes($empresaId);

        $pdo = Model::db();
        $tiposCompra = $pdo->query("
            SELECT tg.id, CONCAT(c.codigo, ' - ', tg.nombre_visible) AS nombre_visible, c.id AS cuenta_id
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.aplica_a IN ('compra','ambos') AND tg.activo = 1 ORDER BY tg.orden
        ")->fetchAll(PDO::FETCH_ASSOC);
        $tiposVenta = $pdo->query("
            SELECT tg.id, CONCAT(c.codigo, ' - ', tg.nombre_visible) AS nombre_visible, c.id AS cuenta_id
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.aplica_a IN ('venta','ambos') AND tg.activo = 1 ORDER BY tg.orden
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Una regla se dispara por RUC sin importar si ese RUC resulta ser
        // cliente o proveedor, así que el selector ofrece las cuentas de
        // ambos catálogos (compra + venta), sin duplicar por cuenta.
        $tiposTodos = [];
        foreach (array_merge($tiposCompra, $tiposVenta) as $t) {
            $tiposTodos[$t['cuenta_id']] = $t;
        }
        usort($tiposTodos, fn($a, $b) => strcmp($a['nombre_visible'], $b['nombre_visible']));

        $pageTitle = 'Reglas de clasificación — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/imputacion/views/reglas.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function crear(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit; }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $ruc      = trim($_POST['ruc'] ?? '');
        $cuentaId = (int)($_POST['cuenta_id'] ?? 0);

        if ($ruc === '' || !$cuentaId) {
            $_SESSION['reglas_error'] = 'Falta el RUC o la cuenta destino.';
            header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit;
        }

        $stmtCuenta = Model::db()->prepare("SELECT id FROM cuentas_contables WHERE id = ? AND (empresa_id IS NULL OR empresa_id = ?)");
        $stmtCuenta->execute([$cuentaId, $empresaId]);
        if (!$stmtCuenta->fetch()) {
            $_SESSION['reglas_error'] = 'Cuenta destino inválida.';
            header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit;
        }

        ReglaImputacionService::crear($empresaId, $ruc, $cuentaId);
        header("Location: /empresas/{$empresaId}/imputacion/reglas?ok=1"); exit;
    }

    public function eliminar(int $empresaId, int $reglaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        try {
            ReglaImputacionService::eliminar($empresaId, $reglaId);
        } catch (PDOException $e) {
            // Ya clasificó algo (imputaciones.regla_id la referencia) — se
            // conserva la trazabilidad de lo ya hecho en vez de dejar
            // huérfano ese historial; desactivarla sí se puede siempre.
            $_SESSION['reglas_error'] = 'No se puede eliminar: ya se usó para clasificar comprobantes. Puedes desactivarla en su lugar.';
        }
        header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit;
    }

    public function toggle(int $empresaId, int $reglaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        ReglaImputacionService::toggle($empresaId, $reglaId);
        header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit;
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
