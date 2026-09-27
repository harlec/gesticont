<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/AsientoService.php';

/**
 * Asientos manuales: ajustes, provisiones (CTS, gratificaciones), depreciación
 * y correcciones que el contador registra a mano. Entran al Diario, Mayor y
 * Balances como cualquier otro asiento (origen "manual") y la regeneración
 * automática nunca los borra.
 */
class AsientoManualController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo  = Model::db();
        $anio = Periodo::anio($empresaId);

        $cuentas = $pdo->prepare("
            SELECT id, codigo, nombre FROM cuentas_contables
            WHERE (empresa_id IS NULL OR empresa_id = ?) AND nivel >= 3 ORDER BY codigo
        ");
        $cuentas->execute([$empresaId]);
        $cuentas = $cuentas->fetchAll(PDO::FETCH_ASSOC);

        $manuales = AsientoService::listarManuales($empresaId, $anio);

        $editando = null;
        if (!empty($_GET['editar'])) {
            foreach ($manuales as $m) if ((int)$m['id'] === (int)$_GET['editar']) $editando = $m;
            if (!$editando) {
                // puede estar en otro año: buscarlo directamente
                $stmt = $pdo->prepare("SELECT YEAR(fecha) FROM asientos WHERE id = ? AND empresa_id = ? AND origen = 'manual'");
                $stmt->execute([(int)$_GET['editar'], $empresaId]);
                $otro = $stmt->fetchColumn();
                if ($otro) { header("Location: /empresas/{$empresaId}/asientos?anio={$otro}&editar=" . (int)$_GET['editar']); exit; }
            }
        }

        $pageTitle = 'Asientos manuales — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/asientos/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function guardar(int $empresaId): void
    {
        $this->_post($empresaId);

        $lineas = [];
        $cuentas = $_POST['cuenta_id'] ?? []; $debes = $_POST['debe'] ?? []; $haberes = $_POST['haber'] ?? [];
        foreach ((array)$cuentas as $i => $c) {
            $lineas[] = ['cuenta_id' => $c, 'debe' => $debes[$i] ?? 0, 'haber' => $haberes[$i] ?? 0];
        }
        $fecha = (string)($_POST['fecha'] ?? '');
        $glosa = (string)($_POST['glosa'] ?? '');
        $id    = (int)($_POST['asiento_id'] ?? 0);

        $r = $id ? AsientoService::actualizarManual($empresaId, $id, $fecha, $glosa, $lineas)
                 : AsientoService::crearManual($empresaId, $fecha, $glosa, $lineas);

        $anio = preg_match('/^\d{4}/', $fecha) ? substr($fecha, 0, 4) : Periodo::anio($empresaId);
        if (!$r['ok']) {
            $_SESSION['asiento_error'] = $r['error'];
            // conserva lo escrito para no obligar a volver a digitarlo
            $_SESSION['asiento_borrador'] = ['fecha' => $fecha, 'glosa' => $glosa, 'lineas' => $lineas, 'id' => $id];
            header("Location: /empresas/{$empresaId}/asientos?anio={$anio}" . ($id ? "&editar={$id}" : '')); exit;
        }
        $_SESSION['asiento_ok'] = ($id ? 'Asiento actualizado.' : 'Asiento N.° ' . $r['correlativo'] . ' registrado.')
            . ' Si usaste cuentas de gasto (6x), regenera los asientos de ese mes en el Libro Diario para que el gasto pase por destino (94/95).';
        header("Location: /empresas/{$empresaId}/asientos?anio={$anio}"); exit;
    }

    public function eliminar(int $empresaId, int $aid): void
    {
        $this->_post($empresaId);
        $r = AsientoService::eliminarManual($empresaId, $aid);
        $r['ok'] ? $_SESSION['asiento_ok'] = 'Asiento eliminado.' : $_SESSION['asiento_error'] = $r['error'];
        header("Location: /empresas/{$empresaId}/asientos?anio=" . (preg_match('/^\d{4}$/', $_POST['anio'] ?? '') ? $_POST['anio'] : Periodo::anio($empresaId))); exit;
    }

    private function _post(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/asientos"); exit; }
        if (!$this->_getEmpresa($empresaId)) { http_response_code(403); die('Sin acceso'); }
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
