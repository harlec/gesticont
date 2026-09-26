<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CobroPagoService.php';

/**
 * Cobros y pagos por comprobante. Al clasificar se asume que todo está
 * cobrado/pagado; aquí se corrige cuando un cliente pagó solo una parte o
 * fue a crédito (en cualquier momento, mientras el año siga abierto).
 */
class CobranzaController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $origen  = $this->_origen($_GET['origen'] ?? 'venta');
        $periodo = Periodo::resolver($empresaId);
        $estado  = in_array($_GET['estado'] ?? '', ['credito', 'parcial', 'pagado'], true) ? $_GET['estado'] : 'todos';
        $c       = CobroPagoService::config($origen);

        $contraparte = $origen === 'venta' ? 'd.cliente_nombre' : 'd.proveedor_nombre';
        $stmt = $pdo->prepare("
            SELECT d.id, d.tipo_comp, d.serie, d.correlativo, d.fecha_emision, d.total,
                   {$contraparte} AS contraparte, COALESCE(p.pagado, 0) AS pagado, COALESCE(p.movs, 0) AS movs
            FROM {$c['tabla']} d
            LEFT JOIN (
                SELECT {$c['col']} AS doc, SUM(monto) AS pagado, COUNT(*) AS movs
                FROM caja_movimientos WHERE empresa_id = ? AND tipo = ? AND {$c['col']} IS NOT NULL
                GROUP BY {$c['col']}
            ) p ON p.doc = d.id
            WHERE d.empresa_id = ? AND d.periodo = ? AND d.estado_imputacion = 'imputado' AND d.estado_sunat = '1'
            ORDER BY d.fecha_emision, d.id
        ");
        $stmt->execute([$empresaId, $c['tipoMov'], $empresaId, $periodo]);

        $docs = [];
        $resumen = ['total' => 0.0, 'pagado' => 0.0, 'saldo' => 0.0, 'credito' => 0, 'parcial' => 0, 'pagado_n' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $total  = round((float)$d['total'], 2);
            $pagado = round((float)$d['pagado'], 2);
            $saldo  = round($total - $pagado, 2);
            $d['saldo']  = $saldo;
            $d['estado'] = $saldo <= CobroPagoService::TOLERANCIA ? 'pagado' : ($pagado > 0 ? 'parcial' : 'credito');

            $resumen['total'] += $total; $resumen['pagado'] += $pagado; $resumen['saldo'] += max($saldo, 0);
            $resumen[$d['estado'] === 'pagado' ? 'pagado_n' : $d['estado']]++;

            if ($estado === 'todos' || $estado === $d['estado']) $docs[] = $d;
        }

        // Períodos con comprobantes clasificados, para el selector.
        $stmtP = $pdo->prepare("
            SELECT DISTINCT periodo FROM {$c['tabla']}
            WHERE empresa_id = ? AND estado_imputacion = 'imputado' AND estado_sunat = '1' ORDER BY periodo DESC
        ");
        $stmtP->execute([$empresaId]);
        $periodos = $stmtP->fetchAll(PDO::FETCH_COLUMN);

        $pageTitle = 'Cobros y pagos — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/cobranzas/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function registrar(int $empresaId): void
    {
        $empresa = $this->_post($empresaId);
        $origen = $this->_origen($_POST['origen'] ?? 'venta');

        $r = CobroPagoService::registrar(
            $empresaId, $origen, (int)($_POST['documento_id'] ?? 0),
            (float)str_replace(',', '', (string)($_POST['monto'] ?? 0)),
            !empty($_POST['fecha']) ? $_POST['fecha'] : null,
            Auth::id()
        );
        $this->_volver($empresaId, $r['ok'] ? 'Registrado.' : null, $r['ok'] ? null : $r['error']);
    }

    public function credito(int $empresaId): void
    {
        $empresa = $this->_post($empresaId);
        $origen = $this->_origen($_POST['origen'] ?? 'venta');

        $r = CobroPagoService::pasarACredito($empresaId, $origen, (int)($_POST['documento_id'] ?? 0));
        $this->_volver($empresaId, $r['ok'] ? 'Pasado a crédito: se quitaron sus cobros/pagos de Caja.' : null, $r['ok'] ? null : $r['error']);
    }

    public function todo(int $empresaId): void
    {
        $empresa = $this->_post($empresaId);
        $origen  = $this->_origen($_POST['origen'] ?? 'venta');
        $periodo = preg_match('/^\d{6}$/', $_POST['periodo'] ?? '') ? $_POST['periodo'] : Periodo::resolver($empresaId);

        $r = CobroPagoService::marcarTodoPagado($empresaId, $origen, $periodo, Auth::id());
        $msg = $r['cantidad'] . ' comprobante(s) marcados como ' . ($origen === 'venta' ? 'cobrados' : 'pagados') . ' por su saldo.';
        $this->_volver($empresaId, $msg, $r['errores'] ? "{$r['errores']} no se pudieron marcar (año cerrado)." : null);
    }

    /** Valida método POST y acceso; devuelve la empresa. */
    private function _post(int $empresaId): array
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/cobranzas"); exit; }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }
        return $empresa;
    }

    private function _volver(int $empresaId, ?string $ok, ?string $error): void
    {
        if ($ok)    $_SESSION['cobranza_ok']    = $ok;
        if ($error) $_SESSION['cobranza_error'] = $error;
        $qs = http_build_query([
            'origen'  => $this->_origen($_POST['origen'] ?? 'venta'),
            'periodo' => preg_match('/^\d{6}$/', $_POST['periodo'] ?? '') ? $_POST['periodo'] : Periodo::resolver($empresaId),
            'estado'  => $_POST['estado'] ?? 'todos',
        ]);
        header("Location: /empresas/{$empresaId}/cobranzas?{$qs}"); exit;
    }

    private function _origen(string $v): string
    {
        return $v === 'compra' ? 'compra' : 'venta';
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
