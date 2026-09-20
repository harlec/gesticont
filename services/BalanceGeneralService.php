<?php
require_once ROOT . '/services/BalanceService.php';
require_once ROOT . '/services/EstadoResultadosService.php';

/**
 * GestiCont — Balance General (Estado de Situación Financiera). Ver
 * plan-completo-motor-contable.md sección 2.11.
 *
 * Clasifica Activo/Pasivo en Corriente/No Corriente por el elemento PCGE
 * del código de cuenta (1-2 = activo corriente, 3 = activo no corriente,
 * 4 excepto 45 = pasivo corriente, 45 = pasivo no corriente) — esta regla
 * es fija por el propio diseño del PCGE, no depende de cada empresa.
 */
class BalanceGeneralService
{
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte, array $parametros): array
    {
        $balance = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCorte);
        $porCodigo = [];
        foreach ($balance['filas'] as $f) $porCodigo[$f['codigo']] = $f;

        $activoCorriente = $activoNoCorriente = 0.0;
        $pasivoCorriente = $pasivoNoCorriente = 0.0;

        foreach ($balance['filas'] as $f) {
            $elemento = $f['codigo'][0];
            if ($elemento === '5') continue; // patrimonio se calcula aparte (501/591/resultado)

            // Columna "activo" del Balance de Comprobación: una cuenta que
            // normalmente es pasivo (ej. 4011 IGV) puede tener saldo deudor
            // (crédito fiscal a favor) y ahí sí es un activo real — por eso
            // se suma esta columna sin importar el elemento de origen de la
            // cuenta, tal como ya lo resuelve BalanceService.
            $activoNoCorriente += $elemento === '3' ? $f['activo'] : 0;
            $activoCorriente   += $elemento === '3' ? 0 : $f['activo'];

            $dosDigitos = substr($f['codigo'], 0, 2);
            $pasivoNoCorriente += $dosDigitos === '45' ? $f['pasivo'] : 0;
            $pasivoCorriente   += $dosDigitos === '45' ? 0 : $f['pasivo'];
        }
        $activoCorriente   = round($activoCorriente, 2);
        $activoNoCorriente = round($activoNoCorriente, 2);
        $pasivoCorriente   = round($pasivoCorriente, 2);
        $pasivoNoCorriente = round($pasivoNoCorriente, 2);
        $totalActivo = round($activoCorriente + $activoNoCorriente, 2);
        $totalPasivo = round($pasivoCorriente + $pasivoNoCorriente, 2);

        // El saldo "normal" de 501/591 es acreedor (columna Pasivo del
        // Balance de Comprobación), pero si alguna vez tuvieran saldo
        // deudor (ej. pérdidas acumuladas en 591), hay que restarlo, no
        // ignorarlo.
        $capitalSocial        = ($porCodigo['501']['pasivo'] ?? 0.0) - ($porCodigo['501']['activo'] ?? 0.0);
        $resultadosAcumulados = ($porCodigo['591']['pasivo'] ?? 0.0) - ($porCodigo['591']['activo'] ?? 0.0);

        // El resultado del ejercicio EN CURSO todavía no está asentado en
        // 5911/5912 (eso solo ocurre en el Cierre de Período, 2.16) — se
        // toma directo del Estado de Resultados para que el Balance
        // General de un período abierto muestre la utilidad corrida.
        $estadoResultados = EstadoResultadosService::generar($empresaId, $periodoContableId, $fechaCorte, $parametros);
        $resultadoEjercicio = $estadoResultados['utilidad_ejercicio'];

        $patrimonio = round($capitalSocial + $resultadosAcumulados + $resultadoEjercicio, 2);
        $totalPasivoPatrimonio = round($totalPasivo + $patrimonio, 2);

        $descuadre = round($totalActivo - $totalPasivoPatrimonio, 2);

        // Compras clasificadas en cuentas inventariables (601-604: mercadería,
        // materia prima, suministros, envases) que siguen ahí porque no existe
        // Kardex/Inventario Final para reclasificarlas a existencias (20/24/25/26)
        // o a costo de venta (691). Mientras tanto ese monto no aparece ni como
        // Activo ni dentro del Estado de Resultados — es la otra causa esperada
        // de descuadre, normalmente más grande que el IR sin provisionar.
        $pendienteInventariable = 0.0;
        foreach ($balance['filas'] as $f) {
            if ($f['es_inventariable']) $pendienteInventariable += ($f['deudor'] - $f['acreedor']);
        }
        $pendienteInventariable = round($pendienteInventariable, 2);

        return [
            'activo_corriente' => $activoCorriente, 'activo_no_corriente' => $activoNoCorriente, 'total_activo' => $totalActivo,
            'pasivo_corriente' => $pasivoCorriente, 'pasivo_no_corriente' => $pasivoNoCorriente, 'total_pasivo' => $totalPasivo,
            'capital_social' => $capitalSocial, 'resultados_acumulados' => $resultadosAcumulados,
            'resultado_ejercicio' => $resultadoEjercicio, 'patrimonio' => $patrimonio,
            'total_pasivo_patrimonio' => $totalPasivoPatrimonio,
            'descuadre' => $descuadre,
            'impuesto_renta_pendiente' => $estadoResultados['impuesto_renta'],
            'pendiente_inventariable' => $pendienteInventariable,
        ];
    }
}
