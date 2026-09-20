<?php
require_once ROOT . '/services/BalanceService.php';

/**
 * GestiCont — Estado de Resultados (Pérdidas y Ganancias), calculado
 * como sumas de subconjuntos del Balance de Comprobación — nunca un
 * recálculo independiente. Ver plan-completo-motor-contable.md 2.12-2.13.
 */
class EstadoResultadosService
{
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte, array $parametros): array
    {
        $balance = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCorte);

        $porCodigo = [];
        foreach ($balance['filas'] as $f) $porCodigo[$f['codigo']] = $f;

        $sum = function (array $codigos, string $col) use ($porCodigo) {
            $total = 0.0;
            foreach ($codigos as $c) $total += $porCodigo[$c][$col] ?? 0;
            return round($total, 2);
        };

        $ingresosVentas = $sum(['701', '702', '703', '704'], 'ganancias');
        $costoVentas    = $sum(['691'], 'perdidas');
        $utilidadBruta  = round($ingresosVentas - $costoVentas, 2);

        // NOTA: el Costo de Ventas (691) solo tendrá dato una vez exista el
        // módulo de Kardex/Inventario Final (spec 2.6) — hasta entonces esta
        // línea queda en 0 y la Utilidad Bruta coincide con los Ingresos.
        $costoProduccion   = $sum(['92'], 'perdidas');
        $gastosAdmin       = $sum(['94'], 'perdidas');
        $gastosVenta       = $sum(['95'], 'perdidas');
        $gastosFinancieros = $sum(['96'], 'perdidas');
        $utilidadOperativa = round($utilidadBruta - $costoProduccion - $gastosAdmin - $gastosVenta - $gastosFinancieros, 2);

        $ingresosFinancieros   = $sum(['779'], 'ganancias');
        $utilidadAntesImpuestos = round($utilidadOperativa + $ingresosFinancieros, 2);

        // El IR y la Reserva Legal solo se calculan sobre utilidad positiva —
        // con pérdida no corresponde ninguno de los dos.
        $tasaIr        = (float)$parametros['tasa_ir'];
        $impuestoRenta = $utilidadAntesImpuestos > 0 ? round($utilidadAntesImpuestos * $tasaIr / 100, 2) : 0.0;
        $utilidadEjercicio = round($utilidadAntesImpuestos - $impuestoRenta, 2);

        $pctReserva   = (float)$parametros['pct_reserva_legal'];
        $reservaLegal = $utilidadEjercicio > 0 ? round($utilidadEjercicio * $pctReserva / 100, 2) : 0.0;
        $utilidadAntesReparticion = round($utilidadEjercicio - $reservaLegal, 2);

        return [
            'ingresos_ventas'           => $ingresosVentas,
            'costo_ventas'              => $costoVentas,
            'utilidad_bruta'            => $utilidadBruta,
            'costo_produccion'          => $costoProduccion,
            'gastos_admin'              => $gastosAdmin,
            'gastos_venta'              => $gastosVenta,
            'gastos_financieros'        => $gastosFinancieros,
            'utilidad_operativa'        => $utilidadOperativa,
            'ingresos_financieros'      => $ingresosFinancieros,
            'utilidad_antes_impuestos'  => $utilidadAntesImpuestos,
            'tasa_ir'                   => $tasaIr,
            'impuesto_renta'            => $impuestoRenta,
            'utilidad_ejercicio'        => $utilidadEjercicio,
            'pct_reserva_legal'         => $pctReserva,
            'reserva_legal'             => $reservaLegal,
            'utilidad_antes_reparticion'=> $utilidadAntesReparticion,
        ];
    }
}
