<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';

/**
 * Cambia el período de trabajo activo desde el selector de la barra de
 * identidad (nav.php) — no muestra nada, solo guarda en sesión y redirige
 * de vuelta a la página en la que estaba el usuario, ya con ese período
 * aplicado (ver Periodo::resolver, que cada controlador de pantalla usa
 * en vez de leer $_GET['periodo'] directo).
 */
class PeriodoController
{
    public function set(int $empresaId): void
    {
        Auth::require();
        if (!$this->_tieneAcceso($empresaId)) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_GET['periodo'] ?? '';
        if (preg_match('/^\d{6}$/', $periodo)) {
            $_SESSION['periodo_activo'][$empresaId] = $periodo;
        }

        $volver = $_GET['volver'] ?? '';
        // Solo se acepta una ruta relativa dentro de la misma empresa —
        // nunca una URL externa (evita usarlo como redirector abierto).
        if (!preg_match('#^/empresas/' . $empresaId . '(/|$|\?)#', $volver)) {
            $volver = "/empresas/{$empresaId}";
        }

        header("Location: {$volver}"); exit;
    }

    private function _tieneAcceso(int $id): bool
    {
        $pdo = Model::db();
        if (Auth::isSuperadmin()) {
            $stmt = $pdo->prepare("SELECT 1 FROM empresas WHERE id = ? AND activo = 1");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 1 FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ?
            ");
            $stmt->execute([$id, Auth::id()]);
        }
        return (bool)$stmt->fetchColumn();
    }
}
