<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/VencimientosService.php';

/** Marca una obligación (SIRE, PDT 621, PLAME) como presentada o la desmarca. Es un registro del avance del contador. */
class ObligacionController
{
    public function marcar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}"); exit; }
        $pdo = Model::db();
        if (Auth::isSuperadmin()) {
            $st = $pdo->prepare("SELECT 1 FROM empresas WHERE id = ? AND activo = 1"); $st->execute([$empresaId]);
        } else {
            $st = $pdo->prepare("SELECT 1 FROM empresas e INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ? AND eu.activo = 1");
            $st->execute([$empresaId, Auth::id()]);
        }
        if (!$st->fetchColumn()) { http_response_code(403); die('Sin acceso'); }

        $r = VencimientosService::marcar($pdo, $empresaId, (string)($_POST['tipo'] ?? ''), (string)($_POST['periodo'] ?? ''), ($_POST['presentado'] ?? '1') === '1', Auth::id());
        $_SESSION[$r['ok'] ? 'obl_ok' : 'obl_error'] = $r['ok']
            ? (($_POST['presentado'] ?? '1') === '1' ? 'Marcado como presentado.' : 'Se quitó la marca de presentado.')
            : $r['error'];
        header("Location: /empresas/{$empresaId}"); exit;
    }
}
