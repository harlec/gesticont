<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/PurgaService.php';

/**
 * Depuración de la data anterior al período mínimo (ver services/PurgaService.php).
 * Irreversible, por eso: solo superadmin, una empresa a la vez, se pide escribir el RUC de
 * la empresa para confirmar, y queda registrado en actividad_log.
 */
class DepurarController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        if (!Auth::isSuperadmin()) { http_response_code(403); die('Solo el superadmin puede depurar datos.'); }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo      = Model::db();
        $hasta    = Periodo::minimo();
        $conteos  = PurgaService::conteos($pdo, $empresaId, $hasta);
        $apertura = PurgaService::apertura($pdo, $empresaId, $hasta);
        $total    = array_sum(array_column($conteos, 'n'));

        $pageTitle = 'Depurar datos anteriores — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/depurar/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    /** POST (JSON): borra UNA tanda. El navegador repite hasta que 'terminado' sea true. */
    public function paso(int $empresaId): void
    {
        Auth::require();
        header('Content-Type: application/json; charset=utf-8');
        $salir = function (array $d, int $code = 200) { http_response_code($code); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; };

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $salir(['ok' => false, 'error' => 'Método no permitido'], 405);
        if (!Auth::isSuperadmin()) $salir(['ok' => false, 'error' => 'Solo el superadmin puede depurar datos.'], 403);
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) $salir(['ok' => false, 'error' => 'No encontrado'], 404);

        // Confirmación verificada también en el servidor, no solo en el botón.
        if (trim((string)($_POST['confirmo'] ?? '')) !== (string)$empresa['ruc']) {
            $salir(['ok' => false, 'error' => 'La confirmación no coincide con el RUC de la empresa.'], 422);
        }
        $uid = Auth::id();
        session_write_close();          // no bloquear otras pantallas mientras dura el borrado
        set_time_limit(50);

        $pdo   = Model::db();
        $hasta = Periodo::minimo();
        try {
            $ap = PurgaService::apertura($pdo, $empresaId, $hasta);
            if (!$ap['ok']) $salir(['ok' => false, 'error' => "La empresa no tiene saldos de apertura de {$ap['anio']}: no se puede depurar sin perder su punto de partida."], 409);

            if (!empty($_POST['inicio'])) $this->_log($pdo, $uid, $empresaId, 'depurar_inicio', "Inicio de depuración de datos anteriores a {$hasta}");
            $r = PurgaService::paso($pdo, $empresaId, $hasta);
            if ($r['terminado']) $this->_log($pdo, $uid, $empresaId, 'depurar_fin', "Depuración terminada: datos anteriores a {$hasta} borrados");
            $salir(['ok' => true] + $r);
        } catch (Throwable $e) {
            $this->_log($pdo, $uid, $empresaId, 'depurar_error', substr($e->getMessage(), 0, 500));
            $salir(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function _log(PDO $pdo, ?int $uid, int $empresaId, string $accion, string $desc): void
    {
        try {
            $pdo->prepare("INSERT INTO actividad_log (usuario_id, empresa_id, accion, descripcion, ip) VALUES (?,?,?,?,?)")
                ->execute([$uid, $empresaId, $accion, $desc, $_SERVER['REMOTE_ADDR'] ?? null]);
        } catch (Throwable $e) { /* el registro es informativo: no debe impedir ni romper el borrado */ }
    }

    private function _getEmpresa(int $id): ?array
    {
        $stmt = Model::db()->prepare("SELECT * FROM empresas WHERE id = ? AND activo = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
