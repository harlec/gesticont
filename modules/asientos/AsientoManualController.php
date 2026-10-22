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

        // Datos para las plantillas que proponen un monto: existencias en libros (costo de ventas) y estimación del impuesto a la renta
        require_once ROOT . '/services/BalanceService.php';
        require_once ROOT . '/services/EstadoResultadosService.php';
        $periodo = Periodo::resolver($empresaId);
        $fechaPeriodo = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
        $plantillaDatos = ['existencias' => 0.0, 'inicial' => 0.0, 'compras' => 0.0, 'compras_bruto' => 0.0, 'saldo611' => 0.0, 'saldo601' => 0.0, 'ir_estimado' => 0.0, 'tasa_ir' => 29.5, 'ir_registrado' => false, 'utilidad_antes' => 0.0];
        $stPer = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stPer->execute([$empresaId, (int)substr($periodo, 0, 4)]);
        if ($pid = (int)$stPer->fetchColumn()) {
            $bal = BalanceService::comprobacion($empresaId, $pid, $fechaPeriodo);
            foreach ($bal['filas'] as $f) if ($f['codigo'] === '201') $plantillaDatos['existencias'] = round($f['activo'], 2);
            // Desglose para el costo de ventas: existencia inicial (apertura) + compras del año destinadas a 201
            $stIni = $pdo->prepare("SELECT COALESCE(SUM(sa.debe - sa.haber),0) FROM saldos_apertura sa JOIN cuentas_contables c ON c.id = sa.cuenta_id WHERE sa.empresa_id = ? AND sa.periodo_id = ? AND c.codigo = '201'");
            $stIni->execute([$empresaId, $pid]);
            $plantillaDatos['inicial'] = round((float)$stIni->fetchColumn(), 2);
            $plantillaDatos['compras'] = round($plantillaDatos['existencias'] - $plantillaDatos['inicial'], 2);
            // Compras del año (base sin IGV, comprobantes vigentes) hasta el mes de trabajo
            $stB = $pdo->prepare("SELECT COALESCE(SUM(total - igv),0) FROM registro_compras WHERE empresa_id = ? AND periodo LIKE ? AND periodo <= ? AND estado_sunat = '1'");
            $stB->execute([$empresaId, substr($periodo, 0, 4) . '%', $periodo]);
            $plantillaDatos['compras_bruto'] = round((float)$stB->fetchColumn(), 2);
            // Saldos a cancelar entre sí: variación de inventarios (611, acreedor) y compras de mercaderías (601, deudor)
            foreach ($bal['filas'] as $f) {
                if ($f['codigo'] === '611') $plantillaDatos['saldo611'] = round($f['acreedor'] - $f['deudor'], 2);
                if ($f['codigo'] === '601') $plantillaDatos['saldo601'] = round($f['deudor'] - $f['acreedor'], 2);
            }
            $stPar = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
            $stPar->execute([$empresaId, $pid]);
            if ($par = $stPar->fetch(PDO::FETCH_ASSOC)) {
                $er = EstadoResultadosService::generar($empresaId, $pid, $fechaPeriodo, $par);
                $plantillaDatos['tasa_ir'] = (float)$par['tasa_ir']; $plantillaDatos['utilidad_antes'] = $er['utilidad_antes_impuestos'];
                $plantillaDatos['ir_registrado'] = $er['ir_registrado'];
                $plantillaDatos['ir_estimado'] = $er['ir_registrado'] ? 0.0 : $er['impuesto_renta'];
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
        // Los gastos por naturaleza (62 a 68) pasan a 94/95 al volver a generar el Diario; el costo de ventas (69) y el impuesto (88) no.
        $ids = array_filter(array_map('intval', array_column($lineas, 'cuenta_id')));
        $pasaPorDestino = false;
        if ($ids) {
            $st = Model::db()->prepare('SELECT codigo FROM cuentas_contables WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $st->execute(array_values($ids));
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $cod) if (preg_match('/^6[2-8]/', $cod)) $pasaPorDestino = true;
        }
        $_SESSION['asiento_ok'] = ($id ? 'Asiento actualizado.' : 'Asiento N.° ' . $r['correlativo'] . ' registrado.')
            . ($pasaPorDestino ? ' Vuelve a generar el Libro Diario de ese mes para que el gasto pase a administración o ventas (94/95).' : '');
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
