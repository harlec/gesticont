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

        $origen     = $this->_origen($_GET['origen'] ?? 'compra');
        $reglas     = ReglaImputacionService::listar($empresaId, $origen);
        $candidatas = ReglaImputacionService::escanearContrapartes($empresaId, $origen);
        $conteos    = ReglaImputacionService::contarPorOrigen($empresaId);

        // Una regla de compra apunta a cuentas de compra/gasto y una de
        // venta a cuentas de ingreso — el selector ofrece solo el catálogo
        // del origen activo.
        $tipos = Model::db()->query("
            SELECT tg.id, CONCAT(c.codigo, ' - ', tg.nombre_visible) AS nombre_visible, c.id AS cuenta_id
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.aplica_a IN ('" . $origen . "','ambos') AND tg.activo = 1 ORDER BY tg.orden
        ")->fetchAll(PDO::FETCH_ASSOC);

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

        $origen   = $this->_origen($_POST['origen'] ?? 'compra');
        $ruc      = trim($_POST['ruc'] ?? '');
        $cuentaId = (int)($_POST['cuenta_id'] ?? 0);
        $volver   = "/empresas/{$empresaId}/imputacion/reglas?origen={$origen}";

        if ($ruc === '' || !$cuentaId) {
            $_SESSION['reglas_error'] = 'Falta el RUC o la cuenta destino.';
            header("Location: {$volver}"); exit;
        }

        $stmtCuenta = Model::db()->prepare("SELECT id FROM cuentas_contables WHERE id = ? AND (empresa_id IS NULL OR empresa_id = ?)");
        $stmtCuenta->execute([$cuentaId, $empresaId]);
        if (!$stmtCuenta->fetch()) {
            $_SESSION['reglas_error'] = 'Cuenta destino inválida.';
            header("Location: {$volver}"); exit;
        }

        ReglaImputacionService::crear($empresaId, $origen, $ruc, $cuentaId);
        header("Location: {$volver}&ok=1"); exit;
    }

    public function eliminar(int $empresaId, int $reglaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $volver = "/empresas/{$empresaId}/imputacion/reglas?origen=" . $this->_origen($_POST['origen'] ?? 'compra');
        try {
            ReglaImputacionService::eliminar($empresaId, $reglaId);
        } catch (PDOException $e) {
            // Ya clasificó algo (imputaciones.regla_id la referencia) — se
            // conserva la trazabilidad de lo ya hecho en vez de dejar
            // huérfano ese historial; desactivarla sí se puede siempre.
            $_SESSION['reglas_error'] = 'No se puede eliminar: ya se usó para clasificar comprobantes. Puedes desactivarla en su lugar.';
        }
        header("Location: {$volver}"); exit;
    }

    public function toggle(int $empresaId, int $reglaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/imputacion/reglas"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        ReglaImputacionService::toggle($empresaId, $reglaId);
        header("Location: /empresas/{$empresaId}/imputacion/reglas?origen=" . $this->_origen($_POST['origen'] ?? 'compra')); exit;
    }

    private function _origen(string $v): string
    {
        return $v === 'venta' ? 'venta' : 'compra';
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
