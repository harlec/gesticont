<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';

/** Ventas y Compras sincronizadas del período: tarjetas, buscador, filtros por tipo y descarga a Excel (CSV). */
class RegistroController
{
    public function ventas(int $empresaId): void  { $this->_lista($empresaId, 'ventas'); }
    public function compras(int $empresaId): void { $this->_lista($empresaId, 'compras'); }
    public function ventasExcel(int $empresaId): void  { $this->_excel($empresaId, 'ventas'); }
    public function comprasExcel(int $empresaId): void { $this->_excel($empresaId, 'compras'); }

    private const PASO = 15;

    /** Condición SQL y parámetros de los filtros q (búsqueda) y f (todos|boletas|facturas|anulados). */
    private function _filtros(string $tipo, int $empresaId, string $periodo, string $q, string $f): array
    {
        $esV = $tipo === 'ventas';
        $where = "d.empresa_id = ? AND d.periodo = ?"; $par = [$empresaId, $periodo];
        if ($f === 'boletas')       $where .= " AND d.tipo_comp = '03' AND d.estado_sunat = '1'";
        elseif ($f === 'facturas')  $where .= " AND d.tipo_comp = '01' AND d.estado_sunat = '1'";
        elseif ($f === 'anulados')  $where .= " AND d.estado_sunat <> '1'";
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where .= $esV ? " AND (CONCAT(d.serie,'-',d.correlativo) LIKE ? OR d.cliente_nombre LIKE ? OR d.cliente_num_doc LIKE ?)"
                           : " AND (CONCAT(d.serie,'-',d.correlativo) LIKE ? OR d.proveedor_nombre LIKE ? OR d.proveedor_ruc LIKE ?)";
            array_push($par, $like, $like, $like);
        }
        return [$where, $par];
    }

    private function _lista(int $empresaId, string $tipo): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo = Model::db(); $esV = $tipo === 'ventas';
        $tabla = $esV ? 'registro_ventas' : 'registro_compras'; $col = $esV ? 'registro_venta_id' : 'registro_compra_id';
        $periodo = Periodo::resolver($empresaId);
        $q = trim((string)($_GET['q'] ?? ''));
        $f = in_array($_GET['f'] ?? '', ['boletas', 'facturas', 'anulados'], true) ? $_GET['f'] : 'todos';
        $mostrar = max(self::PASO, (int)($_GET['n'] ?? self::PASO));

        // Tarjetas y conteos de chips: sobre TODO el período (sin anulados), no sobre el filtro
        $st = $pdo->prepare("
            SELECT COALESCE(SUM(estado_sunat = '1'),0) AS cant,
                   COALESCE(SUM(CASE WHEN estado_sunat = '1' THEN base_imponible END),0) AS base,
                   COALESCE(SUM(CASE WHEN estado_sunat = '1' THEN igv END),0) AS igv,
                   COALESCE(SUM(CASE WHEN estado_sunat = '1' THEN total END),0) AS total,
                   COUNT(*) AS todos,
                   COALESCE(SUM(tipo_comp = '03' AND estado_sunat = '1'),0) AS boletas,
                   COALESCE(SUM(tipo_comp = '01' AND estado_sunat = '1'),0) AS facturas,
                   COALESCE(SUM(estado_sunat <> '1'),0) AS anulados
            FROM {$tabla} WHERE empresa_id = ? AND periodo = ?
        ");
        $st->execute([$empresaId, $periodo]);
        $resumen = $st->fetch(PDO::FETCH_ASSOC);

        [$where, $par] = $this->_filtros($tipo, $empresaId, $periodo, $q, $f);
        $c = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} d WHERE {$where}"); $c->execute($par);
        $totalFiltrado = (int)$c->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT d.*, (SELECT GROUP_CONCAT(DISTINCT c.codigo ORDER BY c.codigo SEPARATOR ', ')
                         FROM imputaciones i JOIN cuentas_contables c ON c.id = i.cuenta_id WHERE i.{$col} = d.id) AS cuentas
            FROM {$tabla} d WHERE {$where}
            ORDER BY d.fecha_emision ASC, d.serie ASC, d.correlativo ASC LIMIT " . (int)$mostrar);
        $stmt->execute($par);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = ($esV ? 'Ventas' : 'Compras') . ' — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/registros/views/lista.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    /** CSV (se abre en Excel) con los comprobantes del período y los filtros activos. */
    private function _excel(int $empresaId, string $tipo): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }
        $pdo = Model::db(); $esV = $tipo === 'ventas';
        $tabla = $esV ? 'registro_ventas' : 'registro_compras';
        $periodo = Periodo::resolver($empresaId);
        $q = trim((string)($_GET['q'] ?? ''));
        $f = in_array($_GET['f'] ?? '', ['boletas', 'facturas', 'anulados'], true) ? $_GET['f'] : 'todos';
        [$where, $par] = $this->_filtros($tipo, $empresaId, $periodo, $q, $f);
        $stmt = $pdo->prepare("SELECT d.* FROM {$tabla} d WHERE {$where} ORDER BY d.fecha_emision, d.serie, d.correlativo");
        $stmt->execute($par);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ($esV ? 'ventas' : 'compras') . '-' . $empresa['ruc'] . '-' . $periodo . '.csv"');
        $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['Fecha', 'Tipo', 'Serie', 'Número', $esV ? 'Documento cliente' : 'RUC proveedor', $esV ? 'Cliente' : 'Proveedor', 'Base imponible', 'IGV', 'Total', 'Estado'], ',', '"', '\\');
        $tipos = ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito'];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            fputcsv($o, [$r['fecha_emision'], $tipos[$r['tipo_comp']] ?? $r['tipo_comp'], $r['serie'], $r['correlativo'],
                $esV ? $r['cliente_num_doc'] : $r['proveedor_ruc'], $esV ? $r['cliente_nombre'] : $r['proveedor_nombre'],
                number_format((float)$r['base_imponible'], 2, '.', ''), number_format((float)$r['igv'], 2, '.', ''), number_format((float)$r['total'], 2, '.', ''),
                $r['estado_sunat'] === '1' ? 'Activo' : 'Anulado'], ',', '"', '\\');
        }
        fclose($o); exit;
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
