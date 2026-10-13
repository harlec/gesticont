<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/CierreService.php';
require_once ROOT . '/services/BalanceService.php';

class CierreController
{
    public function index(int $empresaId): void
    {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('No encontrado'); }

        $pdo  = Model::db();
        $anio = Periodo::anio($empresaId);

        $stmtPer = $pdo->prepare("SELECT * FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);

        $pendientes = ['ventas' => 0, 'compras' => 0];
        $pendienteInventariable = 0.0;
        $yaCerrado = false;
        $costoOk = true; $existencias = 0.0; $costo = 0.0; $irOk = true; $irRegistrado = false; $utilidadAntes = 0.0; $librosOk = true; $librosDetalle = '';

        if ($periodoContable) {
            $likeAnio = $anio . '%';
            $stmtPend = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente' AND estado_sunat='1') AS ventas,
                    (SELECT COUNT(*) FROM registro_compras WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente' AND estado_sunat='1') AS compras
            ");
            $stmtPend->execute([$empresaId, $likeAnio, $empresaId, $likeAnio]);
            $pendientes = $stmtPend->fetch(PDO::FETCH_ASSOC);

            $pid = (int)$periodoContable['id'];
            $balance = BalanceService::comprobacion($empresaId, $pid, "{$anio}-12-31");
            $pendienteInventariable = BalanceService::pendienteExistencias($balance['filas']);

            $stmtCierre = $pdo->prepare("SELECT * FROM cierres_periodo WHERE empresa_id = ? AND periodo_id = ?");
            $stmtCierre->execute([$empresaId, $pid]);
            $yaCerrado = (bool)$stmtCierre->fetch(PDO::FETCH_ASSOC);

            if (!$yaCerrado && !empty($balance['filas'])) {
                require_once ROOT . '/services/EstadoResultadosService.php';
                require_once ROOT . '/services/BalanceGeneralService.php';
                foreach ($balance['filas'] as $f) if (preg_match('/^(20|24|25|26)/', $f['codigo'])) $existencias += $f['activo'];
                $stmtPar = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
                $stmtPar->execute([$empresaId, $pid]);
                if ($par = $stmtPar->fetch(PDO::FETCH_ASSOC)) {
                    $er = EstadoResultadosService::generar($empresaId, $pid, "{$anio}-12-31", $par);
                    $costo = $er['costo_ventas']; $irRegistrado = $er['ir_registrado']; $utilidadAntes = $er['utilidad_antes_impuestos'];
                    $costoOk = !($existencias > 0.004 && $costo < 0.005);
                    $irOk = $irRegistrado || $utilidadAntes <= 0.004;
                    $bg = BalanceGeneralService::generar($empresaId, $pid, "{$anio}-12-31", $par);
                    $t = $balance['totales'];
                    $cuadraDiario = abs($t['debe'] - $t['haber']) < 0.01;
                    $cuadraNatFun = abs(($t['nat_ganancias'] - $t['nat_perdidas']) - ($t['fun_ganancias'] - $t['fun_perdidas'])) < 0.02;
                    $cuadraBalance = abs($bg['descuadre']) < 0.01;
                    $librosOk = $cuadraDiario && $cuadraNatFun && $cuadraBalance;
                    $librosDetalle = !$cuadraDiario ? 'El Debe y el Haber del Diario no son iguales.' : (!$cuadraNatFun ? 'El resultado por naturaleza y por función no coincide.' : (!$cuadraBalance ? 'El balance general no cuadra por S/ ' . number_format(abs($bg['descuadre']), 2) . '.' : ''));
                }
            }
        }

        $pasos = [
            ['ventas',  'Ventas clasificadas',       (int)$pendientes['ventas'] === 0,  (int)$pendientes['ventas'] === 0 ? 'Todas las ventas de ' . $anio . ' están clasificadas.' : 'Faltan clasificar ' . (int)$pendientes['ventas'] . ' venta(s).', "/empresas/{$empresaId}/imputacion?tipo=ventas", 'Clasificar ventas'],
            ['compras', 'Compras clasificadas',      (int)$pendientes['compras'] === 0, (int)$pendientes['compras'] === 0 ? 'Todas las compras de ' . $anio . ' están clasificadas.' : 'Faltan clasificar ' . (int)$pendientes['compras'] . ' compra(s).', "/empresas/{$empresaId}/imputacion?tipo=compras", 'Clasificar compras'],
            ['costo',   'Costo de ventas registrado', $costoOk, $costoOk ? ($existencias > 0.004 ? 'Registrado: S/ ' . number_format($costo, 2) . '.' : 'No hay existencias que ajustar.') : 'Hay existencias por S/ ' . number_format($existencias, 2) . ' y el costo de ventas está en cero.', "/empresas/{$empresaId}/asientos?plantilla=costo", 'Registrar costo de ventas'],
            ['ir',      'Impuesto a la renta provisionado', $irOk, $irOk ? ($irRegistrado ? 'Registrado en la cuenta 881.' : 'No hay utilidad: no corresponde provisionar.') : 'Falta registrar el impuesto (881 / 4017).', "/empresas/{$empresaId}/asientos?plantilla=ir", 'Registrar el impuesto'],
            ['libros',  'Los libros cuadran',        $librosOk, $librosOk ? 'Diario, resultados y balance cuadran.' : $librosDetalle, "/empresas/{$empresaId}/balance", 'Ver balance de comprobación'],
        ];
        $faltan = count(array_filter($pasos, fn($p) => !$p[2]));
        $puedeCerrar = $periodoContable && $periodoContable['estado'] === 'abierto' && !$yaCerrado && $faltan === 0;

        $pageTitle = 'Cierre del año — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/cierre/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function cerrar(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/cierre"); exit;
        }
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $anio = (int)($_POST['anio'] ?? date('Y'));
        $resultado = CierreService::cerrarPeriodo($empresaId, $anio, Auth::id());

        if (isset($resultado['error'])) {
            $_SESSION['cierre_error'] = $resultado['error'];
        } else {
            $_SESSION['cierre_ok'] = sprintf(
                'Período %d cerrado. Utilidad neta: S/ %s. Saldos de apertura de %d generados.',
                $anio, number_format($resultado['utilidad_neta'], 2), $resultado['anio_siguiente']
            );
        }

        header("Location: /empresas/{$empresaId}/cierre?anio={$anio}"); exit;
    }

    public function reabrir(int $empresaId): void
    {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$empresaId}/cierre"); exit; }
        if (!$this->_getEmpresa($empresaId)) { http_response_code(403); die('Sin acceso'); }
        $anio = (int)($_POST['anio'] ?? date('Y'));
        $r = CierreService::reabrirPeriodo($empresaId, $anio);
        if (isset($r['error'])) $_SESSION['cierre_error'] = $r['error']; else $_SESSION['cierre_ok'] = "El año {$anio} quedó abierto otra vez. Cuando termines tus ajustes, ciérralo de nuevo.";
        header("Location: /empresas/{$empresaId}/cierre?anio={$anio}"); exit;
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
