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
        for ($i = 1; $i <= 12; $i++) $periodos[] = date('Ym', strtotime("first day of -{$i} month"));

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
        $periodo = $_POST['periodo'] ?? date('Ym', strtotime('first day of -1 month'));

        if ($rango === 'todo') {
            $periodos = array_map(fn($i) => date('Ym', strtotime("first day of -{$i} month")), range(1, 12));
        } elseif ($rango === 'anio') {
            // Año vigente desde enero hasta el mes anterior (el mes en curso
            // aún no está cerrado); en enero no hay mes anterior dentro del
            // año, así que solo entonces se incluye el mes actual.
            $anio = (int)date('Y');
            $hasta = max(1, (int)date('n') - 1);
            $periodos = array_map(fn($m) => sprintf('%04d%02d', $anio, $m), range(1, $hasta));
        } else {
            $periodos = [$periodo];
        }
        // Varios meses = varias consultas a SUNAT por página; se da más margen
        // que el límite por defecto para que no se corte a la mitad.
        if (count($periodos) > 1) set_time_limit(600);

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
                        'total_sunat' => $res['total_sunat'], 'paginas' => $res['paginas'], 'aviso' => $res['error'],
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
                        // La clave única de la tabla incluye al proveedor: dos proveedores
                        // distintos pueden emitir el mismo F001-123. Sin el RUC en la búsqueda
                        // el segundo se descartaba como "ya existía" y la compra se perdía.
                        // Un registro viejo con RUC vacío también cuenta (se rellena abajo).
                        $chk = $pdo->prepare("
                            SELECT id, proveedor_ruc FROM registro_compras
                            WHERE empresa_id=? AND tipo_comp=? AND serie=? AND correlativo=?
                              AND (proveedor_ruc = ? OR proveedor_ruc = '' OR proveedor_ruc IS NULL)
                            ORDER BY (proveedor_ruc = ?) DESC LIMIT 1
                        ");
                        $chk->execute([$empresaId, $c['tipo_comp'], $c['serie'], $c['correlativo'], $c['proveedor_ruc'], $c['proveedor_ruc']]);
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
                                $pdo->prepare("UPDATE registro_compras SET fuente='declarado', sync_at=NOW() WHERE id=?")
                                    ->execute([$existente['id']]);
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
                        'total_sunat' => $res['total_sunat'], 'paginas' => $res['paginas'], 'aviso' => $res['error'],
                    ];
                } catch (Exception $e) {
                    $resultado['compras'][$p] = ['error' => $e->getMessage()];
                }
            }
        }

        $_SESSION['sync_resultado'] = $resultado;
        header("Location: /empresas/{$empresaId}/sync?ok=1"); exit;
    }

    /**
     * Diagnóstico (solo superadmin): muestra qué devuelve SUNAT de verdad para
     * un período y lo compara con lo guardado, para saber si faltan datos y
     * por qué. Uso: /empresas/{id}/sync/diagnostico?periodo=202601&tipo=ventas
     * Opcional: &f621=91999 (base declarada) para ver la diferencia.
     */
    public function diagnostico(int $empresaId): void
    {
        Auth::require();
        if (!Auth::isSuperadmin()) { http_response_code(403); die('Solo superadmin'); }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        header('Content-Type: text/plain; charset=utf-8');
        set_time_limit(300);
        $periodo = preg_match('/^\d{6}$/', $_GET['periodo'] ?? '') ? $_GET['periodo'] : date('Ym', strtotime('first day of -1 month'));
        $tipo    = ($_GET['tipo'] ?? 'ventas') === 'compras' ? 'compras' : 'ventas';
        $f621    = isset($_GET['f621']) ? (float)$_GET['f621'] : null;
        $pdo     = Model::db();

        $stmt = $pdo->prepare("SELECT sol_usuario, sol_clave, api_client_id, api_client_secret FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute([$empresaId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert || empty($cert['api_client_id'])) die('Sin credenciales API configuradas.');

        $enc = new EncryptService();
        $sunat = new SunatApiService();
        $token = $sunat->getToken($enc->decrypt($cert['api_client_id']), $enc->decrypt($cert['api_client_secret']),
            $empresa['ruc'], $enc->decrypt($cert['sol_usuario']), $enc->decrypt($cert['sol_clave']));

        echo "DIAGNÓSTICO SIRE — {$empresa['razon_social']} — {$tipo} {$periodo}\n", str_repeat('=', 70), "\n\n";

        foreach (['declarado', 'propuesta'] as $fuente) {
            echo "── Fuente: {$fuente} ──\n";
            try {
                $r = $sunat->paginaCruda($token, $tipo, $empresa['ruc'], $periodo, $fuente, 1);
            } catch (Throwable $e) { echo "  ERROR: ", $e->getMessage(), "\n\n"; continue; }
            echo "  Claves de la respuesta : ", implode(', ', array_keys($r)), "\n";
            echo "  paginacion             : ", json_encode($r['paginacion'] ?? null), "\n";
            echo "  registros en pág. 1    : ", count($r['registros'] ?? []), "\n";
            if (!empty($r['registros'][0])) echo "  1er registro (crudo)   : ", json_encode($r['registros'][0], JSON_UNESCAPED_UNICODE), "\n";
            $totalSunat = (int)($r['paginacion']['totalRegistros'] ?? 0);
            if ($totalSunat === 0) { echo "  (sin registros en esta fuente)\n\n"; continue; }

            // Recorrer todas las páginas acumulando
            $acumular = function (array &$grupo, string $clave, float $b, float $i, float $t): void {
                $grupo[$clave] ??= ['n' => 0, 'base' => 0.0, 'igv' => 0.0, 'total' => 0.0];
                $grupo[$clave]['n']++; $grupo[$clave]['base'] += $b; $grupo[$clave]['igv'] += $i; $grupo[$clave]['total'] += $t;
            };
            $porTipo = []; $porEstado = []; $porDia = []; $porPer = []; $series = []; $claves = []; $filas = 0; $sumB = $sumI = $sumT = 0.0; $rangos = 0;
            for ($pg = 1; $pg <= 200; $pg++) {
                try { $resp = $pg === 1 ? $r : $sunat->paginaCruda($token, $tipo, $empresa['ruc'], $periodo, $fuente, $pg); }
                catch (Throwable $e) { echo "  Página {$pg}: ERROR ", $e->getMessage(), "\n"; break; }
                $regs = $resp['registros'] ?? [];
                echo "  Página {$pg}: ", count($regs), " registros\n";
                if (!$regs) break;
                foreach ($regs as $x) {
                    $filas++;
                    $tp = (string)($x['codTipoCDP'] ?? $x['codTipoComprobante'] ?? '?');
                    $es = (string)($x['codEstadoComprobante'] ?? '?');
                    $b = (float)($x['mtoBIGravada'] ?? ($x['montos']['mtoBIGravadaDG'] ?? 0));
                    $i = (float)($x['mtoIGV'] ?? ($x['montos']['mtoIgvIpmDG'] ?? 0));
                    $t = (float)($x['mtoTotalCP'] ?? ($x['montos']['mtoTotalCp'] ?? 0));
                    $sumB += $b; $sumI += $i; $sumT += $t;
                    $acumular($porTipo, $tp, $b, $i, $t);
                    $acumular($porEstado, $es, $b, $i, $t);
                    $fe = (string)($x['fecEmision'] ?? $x['fecEmisionCP'] ?? '?');
                    $pt = (string)($x['perPeriodoTributario'] ?? '?');
                    $porDia[$fe] = ($porDia[$fe] ?? 0) + 1;
                    $porPer[$pt] = ($porPer[$pt] ?? 0) + 1;
                    $sk = $tp . '-' . (string)($x['numSerieCDP'] ?? $x['numSerie'] ?? '?');
                    $cn = (int)($x['numCDP'] ?? $x['numCdp'] ?? 0);
                    if (!isset($series[$sk])) $series[$sk] = ['n' => 0, 'min' => $cn, 'max' => $cn, 'f_min' => $fe, 'f_max' => $fe, 'nums' => []];
                    $series[$sk]['n']++; $series[$sk]['min'] = min($series[$sk]['min'], $cn); $series[$sk]['max'] = max($series[$sk]['max'], $cn);
                    $series[$sk]['nums'][$cn] = true;
                    foreach (array_keys($x) as $kk) $claves[$kk] = true;
                    foreach ($x as $kk => $vv) if (preg_match('/final|rango|hasta/i', (string)$kk) && $vv !== '' && $vv !== null) { $rangos++; break; }
                }
                if ($filas >= $totalSunat) break;
            }
            echo "\n  Filas leídas: {$filas} de {$totalSunat} que informa SUNAT\n";
            echo "  Filas con campo de rango/final (boletas agrupadas): {$rangos}\n";
            echo sprintf("  SUMA  base=%.2f  igv=%.2f  total=%.2f\n", $sumB, $sumI, $sumT);
            echo "  Por tipo de comprobante:\n";
            foreach ($porTipo as $k => $a) echo sprintf("    %-3s n=%-6d base=%12.2f igv=%11.2f total=%12.2f\n", $k, $a['n'], $a['base'], $a['igv'], $a['total']);
            echo "  Por estado:\n";
            foreach ($porEstado as $k => $a) echo sprintf("    %-3s n=%-6d base=%12.2f igv=%11.2f total=%12.2f\n", $k, $a['n'], $a['base'], $a['igv'], $a['total']);
            echo "  Campos de cada registro: ", implode(', ', array_keys($claves)), "\n";
            // Distribución por fecha de emisión (dd/mm/aaaa o aaaa-mm-dd → ordenar por fecha real)
            $orden = fn($f) => preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $f, $m) ? "{$m[3]}-{$m[2]}-{$m[1]}" : $f;
            uksort($porDia, fn($a, $b) => strcmp($orden($a), $orden($b)));
            $dias = array_keys($porDia);
            echo "\n  Fechas de emisión: ", count($dias), " días distintos · primera=", $dias[0] ?? '-', " · última=", end($dias) ?: '-', "\n";
            echo "  Primeros 5 días: ", implode(' | ', array_map(fn($d) => "$d:{$porDia[$d]}", array_slice($dias, 0, 5))), "\n";
            echo "  Últimos 5 días : ", implode(' | ', array_map(fn($d) => "$d:{$porDia[$d]}", array_slice($dias, -5))), "\n";
            echo "  Por período tributario (perPeriodoTributario) — si hay más de uno, SUNAT mezcla períodos:\n";
            foreach ($porPer as $k => $n) echo "    {$k}: {$n}\n";
            echo "  Series y saltos de correlativo (faltantes = máx - mín + 1 - cantidad):\n";
            foreach ($series as $k => $a) echo sprintf("    %-8s n=%-6d correlativo %d → %d  faltantes=%d\n", $k, $a['n'], $a['min'], $a['max'], $a['max'] - $a['min'] + 1 - count($a['nums']));
            if ($f621 !== null && $tipo === 'ventas') echo sprintf("  F621 base declarada=%.2f  → diferencia contra SUNAT(base)=%.2f\n", $f621, $f621 - $sumB);
            echo "\n";
        }

        $tabla = $tipo === 'ventas' ? 'registro_ventas' : 'registro_compras';
        $st = $pdo->prepare("SELECT COUNT(*) n, COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(igv),0) igv, COALESCE(SUM(total),0) total FROM {$tabla} WHERE empresa_id = ? AND periodo = ?");
        $st->execute([$empresaId, $periodo]);
        $db = $st->fetch(PDO::FETCH_ASSOC);
        echo "── Guardado en GestiCont ({$tabla}) ──\n";
        echo sprintf("  n=%d base=%.2f igv=%.2f total=%.2f\n", $db['n'], $db['base'], $db['igv'], $db['total']);
        $st = $pdo->prepare("SELECT tipo_comp, estado_sunat, COUNT(*) n, SUM(base_imponible) base FROM {$tabla} WHERE empresa_id = ? AND periodo = ? GROUP BY tipo_comp, estado_sunat");
        $st->execute([$empresaId, $periodo]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) echo sprintf("    tipo=%s estado=%s n=%d base=%.2f\n", $x['tipo_comp'], $x['estado_sunat'], $x['n'], $x['base']);
        $st = $pdo->prepare("SELECT MIN(fecha_emision) f1, MAX(fecha_emision) f2, COUNT(DISTINCT fecha_emision) dias FROM {$tabla} WHERE empresa_id = ? AND periodo = ?");
        $st->execute([$empresaId, $periodo]);
        $x = $st->fetch(PDO::FETCH_ASSOC);
        echo "  Fechas guardadas: {$x['f1']} → {$x['f2']} ({$x['dias']} días distintos)\n";
        $st = $pdo->prepare("SELECT tipo_comp, serie, COUNT(*) n, MIN(correlativo) mn, MAX(correlativo) mx FROM {$tabla} WHERE empresa_id = ? AND periodo = ? GROUP BY tipo_comp, serie");
        $st->execute([$empresaId, $periodo]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $x) echo sprintf("    %s-%-6s n=%-6d correlativo %d → %d  faltantes=%d\n", $x['tipo_comp'], $x['serie'], $x['n'], $x['mn'], $x['mx'], $x['mx'] - $x['mn'] + 1 - $x['n']);
        exit;
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
