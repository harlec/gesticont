<?php
require_once ROOT . '/services/BalanceService.php';
require_once ROOT . '/services/BalanceGeneralService.php';

/**
 * Qué subsecciones de Contabilidad tienen algo que revisar (chip ámbar en la barra de subsecciones).
 * true = revisar · int = N pendientes · 'cerrado' = el año ya está cerrado · null = sin observaciones.
 */
class ContabilidadService
{
    public static function revisiones(int $empresaId, string $periodo): array
    {
        static $cache = [];
        if (isset($cache[$empresaId . $periodo])) return $cache[$empresaId . $periodo];
        $pdo = Model::db(); $anio = (int)substr($periodo, 0, 4);
        $out = [];

        // Comprobantes sin clasificar → el Diario saldrá incompleto
        $st = $pdo->prepare("SELECT (SELECT COUNT(*) FROM registro_ventas WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1') + (SELECT COUNT(*) FROM registro_compras WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'pendiente' AND estado_sunat = '1')");
        $st->execute([$empresaId, $periodo, $empresaId, $periodo]);
        $sinClasificar = (int)$st->fetchColumn();
        if ($sinClasificar > 0) $out['diario'] = true;

        $st = $pdo->prepare("SELECT id, estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $st->execute([$empresaId, $anio]);
        $pc = $st->fetch(PDO::FETCH_ASSOC);
        if ($pc) {
            $pid = (int)$pc['id'];
            $corte = date('Y-m-t', strtotime(substr($periodo, 0, 4) . '-' . substr($periodo, 4, 2) . '-01'));
            $b = BalanceService::comprobacion($empresaId, $pid, $corte);
            if (!empty($b['filas'])) {
                if (abs($b['totales']['debe'] - $b['totales']['haber']) > 0.009) { $out['balance'] = true; $out['diario'] = true; }
                $t = $b['totales'];
                $nat = $t['nat_ganancias'] - $t['nat_perdidas']; $fun = $t['fun_ganancias'] - $t['fun_perdidas'];
                if (abs($nat - $fun) > 0.02) $out['balance'] = true;

                $pp = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?"); $pp->execute([$empresaId, $pid]);
                if ($par = $pp->fetch(PDO::FETCH_ASSOC)) {
                    $bg = BalanceGeneralService::generar($empresaId, $pid, $corte, $par);
                    if (abs($bg['descuadre']) > 0.01 || $bg['depreciacion_mayor_al_costo']) $out['balance-general'] = true;
                    if (!$bg['ir_registrado'] && $bg['utilidad_antes_impuestos'] > 0) $out['resultados'] = true;
                    $costo = EstadoResultadosService::generar($empresaId, $pid, $corte, $par)['costo_ventas'];
                    $existencias = 0.0; foreach ($b['filas'] as $f) if (preg_match('/^(20|24|25|26)/', $f['codigo'])) $existencias += $f['activo'];
                    if ($costo == 0 && $existencias > 0) $out['resultados'] = true;
                }
            }
            if ($pc['estado'] === 'cerrado') $out['cierre'] = 'cerrado';
        }
        return $cache[$empresaId . $periodo] = $out;
    }
}
