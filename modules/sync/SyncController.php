<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/EncryptService.php';
require_once ROOT . '/services/SunatApiService.php';

class SyncController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo = Model::db();

        $periodos = [];
        for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("-{$i} month"));

        $stmtV = $pdo->prepare("
            SELECT periodo, COUNT(*) as cant, SUM(total) as total, MAX(fuente) as fuente
            FROM registro_ventas WHERE empresa_id = ? GROUP BY periodo ORDER BY periodo DESC
        ");
        $stmtV->execute([$empresaId]);
        $ventasSinc = $stmtV->fetchAll(PDO::FETCH_ASSOC);

        $stmtC = $pdo->prepare("
            SELECT periodo, COUNT(*) as cant, SUM(total) as total, MAX(fuente) as fuente
            FROM registro_compras WHERE empresa_id = ? GROUP BY periodo ORDER BY periodo DESC
        ");
        $stmtC->execute([$empresaId]);
        $comprasSinc = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = 'Sincronizar SIRE — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/sync/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function ejecutar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/sync"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $tipo    = $_POST['tipo']    ?? 'ambos';
        $rango   = $_POST['rango']   ?? 'periodo';
        $periodo = $_POST['periodo'] ?? date('Ym', strtotime('-1 month'));

        $periodos = $rango === 'todo'
            ? array_map(fn($i) => date('Ym', strtotime("-{$i} month")), range(1, 12))
            : [$periodo];

        $pdo     = Model::db();
        $stmtCrt = $pdo->prepare("
            SELECT sol_usuario, sol_clave, api_client_id, api_client_secret
            FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1
        ");
        $stmtCrt->execute([$empresaId]);
        $cert = $stmtCrt->fetch(PDO::FETCH_ASSOC);

        if (!$cert || empty($cert['api_client_id'])) {
            $_SESSION['sync_error'] = 'Sin credenciales API configuradas.';
            header("Location: /empresas/{$empresaId}/sync"); exit;
        }

        $enc   = new EncryptService();
        $sunat = new SunatApiService();
        $token = $sunat->getToken(
            $enc->decrypt($cert['api_client_id']),
            $enc->decrypt($cert['api_client_secret']),
            $empresa['ruc'],
            $enc->decrypt($cert['sol_usuario']),
            $enc->decrypt($cert['sol_clave'])
        );

        $resultado = ['ventas' => [], 'compras' => []];

        foreach ($periodos as $p) {

            // ── Ventas ──────────────────────────────────────────────────────
            if (in_array($tipo, ['ventas', 'ambos'])) {
                try {
                    $res    = $sunat->getAllVentasPeriodo($token, $p);
                    $ventas = $res['registros'];
                    $fuente = $res['fuente'];
                    $ins = 0; $dup = 0;
                    foreach ($ventas as $v) {
                        $chk = $pdo->prepare("SELECT id FROM registro_ventas WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?");
                        $chk->execute([$empresaId, $v['tipo_comp'], $v['serie'], $v['correlativo']]);
                        if ($chk->fetch()) {
                            // Actualizar fuente si mejoró a declarado
                            if ($fuente === 'declarado') {
                                $pdo->prepare("UPDATE registro_ventas SET fuente='declarado', sync_at=NOW() WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?")
                                    ->execute([$empresaId, $v['tipo_comp'], $v['serie'], $v['correlativo']]);
                            }
                            $dup++; continue;
                        }
                        $pdo->prepare("
                            INSERT INTO registro_ventas
                                (empresa_id, periodo, id_sire, cod_car, tipo_comp, serie, correlativo,
                                 fecha_emision, cliente_tipo_doc, cliente_num_doc, cliente_nombre,
                                 moneda, tipo_cambio, base_imponible, igv, exonerado, inafecto,
                                 total, estado_sunat, fuente)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ")->execute([
                            $empresaId, $p, $v['id_sire'], $v['cod_car'],
                            $v['tipo_comp'], $v['serie'], $v['correlativo'],
                            $v['fecha_emision'], $v['cliente_tipo_doc'], $v['cliente_num_doc'],
                            $v['cliente_nombre'], $v['moneda'], $v['tipo_cambio'],
                            $v['base_imponible'], $v['igv'], $v['exonerado'], $v['inafecto'],
                            $v['total'], $v['estado_sunat'], $fuente,
                        ]);
                        $ins++;
                    }
                    $resultado['ventas'][$p] = [
                        'nuevos' => $ins, 'duplicados' => $dup,
                        'total'  => count($ventas), 'fuente' => $fuente,
                    ];
                } catch (Exception $e) {
                    $resultado['ventas'][$p] = ['error' => $e->getMessage()];
                }
            }

            // ── Compras ─────────────────────────────────────────────────────
            if (in_array($tipo, ['compras', 'ambos'])) {
                try {
                    $res     = $sunat->getAllComprasPeriodo($token, $empresa['ruc'], $p);
                    $compras = $res['registros'];
                    $fuente  = $res['fuente'];
                    $ins = 0; $dup = 0; $rellenados = 0;
                    foreach ($compras as $c) {
                        $chk = $pdo->prepare("SELECT id, proveedor_ruc FROM registro_compras WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?");
                        $chk->execute([$empresaId, $c['tipo_comp'], $c['serie'], $c['correlativo']]);
                        $existente = $chk->fetch(PDO::FETCH_ASSOC);
                        if ($existente) {
                            // Compras sincronizadas antes de leer bien el documento
                            // del proveedor quedaron con el RUC vacío — se completa
                            // aquí, sin tocar nada de lo ya clasificado.
                            if (($existente['proveedor_ruc'] ?? '') === '' && $c['proveedor_ruc'] !== '') {
                                $pdo->prepare("UPDATE registro_compras SET proveedor_ruc=?, proveedor_tipo_doc=?, proveedor_nombre=IF(proveedor_nombre IS NULL OR proveedor_nombre='', ?, proveedor_nombre) WHERE id=?")
                                    ->execute([$c['proveedor_ruc'], $c['proveedor_tipo_doc'], $c['proveedor_nombre'], $existente['id']]);
                                $rellenados++;
                            }
                            if ($fuente === 'declarado') {
                                $pdo->prepare("UPDATE registro_compras SET fuente='declarado', sync_at=NOW() WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?")
                                    ->execute([$empresaId, $c['tipo_comp'], $c['serie'], $c['correlativo']]);
                            }
                            $dup++; continue;
                        }
                        $pdo->prepare("
                            INSERT INTO registro_compras
                                (empresa_id, periodo, id_sire, cod_car, tipo_comp, serie, correlativo,
                                 fecha_emision, proveedor_tipo_doc, proveedor_ruc, proveedor_nombre,
                                 moneda, tipo_cambio, base_imponible, igv, exonerado, inafecto,
                                 total, estado_sunat, fuente)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                        ")->execute([
                            $empresaId, $p, $c['id_sire'], $c['cod_car'],
                            $c['tipo_comp'], $c['serie'], $c['correlativo'],
                            $c['fecha_emision'], $c['proveedor_tipo_doc'], $c['proveedor_ruc'],
                            $c['proveedor_nombre'], $c['moneda'], $c['tipo_cambio'],
                            $c['base_imponible'], $c['igv'], $c['exonerado'], $c['inafecto'],
                            $c['total'], $c['estado_sunat'], $fuente,
                        ]);
                        $ins++;
                    }
                    $resultado['compras'][$p] = [
                        'nuevos' => $ins, 'duplicados' => $dup, 'rellenados' => $rellenados,
                        'total'  => count($compras), 'fuente' => $fuente,
                    ];
                } catch (Exception $e) {
                    $resultado['compras'][$p] = ['error' => $e->getMessage()];
                }
            }
        }

        $_SESSION['sync_resultado'] = $resultado;
        header("Location: /empresas/{$empresaId}/sync?ok=1"); exit;
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
