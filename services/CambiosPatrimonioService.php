<?php
require_once ROOT . '/services/EstadoResultadosService.php';

/**
 * GestiCont — Estado de Cambios en el Patrimonio Neto. Ver
 * plan-completo-motor-contable.md sección 2.14.
 *
 * Columnas: Capital, Reservas Legales, Resultados Acumulados, Total.
 * No hay módulo de aportes/retiros de capital todavía, así que el Capital
 * se mantiene fijo entre el saldo inicial y el final — solo se mueve
 * Resultados Acumulados (por la utilidad del ejercicio) y su traslado
 * parcial a Reservas Legales.
 */
class CambiosPatrimonioService
{
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte, array $parametros): array
    {
        $pdo = Model::db();

        $stmt = $pdo->prepare("
            SELECT c.codigo, SUM(sa.haber) - SUM(sa.debe) AS saldo
            FROM saldos_apertura sa
            JOIN cuentas_contables c ON c.id = sa.cuenta_id
            WHERE sa.empresa_id = ? AND sa.periodo_id = ? AND c.codigo IN ('501', '591')
            GROUP BY c.codigo
        ");
        $stmt->execute([$empresaId, $periodoContableId]);
        $iniciales = ['501' => 0.0, '591' => 0.0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $iniciales[$r['codigo']] = (float)$r['saldo'];

        $capitalInicial    = round($iniciales['501'], 2);
        $resultadosInicial = round($iniciales['591'], 2);

        $estadoResultados = EstadoResultadosService::generar($empresaId, $periodoContableId, $fechaCorte, $parametros);
        $utilidadNeta = $estadoResultados['utilidad_ejercicio']; // ya neto de IR
        $reservaLegal = $estadoResultados['reserva_legal'];

        $capitalFinal    = $capitalInicial;
        $reservasFinal   = round(0 + $reservaLegal, 2);
        $resultadosFinal = round($resultadosInicial + $utilidadNeta - $reservaLegal, 2);

        $totalInicial = round($capitalInicial + 0 + $resultadosInicial, 2);
        $totalFinal   = round($capitalFinal + $reservasFinal + $resultadosFinal, 2);

        return [
            'capital_inicial' => $capitalInicial, 'reservas_inicial' => 0.0, 'resultados_inicial' => $resultadosInicial, 'total_inicial' => $totalInicial,
            'utilidad_neta' => $utilidadNeta,
            'reserva_legal' => $reservaLegal,
            'capital_final' => $capitalFinal, 'reservas_final' => $reservasFinal, 'resultados_final' => $resultadosFinal, 'total_final' => $totalFinal,
        ];
    }
}
