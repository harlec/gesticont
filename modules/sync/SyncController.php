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

        $resultadoSync = $this->_resultadoLeer($empresaId, true);

        $pageTitle = 'Sincronizar SIRE — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/sync/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    /** Períodos a sincronizar según lo elegido en el formulario. */
    private function _periodosDe(array $in): array
    {
        $rango   = $in['rango']   ?? 'periodo';
        $periodo = $in['periodo'] ?? date('Ym', strtotime('first day of -1 month'));
        if ($rango === 'todo') {
            return array_map(fn($i) => date('Ym', strtotime("first day of -{$i} month")), range(1, 12));
        }
        if ($rango === 'anio') {
            // Año vigente desde enero hasta el mes anterior (el mes en curso
            // aún no está cerrado); en enero no hay mes anterior dentro del
            // año, así que solo entonces se incluye el mes actual.
            $anio  = (int)date('Y');
            $hasta = max(1, (int)date('n') - 1);
            return array_map(fn($m) => sprintf('%04d%02d', $anio, $m), range(1, $hasta));
        }
        return [preg_match('/^\d{6}$/', $periodo) ? $periodo : date('Ym', strtotime('first day of -1 month'))];
    }

    /* ---- Estado de la sincronización fuera de la sesión ----------------
     * PHP bloquea el archivo de sesión mientras dura CADA petición. Una
     * sincronización larga (o una que el navegador ya abandonó tras un 504
     * pero el servidor sigue procesando) dejaba esperando a cualquier otra
     * petición del mismo usuario hasta que terminara — y ese bloqueo era
     * otro 504. Por eso el token y los resultados van a archivos temporales
     * y la sesión se suelta (session_write_close) apenas se valida el acceso. */

    private function _dirSync(): string
    {
        $dir = sys_get_temp_dir() . '/gesticont_sync';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        return $dir;
    }

    private function _resultadoArchivo(int $empresaId): string
    {
        return sprintf('%s/res_%d_%d.json', $this->_dirSync(), $empresaId, Auth::id());
    }

    private function _resultadoLeer(int $empresaId, bool $consumir = false): ?array
    {
        $f = $this->_resultadoArchivo($empresaId);
        if (!is_file($f)) return null;
        $r = json_decode((string)file_get_contents($f), true) ?: null;
        if ($consumir) @unlink($f);
        return $r;
    }

    private function _resultadoGuardar(int $empresaId, ?string $tipo, ?string $periodo, ?array $dato): void
    {
        $f = $this->_resultadoArchivo($empresaId);
        $fp = fopen($f, 'c+'); flock($fp, LOCK_EX);
        $r = json_decode((string)stream_get_contents($fp), true) ?: ['ventas' => [], 'compras' => []];
        if ($tipo === null) $r = ['ventas' => [], 'compras' => []];      // reinicio
        else $r[$tipo][$periodo] = $dato;
        ftruncate($fp, 0); rewind($fp); fwrite($fp, json_encode($r)); fflush($fp); flock($fp, LOCK_UN); fclose($fp);
    }

    /**
     * Cliente SIRE + token. El token dura ~1 h en SUNAT; se guarda en un
     * archivo temporal para no pedir uno nuevo en cada paso. $renovar lo
     * descarta (p. ej. tras un 401).
     */
    private function _sire(int $empresaId, array $empresa, bool $renovar = false): array
    {
        $sunat = new SunatApiService();
        $sunat->timeout = 22;   // fallback declarado→propuesta = hasta 2 llamadas: 44 s < 60 s de nginx
        $archivo = sprintf('%s/tok_%d_%d.json', $this->_dirSync(), $empresaId, Auth::id());
        if (!$renovar && is_file($archivo)) {
            $c = json_decode((string)file_get_contents($archivo), true);
            if ($c && $c['exp'] > time()) return [$sunat, $c['token']];
        }

        $stmt = Model::db()->prepare("
            SELECT sol_usuario, sol_clave, api_client_id, api_client_secret
            FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1
        ");
        $stmt->execute([$empresaId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cert || empty($cert['api_client_id'])) throw new RuntimeException('Sin credenciales API configuradas.');

        $enc   = new EncryptService();
        $token = $sunat->getToken(
            $enc->decrypt($cert['api_client_id']), $enc->decrypt($cert['api_client_secret']),
            $empresa['ruc'], $enc->decrypt($cert['sol_usuario']), $enc->decrypt($cert['sol_clave'])
        );
        file_put_contents($archivo, json_encode(['token' => $token, 'exp' => time() + 1500]), LOCK_EX);
        @chmod($archivo, 0600);
        return [$sunat, $token];
    }

    /** Guarda ventas ya descargadas; devuelve [nuevos, duplicados]. */
    private function _guardarVentas(PDO $pdo, int $empresaId, string $p, string $fuente, array $ventas): array
    {
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
        return [$ins, $dup];
    }

    /** Guarda compras ya descargadas; devuelve [nuevos, duplicados, rellenados]. */
    private function _guardarCompras(PDO $pdo, int $empresaId, string $p, string $fuente, array $compras): array
    {
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
        return [$ins, $dup, $rellenados];
    }

    /**
     * Ruta antigua que hacía TODO en una sola petición. Se desactivó: con miles
     * de comprobantes superaba el tiempo de nginx (504) y, como nginx corta al
     * cliente pero PHP sigue trabajando, cada intento dejaba procesos ocupando
     * el servidor hasta 10 minutos. Ahora solo devuelve a la pantalla, que usa
     * la sincronización por partes (plan/paso).
     */
    public function ejecutar(int $empresaId): void
    {
        Auth::require();
        $_SESSION['sync_error'] = 'Actualiza la página (Ctrl+F5) y vuelve a pulsar "Sincronizar ahora": la sincronización ahora se hace por partes, con barra de progreso.';
        header("Location: /empresas/{$empresaId}/sync"); exit;
    }

    /* ===================================================================
     * Sincronización POR PARTES (JSON) — la usa la pantalla con barra de
     * progreso. Cada petición hace poco trabajo (una página de SUNAT, o
     * 300 filas a la base) para no pasar el tiempo máximo del servidor
     * (504 Gateway Time-out) en empresas con miles de comprobantes.
     * Lo descargado se deja en un archivo temporal entre pasos.
     * =================================================================== */

    private const LOTE_GUARDADO = 300;
    private float $_t0 = 0.0;   // inicio del paso en curso (para medir cuánto tarda)
    private float $_msSunat = 0.0;

    private function _json(array $data, int $code = 200): void
    {
        if ($this->_t0 > 0) { $data['ms'] = (int)((microtime(true) - $this->_t0) * 1000); $data['ms_sunat'] = (int)$this->_msSunat; }
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function _archivoTemporal(int $empresaId, string $tipo, string $periodo): string
    {
        return sprintf('%s/%d_%d_%s_%s.json', $this->_dirSync(), $empresaId, Auth::id(), $tipo, $periodo);
    }

    /** POST: devuelve la lista de tareas (tipo + período) que el navegador irá ejecutando. */
    public function plan(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_json(['ok' => false, 'error' => 'Método no permitido'], 405);
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) $this->_json(['ok' => false, 'error' => 'Sin acceso'], 403);

        // Solo se comprueba que haya credenciales (consulta rápida a la base). El token de
        // SUNAT se pide recién en el primer paso: así este plan responde siempre al instante.
        $stmt = Model::db()->prepare("SELECT api_client_id FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1");
        $stmt->execute([$empresaId]);
        if (!$stmt->fetchColumn()) $this->_json(['ok' => false, 'error' => 'Sin credenciales API configuradas.']);
        session_write_close();

        $tipo = $_POST['tipo'] ?? 'ambos';
        $tareas = [];
        foreach ($this->_periodosDe($_POST) as $p) {
            foreach (['ventas', 'compras'] as $t) if (in_array($tipo, [$t, 'ambos'])) $tareas[] = ['tipo' => $t, 'periodo' => $p];
        }
        $this->_resultadoGuardar($empresaId, null, null, null);
        $this->_json(['ok' => true, 'tareas' => $tareas]);
    }

    /**
     * POST fase=descargar&tipo&periodo&page  → baja UNA página de SUNAT y la suma a lo ya bajado.
     * POST fase=guardar&tipo&periodo&offset  → guarda un lote de filas en la base.
     */
    public function paso(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->_json(['ok' => false, 'error' => 'Método no permitido'], 405);
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) $this->_json(['ok' => false, 'error' => 'Sin acceso'], 403);

        $tipo    = ($_POST['tipo'] ?? '') === 'compras' ? 'compras' : 'ventas';
        $periodo = $_POST['periodo'] ?? '';
        if (!preg_match('/^\d{6}$/', $periodo)) $this->_json(['ok' => false, 'error' => 'Período inválido']);
        $fase    = $_POST['fase'] ?? '';
        $archivo = $this->_archivoTemporal($empresaId, $tipo, $periodo);
        // Por debajo del corte de nginx (60 s): un paso trabado muere solo en vez de quedarse ocupando PHP.
        set_time_limit(50);
        session_write_close();
        $this->_t0 = microtime(true);

        try {
            $fase === 'descargar'
                ? $this->_pasoDescargar($empresaId, $empresa, $tipo, $periodo, max(1, (int)($_POST['page'] ?? 1)), $archivo)
                : $this->_pasoGuardar($empresaId, $tipo, $periodo, max(0, (int)($_POST['offset'] ?? 0)), $archivo);
        } catch (Throwable $e) {
            $this->_resultadoGuardar($empresaId, $tipo, $periodo, ['error' => $e->getMessage()]);
            @unlink($archivo);
            $this->_json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function _pasoDescargar(int $empresaId, array $empresa, string $tipo, string $periodo, int $page, string $archivo): void
    {
        $estado = ['perPage' => 100, 'fuente' => null, 'total' => 0, 'unicos' => [], 'guardado' => 0, 'ins' => 0, 'dup' => 0, 'rell' => 0];
        if ($page > 1 && is_file($archivo)) $estado = json_decode(file_get_contents($archivo), true) ?: $estado;
        if ($page === 1) $estado['unicos'] = [];

        $pedir = function (bool $renovar) use ($empresaId, $empresa, $tipo, $periodo, $page, $estado) {
            [$sunat, $token] = $this->_sire($empresaId, $empresa, $renovar);
            return $tipo === 'ventas'
                ? $sunat->getVentasPeriodo($token, $periodo, $page, $estado['perPage'], $estado['fuente'])
                : $sunat->getComprasPeriodo($token, $empresa['ruc'], $periodo, $page, $estado['perPage'], $estado['fuente']);
        };
        $ini = microtime(true);
        $r = $pedir(false);
        if (!empty($r['error']) && str_contains($r['error'], 'HTTP 401')) $r = $pedir(true);   // token vencido
        $this->_msSunat = (microtime(true) - $ini) * 1000;

        // SUNAT rechazó el tamaño de página grande: se repite la página 1 con 100.
        if ($page === 1 && (!empty($r['error']) || $r['fuente'] === 'sin_datos') && $estado['perPage'] > 100) {
            $estado['perPage'] = 100;
            file_put_contents($archivo, json_encode($estado));
            $this->_json(['ok' => true, 'reintentar' => true, 'siguiente' => 1, 'unicos' => 0, 'total' => 0]);
        }
        if ($page === 1) { $estado['fuente'] = $r['fuente']; $estado['total'] = $r['total']; }
        if (!empty($r['error'])) throw new RuntimeException("Página {$page}: " . $r['error']);

        // La API de SIRE solapa las páginas (la N trae N×perPage filas): se cuentan comprobantes ÚNICOS.
        $nuevos = 0;
        foreach ($r['registros'] as $reg) {
            $k = $reg['cod_car'] ?: ($reg['id_sire'] ?: implode('|', [$reg['tipo_comp'], $reg['serie'], $reg['correlativo'], $reg['proveedor_ruc'] ?? '']));
            if (!isset($estado['unicos'][$k])) { $estado['unicos'][$k] = $reg; $nuevos++; }
        }
        $unicos   = count($estado['unicos']);
        $listo    = $unicos >= $estado['total'] || $nuevos === 0 || $page >= 2000;
        $estado['paginas'] = $page;
        file_put_contents($archivo, json_encode($estado));

        $aviso = null;
        if ($listo && $unicos < $estado['total']) {
            $aviso = "SUNAT informa {$estado['total']} comprobantes pero solo se recibieron {$unicos} distintos (páginas leídas: {$page}).";
            error_log("[SIRE {$tipo} {$periodo}] INCOMPLETO — {$aviso}");
        }
        $this->_json([
            'ok' => true, 'unicos' => $unicos, 'total' => $estado['total'],
            'siguiente' => $listo ? null : $page + 1, 'aviso' => $aviso, 'fuente' => $estado['fuente'],
            'recibidas' => count($r['registros']), 'pagina' => $page,
        ]);
    }

    private function _pasoGuardar(int $empresaId, string $tipo, string $periodo, int $offset, string $archivo): void
    {
        if (!is_file($archivo)) throw new RuntimeException('Se perdió lo descargado de este período; vuelve a sincronizarlo.');
        $estado = json_decode(file_get_contents($archivo), true);
        $filas  = array_values($estado['unicos']);
        $total  = count($filas);
        $fuente = $estado['fuente'] ?? 'propuesta';

        // Si el navegador repite un lote ya guardado (reintento tras un corte), no se cuenta dos veces.
        if ($offset >= $estado['guardado']) {
            $pdo = Model::db();
            $lote = array_slice($filas, $offset, self::LOTE_GUARDADO);
            $pdo->beginTransaction();
            try {
                [$ins, $dup, $rell] = $tipo === 'ventas'
                    ? [...$this->_guardarVentas($pdo, $empresaId, $periodo, $fuente, $lote), 0]
                    : $this->_guardarCompras($pdo, $empresaId, $periodo, $fuente, $lote);
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
            $estado['ins'] += $ins; $estado['dup'] += $dup; $estado['rell'] += $rell;
            $estado['guardado'] = $offset + count($lote);
            file_put_contents($archivo, json_encode($estado));
        }

        $siguiente = $offset + self::LOTE_GUARDADO;
        if ($siguiente < $total) $this->_json(['ok' => true, 'guardados' => min($estado['guardado'], $total), 'total' => $total, 'siguiente' => $siguiente]);

        // Último lote: se deja el resumen en sesión (lo muestra la pantalla al recargar).
        $aviso = null;
        if ($total < ($estado['total'] ?? 0)) $aviso = "SUNAT informa {$estado['total']} comprobantes pero solo se recibieron {$total} distintos (páginas leídas: " . ($estado['paginas'] ?? '?') . ").";
        $this->_resultadoGuardar($empresaId, $tipo, $periodo, [
            'nuevos' => $estado['ins'], 'duplicados' => $estado['dup'], 'rellenados' => $estado['rell'],
            'total' => $total, 'fuente' => $fuente,
            'total_sunat' => $estado['total'] ?? 0, 'paginas' => $estado['paginas'] ?? 0, 'aviso' => $aviso,
        ]);
        @unlink($archivo);
        $this->_json(['ok' => true, 'fin' => true, 'guardados' => $total, 'total' => $total, 'nuevos' => $estado['ins']]);
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
            $porTipo = []; $porEstado = []; $vistos = []; $porDia = []; $porPer = []; $series = []; $claves = []; $filas = 0; $sumB = $sumI = $sumT = 0.0; $rangos = 0;
            for ($pg = 1; $pg <= 200; $pg++) {
                try { $resp = $pg === 1 ? $r : $sunat->paginaCruda($token, $tipo, $empresa['ruc'], $periodo, $fuente, $pg); }
                catch (Throwable $e) { echo "  Página {$pg}: ERROR ", $e->getMessage(), "\n"; break; }
                $regs = $resp['registros'] ?? [];
                $antes = $filas;
                if (!$regs) { echo "  Página {$pg}: 0 registros\n"; break; }
                foreach ($regs as $x) {
                    $idu = (string)($x['codCar'] ?? $x['id'] ?? '');
                    if ($idu !== '' && isset($vistos[$idu])) continue;   // las páginas de SIRE se solapan
                    $vistos[$idu] = true;
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
                echo "  Página {$pg}: ", count($regs), " filas recibidas, ", $filas - $antes, " nuevas (únicas acumuladas: {$filas})\n";
                if ($filas >= $totalSunat || $filas === $antes) break;
            }
            echo "\n  Comprobantes ÚNICOS leídos: {$filas} de {$totalSunat} que informa SUNAT\n";
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
