<?php
/**
 * GestiCont — Libro Mayor (spec 2.9): por cada cuenta usada, todas las
 * líneas de asientos_detalle que la tocan, en formato "cuenta T" (una
 * sección por cuenta con su saldo de apertura, movimientos cronológicos y
 * totales). Se calcula siempre por consulta agregada sobre saldos_apertura
 * + asientos_detalle — nunca es una tabla física ni algo editable a mano,
 * a diferencia del Excel de referencia (Valencia) donde el Mayor mezclaba
 * fórmulas con números tipeados directamente. Aquí el Diario (imputación
 * → asientos) es la única fuente de verdad; el Mayor es solo otra forma de
 * mirar esos mismos datos, agrupados por cuenta en vez de por fecha.
 */
class LibroMayorService
{
    /**
     * @param string $fechaCorte 'YYYY-MM-DD' — igual que BalanceService,
     *   acumula desde el saldo de apertura del año hasta esta fecha.
     */
    public static function generar(int $empresaId, int $periodoContableId, string $fechaCorte): array
    {
        $pdo = Model::db();

        // Cuentas con algún movimiento (apertura o asiento) en el período,
        // igual criterio que el Balance de Comprobación.
        $stmtCuentas = $pdo->prepare("
            SELECT c.id, c.codigo, c.nombre
            FROM cuentas_contables c
            LEFT JOIN saldos_apertura sa ON sa.cuenta_id = c.id AND sa.periodo_id = ?
            LEFT JOIN (
                SELECT DISTINCT d.cuenta_id
                FROM asientos_detalle d
                JOIN asientos a ON a.id = d.asiento_id
                WHERE a.empresa_id = ? AND a.periodo_id = ? AND a.fecha <= ?
            ) m ON m.cuenta_id = c.id
            WHERE c.empresa_id IS NULL AND (sa.id IS NOT NULL OR m.cuenta_id IS NOT NULL)
            ORDER BY c.codigo
        ");
        $stmtCuentas->execute([$periodoContableId, $empresaId, $periodoContableId, $fechaCorte]);
        $cuentas = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);
        if (empty($cuentas)) return [];

        $stmtApertura = $pdo->prepare("SELECT debe, haber FROM saldos_apertura WHERE cuenta_id = ? AND periodo_id = ?");
        $stmtMovs = $pdo->prepare("
            SELECT a.fecha, a.glosa, a.origen, d.debe, d.haber
            FROM asientos_detalle d
            JOIN asientos a ON a.id = d.asiento_id
            WHERE d.cuenta_id = ? AND a.empresa_id = ? AND a.periodo_id = ? AND a.fecha <= ?
            ORDER BY a.fecha, a.id
        ");

        $resultado = [];
        foreach ($cuentas as $c) {
            $stmtApertura->execute([$c['id'], $periodoContableId]);
            $apertura = $stmtApertura->fetch(PDO::FETCH_ASSOC);

            $stmtMovs->execute([$c['id'], $empresaId, $periodoContableId, $fechaCorte]);
            $movimientos = $stmtMovs->fetchAll(PDO::FETCH_ASSOC);

            $totalDebe  = (float)($apertura['debe'] ?? 0);
            $totalHaber = (float)($apertura['haber'] ?? 0);
            foreach ($movimientos as $m) {
                $totalDebe  += (float)$m['debe'];
                $totalHaber += (float)$m['haber'];
            }

            $resultado[] = [
                'codigo' => $c['codigo'],
                'nombre' => $c['nombre'],
                'saldo_apertura' => $apertura ? [
                    'debe' => round((float)$apertura['debe'], 2),
                    'haber' => round((float)$apertura['haber'], 2),
                ] : null,
                'movimientos' => $movimientos,
                'total_debe' => round($totalDebe, 2),
                'total_haber' => round($totalHaber, 2),
                'saldo' => round($totalDebe - $totalHaber, 2),
            ];
        }

        return $resultado;
    }
}
