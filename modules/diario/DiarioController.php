<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/AsientoService.php';
require_once ROOT . '/services/PdfReport.php';

class DiarioController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = Periodo::resolver($empresaId);

        $asientos = $this->_asientos($empresaId, $periodo);

        // Pendientes de imputar en el período — para avisar que el asiento
        // puede quedar incompleto si aún hay documentos sin clasificar.
        $stmtPend = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id=? AND periodo=? AND estado_imputacion='pendiente' AND estado_sunat='1') AS ventas,
                (SELECT COUNT(*) FROM registro_compras WHERE empresa_id=? AND periodo=? AND estado_imputacion='pendiente' AND estado_sunat='1') AS compras
        ");
        $stmtPend->execute([$empresaId, $periodo, $empresaId, $periodo]);
        $pendientes = $stmtPend->fetch(PDO::FETCH_ASSOC);

        $pageTitle = 'Libro Diario — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/diario/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function generar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/diario"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_POST['periodo'] ?? date('Ym', strtotime('first day of -1 month'));

        $resultado = AsientoService::generarPeriodo($empresaId, $periodo, Auth::id());

        if (isset($resultado['error'])) {
            $_SESSION['diario_error'] = $resultado['error'];
        } elseif (isset($resultado['aviso'])) {
            $_SESSION['diario_aviso'] = $resultado['aviso'];
        } else {
            $_SESSION['diario_ok'] = 'Asientos generados: ' . implode(', ', array_keys($resultado['asientos']));
        }

        header("Location: /empresas/{$empresaId}/diario?periodo={$periodo}"); exit;
    }

    /** Asientos del período con sus líneas, en orden de correlativo. */
    private function _asientos(int $empresaId, string $periodo): array
    {
        $stmt = Model::db()->prepare("
            SELECT a.id, a.correlativo, a.fecha, a.glosa, a.origen,
                   d.cuenta_id, c.codigo, c.nombre, d.debe, d.haber
            FROM asientos a
            JOIN asientos_detalle d ON d.asiento_id = a.id
            JOIN cuentas_contables c ON c.id = d.cuenta_id
            WHERE a.empresa_id = ?
              AND DATE_FORMAT(a.fecha, '%Y%m') = ?
            ORDER BY a.correlativo ASC, d.id ASC
        ");
        $stmt->execute([$empresaId, $periodo]);

        $asientos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $asientos[$f['id']]['correlativo'] ??= $f['correlativo'];
            $asientos[$f['id']]['fecha']       ??= $f['fecha'];
            $asientos[$f['id']]['glosa']       ??= $f['glosa'];
            $asientos[$f['id']]['origen']      ??= $f['origen'];
            $asientos[$f['id']]['lineas'][] = $f;
        }
        return $asientos;
    }

    /** Libro Diario en PDF — una sola tabla continua (formato del Libro Diario 5.1 de SUNAT). */
    public function pdf(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $periodo  = Periodo::resolver($empresaId);
        $asientos = $this->_asientos($empresaId, $periodo);
        $pdf = new PdfReport($empresa, 'Libro Diario', 'Período ' . Periodo::etiqueta($periodo, true) . ' · Expresado en soles', 'L');

        if (empty($asientos)) {
            $pdf->alerta('Sin asientos generados para este período.', 'warn');
            $pdf->salir('libro-diario-' . $periodo);
        }

        $fmt = fn($v) => $v != 0 ? number_format((float)$v, 2) : '';
        $pdf->libroCabecera(
            ['N°', 'Fecha', 'Glosa', 'Código', 'Denominación', 'Debe', 'Haber'],
            [0.05, 0.08, 0.27, 0.07, 0.29, 0.12, 0.12],
            ['L', 'L', 'L', 'L', 'L', 'R', 'R']
        );
        $totD = $totH = 0.0;
        foreach ($asientos as $a) {
            $sd = $sh = 0.0;
            foreach ($a['lineas'] as $i => $l) {
                $pdf->libroFila([
                    $i === 0 ? $a['correlativo'] : '',
                    $i === 0 ? date('d/m/Y', strtotime($a['fecha'])) : '',
                    $i === 0 ? $a['glosa'] : '',
                    $l['codigo'], $l['nombre'], $fmt($l['debe']), $fmt($l['haber']),
                ], $i === 0 ? 'titulo' : 'normal');
                $sd += (float)$l['debe']; $sh += (float)$l['haber'];
            }
            $totD += $sd; $totH += $sh;
        }
        $pdf->libroFila(['', '', '', '', 'TOTAL DEL PERÍODO', number_format($totD, 2), number_format($totH, 2)], 'total');
        $pdf->salir('libro-diario-' . $periodo);
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
