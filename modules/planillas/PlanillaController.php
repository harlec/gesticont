<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/PlameR01Parser.php';

class PlanillaController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo     = Model::db();
        $periodo = Periodo::resolver($empresaId);

        $stmt = $pdo->prepare("
            SELECT * FROM planillas WHERE empresa_id = ? AND periodo = ? ORDER BY trabajador
        ");
        $stmt->execute([$empresaId, $periodo]);
        $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totales = ['bruto' => 0, 'essalud' => 0, 'retencion' => 0, 'neto' => 0];
        foreach ($registros as $r) {
            $bruto = (float)$r['sueldo'] + (float)$r['gratificacion'] + (float)$r['asignacion_familiar'];
            $totales['bruto']     += $bruto;
            $totales['essalud']   += (float)$r['essalud'];
            $totales['retencion'] += (float)$r['retencion_pension'];
            $totales['neto']      += $bruto - (float)$r['retencion_pension'];
        }

        $pageTitle = 'Planillas — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/planillas/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function store(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo   = $_POST['periodo'] ?? date('Ym');
        $trabajador = trim($_POST['trabajador'] ?? '');
        $sueldo     = (float)($_POST['sueldo'] ?? 0);
        $gratif     = (float)($_POST['gratificacion'] ?? 0);
        $asigFam    = (float)($_POST['asignacion_familiar'] ?? 0);
        $essalud    = (float)($_POST['essalud'] ?? 0);
        $regimen    = $_POST['regimen_pension'] ?? 'onp';
        $retencion  = (float)($_POST['retencion_pension'] ?? 0);

        if (empty($trabajador) || !preg_match('/^\d{6}$/', $periodo) || !in_array($regimen, ['onp', 'afp', 'ninguno'], true)) {
            $_SESSION['planilla_error'] = 'Datos incompletos o período inválido.';
            header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
        }

        Model::db()->prepare("
            INSERT INTO planillas
                (empresa_id, periodo, trabajador, sueldo, gratificacion, asignacion_familiar,
                 essalud, regimen_pension, retencion_pension, origen, usuario_id)
            VALUES (?,?,?,?,?,?,?,?,?, 'manual', ?)
        ")->execute([
            $empresaId, $periodo, $trabajador, $sueldo, $gratif, $asigFam,
            $essalud, $regimen, $retencion, Auth::id(),
        ]);

        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}&ok=1"); exit;
    }

    /**
     * Plantilla CSV descargable — no es un parser del archivo real de
     * PLAME/T-Registro (no se pudo verificar ese formato con confianza,
     * ver conversación), es una plantilla propia para carga masiva segura.
     */
    public function plantillaCsv(int $empresaId): void
    {
        // Mismo motivo que en clasificarLote(): con APP_DEBUG=true, cualquier
        // warning/notice se imprimiría antes del CSV y lo corrompería.
        ob_start();
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { ob_end_clean(); http_response_code(403); die('Sin acceso'); }

        ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_planillas.csv"');
        echo "trabajador,sueldo,gratificacion,asignacion_familiar,essalud,regimen_pension,retencion_pension\n";
        echo "Juan Perez Garcia,1500.00,0.00,0.00,135.00,ONP,195.00\n";
        echo "Maria Lopez Diaz,2000.00,0.00,102.50,189.23,AFP,260.00\n";
    }

    public function importarCsv(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_POST['periodo'] ?? date('Ym');

        if (empty($_FILES['archivo']['tmp_name']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['planilla_error'] = 'No se pudo leer el archivo subido.';
            header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
        }

        $handle = fopen($_FILES['archivo']['tmp_name'], 'r');
        if (!$handle) {
            $_SESSION['planilla_error'] = 'No se pudo abrir el archivo.';
            header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
        }

        $regimenMap = ['onp' => 'onp', 'afp' => 'afp', 'ninguno' => 'ninguno', '' => 'ninguno'];
        $fila = 0; $insertados = 0; $errores = [];
        $pdo = Model::db();

        Model::beginTransaction();
        try {
            while (($datos = fgetcsv($handle)) !== false) {
                $fila++;
                if ($fila === 1) continue; // encabezado
                if (count(array_filter($datos, fn($v) => trim((string)$v) !== '')) === 0) continue; // línea vacía

                if (count($datos) < 7) { $errores[] = "Fila {$fila}: faltan columnas."; continue; }
                [$trabajador, $sueldo, $gratif, $asigFam, $essalud, $regimenTxt, $retencion] = array_map('trim', $datos);

                if ($trabajador === '') { $errores[] = "Fila {$fila}: falta el nombre del trabajador."; continue; }
                $regimen = $regimenMap[strtolower($regimenTxt)] ?? null;
                if ($regimen === null) { $errores[] = "Fila {$fila}: régimen '{$regimenTxt}' inválido (usar ONP, AFP o Ninguno)."; continue; }
                if (!is_numeric($sueldo) || !is_numeric($gratif) || !is_numeric($asigFam) || !is_numeric($essalud) || !is_numeric($retencion)) {
                    $errores[] = "Fila {$fila}: algún monto no es numérico.";
                    continue;
                }

                $pdo->prepare("
                    INSERT INTO planillas
                        (empresa_id, periodo, trabajador, sueldo, gratificacion, asignacion_familiar,
                         essalud, regimen_pension, retencion_pension, origen, usuario_id)
                    VALUES (?,?,?,?,?,?,?,?,?, 'manual', ?)
                ")->execute([
                    $empresaId, $periodo, $trabajador, (float)$sueldo, (float)$gratif, (float)$asigFam,
                    (float)$essalud, $regimen, (float)$retencion, Auth::id(),
                ]);
                $insertados++;
            }
            Model::commit();
        } catch (Exception $e) {
            Model::rollback();
            fclose($handle);
            $_SESSION['planilla_error'] = 'Error al importar: ' . $e->getMessage();
            header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
        }
        fclose($handle);

        if ($insertados > 0) {
            $_SESSION['planilla_ok'] = "{$insertados} trabajador(es) importado(s)." . (count($errores) ? ' ' . count($errores) . ' fila(s) con error, omitidas.' : '');
        }
        if (!empty($errores)) {
            $_SESSION['planilla_error'] = implode(' | ', array_slice($errores, 0, 10)) . (count($errores) > 10 ? ' …' : '');
        }

        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
    }

    /**
     * Importa uno o varios reportes R01 de PLAME (XML). El período y el RUC
     * salen del propio archivo — el RUC debe ser el de esta empresa, para no
     * cargar la planilla de otra por error. Volver a importar un mes
     * reemplaza lo que se había importado de PLAME para ese mes (las filas
     * ingresadas a mano no se tocan).
     */
    public function importarPlame(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $archivos = $_FILES['archivos'] ?? null;
        $tmp = is_array($archivos['tmp_name'] ?? null) ? $archivos['tmp_name'] : [];
        if (empty(array_filter($tmp))) {
            $_SESSION['planilla_error'] = 'No se recibió ningún archivo.';
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }

        $pdo = Model::db();
        $oks = []; $errores = []; $periodos = [];

        foreach ($tmp as $i => $ruta) {
            $nombre = $archivos['name'][$i] ?? "archivo {$i}";
            if (($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($ruta) && !is_file($ruta)) {
                $errores[] = "{$nombre}: no se pudo leer."; continue;
            }
            if (filesize($ruta) > 2 * 1024 * 1024) { $errores[] = "{$nombre}: pesa más de 2 MB."; continue; }

            try {
                $r = PlameR01Parser::parsear((string)file_get_contents($ruta));
            } catch (RuntimeException $e) {
                $errores[] = "{$nombre}: " . $e->getMessage() . '.'; continue;
            }

            if ($r['ruc'] !== $empresa['ruc']) {
                $errores[] = "{$nombre}: es de otra empresa (RUC {$r['ruc']}, esta empresa es {$empresa['ruc']}).";
                continue;
            }
            if (empty($r['trabajadores'])) { $errores[] = "{$nombre}: no trae trabajadores."; continue; }

            Model::beginTransaction();
            try {
                $stmtDel = $pdo->prepare("DELETE FROM planillas WHERE empresa_id = ? AND periodo = ? AND origen = 'import_plame'");
                $stmtDel->execute([$empresaId, $r['periodo']]);
                $reemplazados = $stmtDel->rowCount();

                $stmtIns = $pdo->prepare("
                    INSERT INTO planillas
                        (empresa_id, periodo, trabajador, sueldo, gratificacion, asignacion_familiar,
                         essalud, regimen_pension, retencion_pension, origen, usuario_id)
                    VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, 'import_plame', ?)
                ");
                $cant = 0; $bruto = 0.0; $onp = 0; $afp = 0;
                foreach ($r['trabajadores'] as $t) {
                    if ($t['devengado'] <= 0 && $t['retencion'] <= 0) continue;
                    $regimen = PlameR01Parser::inferirRegimen($t['retencion']);
                    $stmtIns->execute([$empresaId, $r['periodo'], $t['nombre'], $t['devengado'], $t['essalud'], $regimen, $t['retencion'], Auth::id()]);
                    $cant++; $bruto += $t['devengado'];
                    if ($regimen === 'onp') $onp++; elseif ($regimen === 'afp') $afp++;
                }
                Model::commit();
            } catch (Exception $e) {
                Model::rollback();
                $errores[] = "{$nombre}: error al guardar ({$e->getMessage()}).";
                continue;
            }

            $periodos[] = $r['periodo'];
            $oks[] = sprintf('%s: %d trabajador(es), bruto S/ %s (%d ONP / %d AFP estimados)%s.',
                Periodo::etiqueta($r['periodo']), $cant, number_format($bruto, 2), $onp, $afp,
                $reemplazados ? ", reemplazó {$reemplazados} registro(s) previos de PLAME" : '');
        }

        if ($oks) {
            $_SESSION['planilla_ok'] = 'Importado desde PLAME — ' . implode(' | ', $oks)
                . ' Revisa el régimen (ONP/AFP) de cada trabajador: se estima por el monto retenido.';
        }
        if ($errores) $_SESSION['planilla_error'] = implode(' | ', $errores);

        $destino = $periodos ? max($periodos) : (preg_match('/^\d{6}$/', $_POST['periodo'] ?? '') ? $_POST['periodo'] : Periodo::resolver($empresaId));
        header("Location: /empresas/{$empresaId}/planillas?periodo={$destino}"); exit;
    }

    /** Corrige el régimen de pensión (ONP/AFP/ninguno) de un trabajador — p. ej. tras importar de PLAME. */
    public function cambiarRegimen(int $empresaId, int $id): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $regimen = $_POST['regimen_pension'] ?? '';
        $periodo = preg_match('/^\d{6}$/', $_POST['periodo'] ?? '') ? $_POST['periodo'] : Periodo::resolver($empresaId);
        if (in_array($regimen, ['onp', 'afp', 'ninguno'], true)) {
            Model::db()->prepare("UPDATE planillas SET regimen_pension = ? WHERE id = ? AND empresa_id = ?")
                ->execute([$regimen, $id, $empresaId]);
        }
        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
    }

    public function eliminar(int $empresaId, int $id): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/planillas"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $periodo = $_POST['periodo'] ?? date('Ym');
        Model::db()->prepare("DELETE FROM planillas WHERE id = ? AND empresa_id = ?")
            ->execute([$id, $empresaId]);

        header("Location: /empresas/{$empresaId}/planillas?periodo={$periodo}"); exit;
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
