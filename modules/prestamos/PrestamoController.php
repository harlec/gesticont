<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/PrestamoService.php';

/** Préstamos bancarios: desembolso, pagos de capital e intereses, saldo pendiente. */
class PrestamoController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $prestamos = PrestamoService::listar($empresaId);
        $tot = ['recibido' => 0.0, 'capital' => 0.0, 'intereses' => 0.0, 'saldo' => 0.0];
        foreach ($prestamos as $p) {
            $tot['recibido'] += $p['recibido']; $tot['capital'] += $p['capital_pagado'];
            $tot['intereses'] += $p['intereses']; $tot['saldo'] += $p['saldo'];
        }

        $pageTitle = 'Préstamos bancarios — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/prestamos/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function crear(int $empresaId): void
    {
        $this->_post($empresaId);
        $r = PrestamoService::crear($empresaId, (string)($_POST['entidad'] ?? ''), $_POST['referencia'] ?? null,
            (string)($_POST['fecha'] ?? ''), (float)str_replace(',', '', (string)($_POST['monto'] ?? 0)), Auth::id());
        $r['ok'] ? $_SESSION['prestamo_ok'] = 'Préstamo registrado y desembolso enviado a Caja.' : $_SESSION['prestamo_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    public function pagar(int $empresaId, int $id): void
    {
        $this->_post($empresaId);
        $r = PrestamoService::pagar($empresaId, $id,
            (float)str_replace(',', '', (string)($_POST['capital'] ?? 0)), (float)str_replace(',', '', (string)($_POST['interes'] ?? 0)),
            (string)($_POST['fecha'] ?? ''), Auth::id());
        $r['ok'] ? $_SESSION['prestamo_ok'] = 'Pago registrado en Caja.' : $_SESSION['prestamo_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    public function eliminar(int $empresaId, int $id): void
    {
        $this->_post($empresaId);
        $r = PrestamoService::eliminar($empresaId, $id);
        $r['ok'] ? $_SESSION['prestamo_ok'] = 'Préstamo eliminado con sus movimientos de Caja.' : $_SESSION['prestamo_error'] = $r['error'];
        $this->_volver($empresaId);
    }

    private function _post(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/prestamos"); exit; }
        if (!$this->_getEmpresa($empresaId)) { http_response_code(403); die('Sin acceso'); }
    }

    private function _volver(int $empresaId): void
    {
        header("Location: /empresas/{$empresaId}/prestamos"); exit;
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
