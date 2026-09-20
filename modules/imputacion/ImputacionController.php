<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';

class ImputacionController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo = Model::db();

        $periodo    = $_GET['periodo'] ?? date('Ym', strtotime('-1 month'));
        $tipoFiltro = $_GET['tipo']    ?? 'todos'; // todos | ventas | compras

        $pendientes = [];

        if ($tipoFiltro !== 'compras') {
            $stmtV = $pdo->prepare("
                SELECT id, 'venta' AS origen, tipo_comp, serie, correlativo, fecha_emision,
                       cliente_nombre AS contraparte_nombre, cliente_num_doc AS contraparte_doc,
                       (total - igv) AS monto_neto, total
                FROM registro_ventas
                WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1'
            ");
            $stmtV->execute([$empresaId, $periodo]);
            $pendientes = array_merge($pendientes, $stmtV->fetchAll(PDO::FETCH_ASSOC));
        }

        if ($tipoFiltro !== 'ventas') {
            $stmtC = $pdo->prepare("
                SELECT id, 'compra' AS origen, tipo_comp, serie, correlativo, fecha_emision,
                       proveedor_nombre AS contraparte_nombre, proveedor_ruc AS contraparte_doc,
                       (total - igv) AS monto_neto, total
                FROM registro_compras
                WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1'
            ");
            $stmtC->execute([$empresaId, $periodo]);
            $pendientes = array_merge($pendientes, $stmtC->fetchAll(PDO::FETCH_ASSOC));
        }

        usort($pendientes, fn($a, $b) => $a['fecha_emision'] <=> $b['fecha_emision']);

        // Periodos con algo pendiente, para el selector — evita mandar al
        // contador a un mes que ya no tiene nada que clasificar.
        $stmtPeriodos = $pdo->prepare("
            SELECT periodo FROM registro_ventas  WHERE empresa_id = ? AND estado_imputacion = 'pendiente'
            UNION
            SELECT periodo FROM registro_compras WHERE empresa_id = ? AND estado_imputacion = 'pendiente'
            ORDER BY periodo DESC
        ");
        $stmtPeriodos->execute([$empresaId, $empresaId]);
        $periodosConPendientes = $stmtPeriodos->fetchAll(PDO::FETCH_COLUMN);

        // Se incluye siempre el código de la cuenta PCGE en el nombre visible
        // ("601 - Mercadería") para que el contador sepa a qué cuenta va cada
        // clasificación, no solo el nombre en lenguaje llano.
        $tiposCompra = $pdo->query("
            SELECT tg.id, CONCAT(c.codigo, ' - ', tg.nombre_visible) AS nombre_visible
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.aplica_a IN ('compra','ambos') AND tg.activo = 1 ORDER BY tg.orden
        ")->fetchAll(PDO::FETCH_ASSOC);

        $tiposVenta = $pdo->query("
            SELECT tg.id, CONCAT(c.codigo, ' - ', tg.nombre_visible) AS nombre_visible
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.aplica_a IN ('venta','ambos') AND tg.activo = 1 ORDER BY tg.orden
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Sugerencia no vinculante: última cuenta usada para la misma contraparte
        // (mejora de UX barata de la spec — no es el motor de reglas de Fase 2).
        $sugerencias = [];
        foreach ($pendientes as $doc) {
            $docNum = $doc['contraparte_doc'];
            if (empty($docNum) || isset($sugerencias[$doc['origen'] . ':' . $docNum])) continue;

            if ($doc['origen'] === 'venta') {
                $tabla = 'registro_ventas'; $col = 'registro_venta_id'; $docCol = 'cliente_num_doc';
            } else {
                $tabla = 'registro_compras'; $col = 'registro_compra_id'; $docCol = 'proveedor_ruc';
            }

            $stmtSug = $pdo->prepare("
                SELECT tg.id AS tipo_gasto_id
                FROM imputaciones i
                JOIN {$tabla} d   ON d.id = i.{$col}
                JOIN tipos_gasto tg ON tg.cuenta_id = i.cuenta_id
                WHERE d.empresa_id = ? AND d.{$docCol} = ?
                ORDER BY i.created_at DESC LIMIT 1
            ");
            $stmtSug->execute([$empresaId, $docNum]);
            $sug = $stmtSug->fetch(PDO::FETCH_ASSOC);
            if ($sug) $sugerencias[$doc['origen'] . ':' . $docNum] = (int)$sug['tipo_gasto_id'];
        }

        $pageTitle = 'Clasificación de comprobantes — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/imputacion/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function clasificar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $origen      = $_POST['origen'] ?? '';
        $documentoId = (int)($_POST['documento_id'] ?? 0);
        $tipoGastoId = (int)($_POST['tipo_gasto_id'] ?? 0);

        if (!in_array($origen, ['venta', 'compra'], true) || !$documentoId || !$tipoGastoId) {
            $_SESSION['imputacion_error'] = 'Datos incompletos.';
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        $tabla = $origen === 'venta' ? 'registro_ventas' : 'registro_compras';
        $col   = $origen === 'venta' ? 'registro_venta_id' : 'registro_compra_id';

        $pdo = Model::db();

        $stmtDoc = $pdo->prepare("
            SELECT * FROM {$tabla}
            WHERE id = ? AND empresa_id = ? AND estado_imputacion = 'pendiente' LIMIT 1
        ");
        $stmtDoc->execute([$documentoId, $empresaId]);
        $doc = $stmtDoc->fetch(PDO::FETCH_ASSOC);
        if (!$doc) {
            $_SESSION['imputacion_error'] = 'El comprobante ya no está pendiente o no existe.';
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        $stmtTipo = $pdo->prepare("SELECT cuenta_id FROM tipos_gasto WHERE id = ? AND activo = 1 LIMIT 1");
        $stmtTipo->execute([$tipoGastoId]);
        $tipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);
        if (!$tipo) {
            $_SESSION['imputacion_error'] = 'Tipo de clasificación inválido.';
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        // Monto que se imputa a la cuenta de gasto/ingreso por naturaleza: el
        // valor de la operación SIN el IGV (que ya tiene su propio destino fijo
        // en el asiento: 4011). Cubre gravado + exonerado + inafecto.
        $montoNeto = (float)$doc['total'] - (float)$doc['igv'];

        Model::beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO imputaciones ({$col}, cuenta_id, monto, usuario_id)
                VALUES (?, ?, ?, ?)
            ")->execute([$documentoId, $tipo['cuenta_id'], $montoNeto, Auth::id()]);

            $pdo->prepare("UPDATE {$tabla} SET estado_imputacion = 'imputado' WHERE id = ?")
                ->execute([$documentoId]);

            Model::commit();
        } catch (Exception $e) {
            Model::rollback();
            $_SESSION['imputacion_error'] = 'Error al guardar la clasificación.';
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        header("Location: /empresas/{$empresaId}/imputacion?ok=1"); exit;
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
