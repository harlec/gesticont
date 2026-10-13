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
 *
 * Correcciones del rediseño:
 *  - Las cuentas 19, 29 y 39 (provisión por cobranza dudosa, desvalorización de existencias y
 *    depreciación acumulada) tienen saldo ACREEDOR pero son cuentas del Activo que restan: antes
 *    esa columna se sumaba al Pasivo corriente. Ahora restan del activo de su grupo.
 *  - El resultado del ejercicio sale de los LIBROS: si el impuesto a la renta ya está registrado
 *    (881 / 4017) se muestra el resultado después de impuestos; si no, el resultado ANTES de
 *    impuestos y se avisa que falta provisionarlo. Antes se restaba un impuesto estimado que no
 *    estaba en ningún pasivo y el balance no cuadraba por exactamente ese monto.
 */
class BalanceGeneralService
{
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte, array $parametros): array
    {
        $balance = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCorte);
        $porCodigo = [];
        foreach ($balance['filas'] as $f) $porCodigo[$f['codigo']] = $f;

        $sec = ['activo_corriente' => [], 'activo_no_corriente' => [], 'pasivo_corriente' => [], 'pasivo_no_corriente' => []];
        $otrosPatrimonio = [];
        $costoActivosFijos = 0.0; $depreciacion = 0.0;

        foreach ($balance['filas'] as $f) {
            $cod = $f['codigo']; $el = $cod[0]; $dos = substr($cod, 0, 2);
            if ($el === '5') {
                // patrimonio: capital (501), acumulados (591) y resultado (5911/5912) se arman aparte; el resto va como "otras cuentas"
                if (!in_array($cod, ['501', '591', '5911', '5912'], true) && $cod !== '59') {
                    $m = round($f['pasivo'] - $f['activo'], 2);
                    if ($m != 0) $otrosPatrimonio[] = ['codigo' => $cod, 'nombre' => $f['nombre'], 'monto' => $m];
                }
                continue;
            }
            // provisiones y depreciación acumulada: saldo acreedor que RESTA del activo
            if (in_array($dos, ['19', '29', '39'], true)) {
                $neto = round($f['activo'] - $f['pasivo'], 2);
                if ($neto == 0) continue;
                $sec[$el === '3' ? 'activo_no_corriente' : 'activo_corriente'][] = ['codigo' => $cod, 'nombre' => $f['nombre'], 'monto' => $neto, 'resta' => $neto < 0];
                if ($dos === '39') $depreciacion += -$neto;
                continue;
            }
            if ($f['activo'] > 0) {
                $sec[$el === '3' ? 'activo_no_corriente' : 'activo_corriente'][] = ['codigo' => $cod, 'nombre' => $f['nombre'], 'monto' => $f['activo'], 'resta' => false];
                if ($el === '3' && $dos !== '39') $costoActivosFijos += $f['activo'];
            }
            // Columna "pasivo" de una cuenta que normalmente es activo (ej. un adelanto con saldo acreedor) también es pasivo real.
            if ($f['pasivo'] > 0) {
                $sec[$dos === '45' ? 'pasivo_no_corriente' : 'pasivo_corriente'][] = ['codigo' => $cod, 'nombre' => $f['nombre'], 'monto' => $f['pasivo'], 'resta' => false];
            }
        }
        $tot = fn(array $l) => round(array_sum(array_column($l, 'monto')), 2);
        $activoCorriente = $tot($sec['activo_corriente']);     $activoNoCorriente = $tot($sec['activo_no_corriente']);
        $pasivoCorriente = $tot($sec['pasivo_corriente']);     $pasivoNoCorriente = $tot($sec['pasivo_no_corriente']);
        $totalActivo = round($activoCorriente + $activoNoCorriente, 2);
        $totalPasivo = round($pasivoCorriente + $pasivoNoCorriente, 2);

        $capitalSocial        = ($porCodigo['501']['pasivo'] ?? 0.0) - ($porCodigo['501']['activo'] ?? 0.0);
        $resultadosAcumulados = ($porCodigo['591']['pasivo'] ?? 0.0) - ($porCodigo['591']['activo'] ?? 0.0);
        $otros = round(array_sum(array_column($otrosPatrimonio, 'monto')), 2);

        // Resultado del ejercicio de los libros. Abierto: se calcula del Estado de Resultados (5911/5912 están en cero).
        // Cerrado: el Estado de Resultados da 0 (las cuentas 6/7/9 quedaron cerradas) y el monto real está en 5911/5912.
        $resultado5911 = ($porCodigo['5911']['pasivo'] ?? 0.0) - ($porCodigo['5911']['activo'] ?? 0.0);
        $resultado5912 = ($porCodigo['5912']['activo'] ?? 0.0) - ($porCodigo['5912']['pasivo'] ?? 0.0);
        $resultadoYaCerrado = $resultado5911 - $resultado5912;

        $er = EstadoResultadosService::generar($empresaId, $periodoContableId, $fechaCorte, $parametros);
        $irRegistrado = $er['ir_registrado'] || abs($resultadoYaCerrado) > 0.001;
        $resultadoEnCurso = $er['ir_registrado'] ? $er['utilidad_ejercicio'] : $er['utilidad_antes_impuestos'];
        $resultadoEjercicio = round($resultadoEnCurso + $resultadoYaCerrado, 2);

        $patrimonio = round($capitalSocial + $resultadosAcumulados + $otros + $resultadoEjercicio, 2);
        $totalPasivoPatrimonio = round($totalPasivo + $patrimonio, 2);
        $descuadre = round($totalActivo - $totalPasivoPatrimonio, 2);

        // Compras en cuentas inventariables (601-604) que no pasaron a existencias ni a costo de venta (el asiento de destino las mueve).
        $pendienteInventariable = BalanceService::pendienteExistencias($balance['filas']);

        return [
            'activo_corriente' => $activoCorriente, 'activo_no_corriente' => $activoNoCorriente, 'total_activo' => $totalActivo,
            'pasivo_corriente' => $pasivoCorriente, 'pasivo_no_corriente' => $pasivoNoCorriente, 'total_pasivo' => $totalPasivo,
            'capital_social' => $capitalSocial, 'resultados_acumulados' => $resultadosAcumulados, 'otras_patrimonio' => $otros,
            'resultado_ejercicio' => $resultadoEjercicio, 'patrimonio' => $patrimonio,
            'total_pasivo_patrimonio' => $totalPasivoPatrimonio,
            'descuadre' => $descuadre,
            'detalle' => $sec + ['otros_patrimonio' => $otrosPatrimonio],
            'resultado_rotulo' => $irRegistrado ? 'después de impuestos' : 'antes de impuestos',
            'ir_registrado' => $irRegistrado,
            'impuesto_renta_estimado' => $irRegistrado ? 0.0 : $er['impuesto_renta'],
            'utilidad_antes_impuestos' => $er['utilidad_antes_impuestos'],
            'impuesto_renta_pendiente' => $irRegistrado ? 0.0 : $er['impuesto_renta'],   // compatibilidad con pantallas anteriores
            'depreciacion_acumulada' => round($depreciacion, 2), 'costo_activos_fijos' => round($costoActivosFijos, 2),
            'depreciacion_mayor_al_costo' => $depreciacion > 0.009 && $depreciacion > $costoActivosFijos + 0.009,
            'pendiente_inventariable' => $pendienteInventariable,
        ];
    }
}
