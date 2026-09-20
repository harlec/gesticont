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

    /** Fallback sin JS: formulario clásico, redirige de vuelta al listado. */
    public function clasificar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $resultado = $this->_clasificarUno(
            $empresaId,
            $_POST['origen'] ?? '',
            (int)($_POST['documento_id'] ?? 0),
            (int)($_POST['tipo_gasto_id'] ?? 0)
        );

        if (!$resultado['ok']) {
            $_SESSION['imputacion_error'] = $resultado['error'];
            header("Location: /empresas/{$empresaId}/imputacion"); exit;
        }

        header("Location: /empresas/{$empresaId}/imputacion?ok=1"); exit;
    }

    /**
     * Clasificación por AJAX — acepta uno o varios comprobantes en un solo
     * request (selección múltiple con la misma cuenta), sin recargar la
     * página. Body JSON: {"items":[{"origen":"venta","documento_id":5,
     * "tipo_gasto_id":3}, ...]}
     */
    public function clasificarLote(int $empresaId): void
    {
        // Buffer de salida: cualquier warning/notice de PHP que se imprima
        // antes del JSON (con APP_DEBUG=true) rompería el fetch().json()
        // del navegador. Se descarta cualquier salida previa y solo se
        // emite el JSON al final.
        ob_start();
        Auth::require();
        header('Content-Type: application/json');

        $salida = function (array $data, int $status = 200) {
            ob_end_clean();
            http_response_code($status);
            echo json_encode($data);
        };

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { $salida(['error' => 'Sin acceso'], 403); return; }

        $body  = json_decode(file_get_contents('php://input'), true);
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        if (empty($items)) { $salida(['error' => 'Nada que clasificar'], 400); return; }

        $resultados = [];
        foreach ($items as $item) {
            $r = $this->_clasificarUno(
                $empresaId,
                $item['origen'] ?? '',
                (int)($item['documento_id'] ?? 0),
                (int)($item['tipo_gasto_id'] ?? 0)
            );
            $r['documento_id'] = (int)($item['documento_id'] ?? 0);
            $r['origen']       = $item['origen'] ?? '';
            $resultados[] = $r;
        }

        $salida(['resultados' => $resultados]);
    }

    private function _clasificarUno(int $empresaId, string $origen, int $documentoId, int $tipoGastoId): array
    {
        if (!in_array($origen, ['venta', 'compra'], true) || !$documentoId || !$tipoGastoId) {
            return ['ok' => false, 'error' => 'Datos incompletos.'];
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
            return ['ok' => false, 'error' => 'El comprobante ya no está pendiente o no existe.'];
        }

        $stmtTipo = $pdo->prepare("
            SELECT tg.cuenta_id, c.codigo, c.nombre
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.id = ? AND tg.activo = 1 LIMIT 1
        ");
        $stmtTipo->execute([$tipoGastoId]);
        $tipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);
        if (!$tipo) {
            return ['ok' => false, 'error' => 'Tipo de clasificación inválido.'];
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
            return ['ok' => false, 'error' => 'Error al guardar la clasificación.'];
        }

        return ['ok' => true, 'cuenta_codigo' => $tipo['codigo'], 'cuenta_nombre' => $tipo['nombre']];
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
