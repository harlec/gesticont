<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';

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
