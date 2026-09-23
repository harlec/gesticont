<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';

/**
 * Inventario Inicial (saldos de apertura) — spec 2.1. Se llena manualmente
 * una sola vez por período de apertura; en años siguientes se debería
 * generar sola desde el Cierre del período anterior (2.16, aún no
 * construido) — hasta entonces, esta pantalla permite editar el período
 * abierto libremente.
 */
class AperturaController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo  = Model::db();
        $anio = Periodo::anio($empresaId);

        $stmtPer = $pdo->prepare("SELECT id, estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        // Solo cuentas de detalle (nivel >= 3) — las de nivel 2 son
        // agrupadores del elemento PCGE, no donde se registra un saldo real.
        $cuentasActivo = $pdo->query("
            SELECT id, codigo, nombre FROM cuentas_contables
            WHERE empresa_id IS NULL AND tipo = 'activo' AND nivel >= 3
            ORDER BY codigo
        ")->fetchAll(PDO::FETCH_ASSOC);

        $cuentasPasivo = $pdo->query("
            SELECT id, codigo, nombre, tipo FROM cuentas_contables
            WHERE empresa_id IS NULL AND tipo IN ('pasivo','patrimonio') AND nivel >= 3
            ORDER BY tipo = 'patrimonio', codigo
        ")->fetchAll(PDO::FETCH_ASSOC);

        $saldosExistentes = [];
        if ($periodoContable) {
            $stmtSaldos = $pdo->prepare("SELECT cuenta_id, debe, haber FROM saldos_apertura WHERE empresa_id = ? AND periodo_id = ?");
            $stmtSaldos->execute([$empresaId, (int)$periodoContable['id']]);
            foreach ($stmtSaldos->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $saldosExistentes[(int)$s['cuenta_id']] = (float)$s['debe'] + (float)$s['haber'];
            }
        }

        $pageTitle = 'Inventario Inicial — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/apertura/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function guardar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/apertura"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $anio   = (int)($_POST['anio'] ?? date('Y'));
        $montos = is_array($_POST['monto'] ?? null) ? $_POST['monto'] : [];

        $pdo = Model::db();

        // Trae tipo Y naturaleza de cada cuenta usada. El monto que se
        // ingresa siempre es positivo (la magnitud del saldo) — a qué
        // columna va (Debe/Haber) lo decide la NATURALEZA de la cuenta, no
        // el tipo. Son cosas distintas: casi todo "activo" es deudora,
        // pero cuentas contra-activo como 395 (Depreciación Acumulada) o
        // 19 (Estimación de Cobranza Dudosa) son acreedoras aunque su tipo
        // siga siendo "activo" — antes esto se decidía solo por tipo, así
        // que la depreciación se habría sumado al Debe en vez de restarse,
        // descuadrando cualquier apertura real que incluya activos usados.
        $cuentaIds = array_map('intval', array_keys($montos));
        $cuentas = [];
        if ($cuentaIds) {
            $in = implode(',', array_fill(0, count($cuentaIds), '?'));
            $stmt = $pdo->prepare("SELECT id, tipo, naturaleza FROM cuentas_contables WHERE id IN ($in) AND empresa_id IS NULL");
            $stmt->execute($cuentaIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cuentas[(int)$c['id']] = ['tipo' => $c['tipo'], 'naturaleza' => $c['naturaleza']];
            }
        }

        $lineas = [];
        $sumaActivo = $sumaPasivoPatrimonio = 0.0;
        foreach ($montos as $cuentaId => $monto) {
            $cuentaId = (int)$cuentaId;
            // abs(): el signo lo decide la naturaleza de la cuenta, nunca
            // lo que haya tipeado la persona — pero es el hábito natural de
            // cualquier contador escribir "-209,483" para una cuenta
            // contra-activo, exactamente como aparece en el propio Estado
            // de Situación Financiera de SUNAT (PDT 710). Antes un monto
            // negativo simplemente se saltaba en silencio (línea completa
            // ignorada), lo que hacía fallar el cuadre sin ninguna pista de
            // por qué — "no soporta negativos" era el síntoma correcto.
            $monto = abs((float)str_replace(',', '', (string)$monto));
            if ($monto == 0.0 || !isset($cuentas[$cuentaId])) continue;

            $c = $cuentas[$cuentaId];
            $esDeudora = $c['naturaleza'] === 'deudora';
            $lineas[] = $esDeudora
                ? ['cuenta_id' => $cuentaId, 'debe' => $monto, 'haber' => 0]
                : ['cuenta_id' => $cuentaId, 'debe' => 0, 'haber' => $monto];

            // Neto por lado del balance: una cuenta "activo" de naturaleza
            // acreedora (contra-activo) resta del total de Activo en vez de
            // sumar; lo mismo para una "pasivo/patrimonio" de naturaleza
            // deudora (ej. 5912 Pérdida del Ejercicio).
            $signo = $esDeudora ? 1 : -1;
            if ($c['tipo'] === 'activo') {
                $sumaActivo += $monto * $signo;
            } else {
                $sumaPasivoPatrimonio += $monto * -$signo;
            }
        }

        $sumaActivo = round($sumaActivo, 2);
        $sumaPasivoPatrimonio = round($sumaPasivoPatrimonio, 2);

        // Control obligatorio de la spec (2.1): rechazar el guardado si no
        // cuadra — nunca guardar un inventario inicial descuadrado. El
        // mensaje dice la diferencia y de qué lado falta directamente —
        // antes solo mostraba los dos totales y había que restar a mano
        // para entender qué faltaba (causa real de este reporte: alguien
        // llenó solo una cuenta de Activo, sin su contrapartida).
        if (empty($lineas)) {
            $_SESSION['apertura_error'] = 'No se guardó: no ingresaste ningún monto.';
            header("Location: /empresas/{$empresaId}/apertura?anio={$anio}"); exit;
        }
        if (abs($sumaActivo - $sumaPasivoPatrimonio) > 0.01) {
            $diferencia = abs(round($sumaActivo - $sumaPasivoPatrimonio, 2));
            $ladoFaltante = $sumaActivo > $sumaPasivoPatrimonio ? 'Pasivo + Patrimonio' : 'Activo';
            $_SESSION['apertura_error'] = sprintf(
                'No se guardó: falta S/ %s del lado de %s para que cuadre (Activo: S/ %s · Pasivo + Patrimonio: S/ %s).',
                number_format($diferencia, 2), $ladoFaltante,
                number_format($sumaActivo, 2), number_format($sumaPasivoPatrimonio, 2)
            );
            header("Location: /empresas/{$empresaId}/apertura?anio={$anio}"); exit;
        }

        $stmtPer = $pdo->prepare("SELECT id, estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        if ($periodoContable && $periodoContable['estado'] === 'cerrado') {
            $_SESSION['apertura_error'] = "El período {$anio} está cerrado — no se puede editar el inventario inicial.";
            header("Location: /empresas/{$empresaId}/apertura?anio={$anio}"); exit;
        }

        Model::beginTransaction();
        try {
            if (!$periodoContable) {
                $pdo->prepare("
                    INSERT INTO periodos_contables (empresa_id, anio, estado, fecha_apertura)
                    VALUES (?, ?, 'abierto', ?)
                ")->execute([$empresaId, $anio, "{$anio}-01-01"]);
                $periodoContableId = (int)$pdo->lastInsertId();
            } else {
                $periodoContableId = (int)$periodoContable['id'];
            }

            // Reemplaza el inventario inicial completo del período — permite
            // corregir mientras el período siga abierto, en vez de acumular
            // filas viejas.
            $pdo->prepare("DELETE FROM saldos_apertura WHERE empresa_id = ? AND periodo_id = ?")
                ->execute([$empresaId, $periodoContableId]);

            $stmtIns = $pdo->prepare("
                INSERT INTO saldos_apertura (empresa_id, periodo_id, cuenta_id, debe, haber)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($lineas as $l) {
                $stmtIns->execute([$empresaId, $periodoContableId, $l['cuenta_id'], $l['debe'], $l['haber']]);
            }

            Model::commit();
        } catch (Exception $e) {
            Model::rollback();
            $_SESSION['apertura_error'] = 'Error al guardar el inventario inicial.';
            header("Location: /empresas/{$empresaId}/apertura?anio={$anio}"); exit;
        }

        header("Location: /empresas/{$empresaId}/apertura?anio={$anio}&ok=1"); exit;
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
