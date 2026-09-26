<?php
/**
 * GestiCont — Balance de Comprobación (8 columnas), calculado siempre
 * agregando saldos_apertura + asientos_detalle. Nunca es una tabla física
 * ni un cálculo paralelo — ver plan-completo-motor-contable.md sección 2.9-2.10.
 */
class BalanceService
{
    /**
     * Cierre de la hoja de trabajo al pie del Balance de Comprobación: una
     * sola resta da la utilidad o pérdida del ejercicio, y esa cifra se suma
     * a la columna que quedó corta de cada par para que ambas igualen.
     *
     *   Resultados: Ganancias - Pérdidas. Si sobran ganancias es UTILIDAD y
     *   se suma a Pérdidas; si sobran pérdidas es PÉRDIDA y se suma a Ganancias.
     *   Inventario: Activo - Pasivo. Con utilidad el Pasivo queda corto (esa
     *   utilidad es patrimonio que aún no está en el pasivo) y se le suma;
     *   con pérdida es el Activo el que queda corto.
     *
     * Es el resultado antes de Impuesto a la Renta y de Reserva Legal.
     * `coincide` es false solo si ambas restas difieren, lo que indicaría
     * que el Debe y el Haber no cuadran.
     *
     * @param array $t $balance['totales']
     */
    public static function cierreResultado(array $t): array
    {
        $resultado = round($t['ganancias'] - $t['perdidas'], 2);
        $difInv    = round($t['activo'] - $t['pasivo'], 2);

        $aj = ['activo' => 0.0, 'pasivo' => 0.0, 'perdidas' => 0.0, 'ganancias' => 0.0];
        if ($resultado > 0)      $aj['perdidas']  = $resultado;
        elseif ($resultado < 0)  $aj['ganancias'] = -$resultado;
        if ($difInv > 0)         $aj['pasivo']    = $difInv;
        elseif ($difInv < 0)     $aj['activo']    = -$difInv;

        return [
            'tipo'       => $resultado > 0 ? 'utilidad' : ($resultado < 0 ? 'perdida' : 'nulo'),
            'monto'      => abs($resultado),
            'ajuste'     => $aj,
            'iguales'    => [
                'activo'    => round($t['activo']    + $aj['activo'], 2),
                'pasivo'    => round($t['pasivo']    + $aj['pasivo'], 2),
                'perdidas'  => round($t['perdidas']  + $aj['perdidas'], 2),
                'ganancias' => round($t['ganancias'] + $aj['ganancias'], 2),
            ],
            'coincide'   => abs($resultado - $difInv) < 0.02,
        ];
    }

    /**
     * Compras de mercadería/materia prima/suministros/envases (601-604) que
     * siguen en el gasto sin haber pasado a existencias. Se compensa con su
     * contrapartida en 61 (611-614, saldo acreedor tras el asiento de
     * "Destino de compras"): una vez pasadas a stock el neto es 0 y ya no
     * cuenta como pendiente de reclasificar.
     *
     * @param array $filas filas de comprobacion()['filas']
     */
    public static function pendienteExistencias(array $filas): float
    {
        $pendiente = 0.0;
        foreach ($filas as $f) {
            if ($f['es_inventariable'] || preg_match('/^61[1-4]$/', $f['codigo'])) {
                $pendiente += ($f['deudor'] - $f['acreedor']);
            }
        }
        return round($pendiente, 2);
    }

    /**
     * @param string $fechaCorte 'YYYY-MM-DD' — acumula desde el saldo de
     *   apertura del año hasta esta fecha inclusive (no solo el mes).
     */
    public static function comprobacion(int $empresaId, int $periodoContableId, string $fechaCorte): array
    {
        $pdo = Model::db();

        $stmt = $pdo->prepare("
            SELECT c.id, c.codigo, c.nombre, c.tipo, c.naturaleza, c.es_inventariable,
                   COALESCE(sa.debe, 0)  + COALESCE(m.debe, 0)  AS suma_debe,
                   COALESCE(sa.haber, 0) + COALESCE(m.haber, 0) AS suma_haber
            FROM cuentas_contables c
            LEFT JOIN saldos_apertura sa
                   ON sa.cuenta_id = c.id AND sa.periodo_id = ?
            LEFT JOIN (
                SELECT d.cuenta_id, SUM(d.debe) AS debe, SUM(d.haber) AS haber
                FROM asientos_detalle d
                JOIN asientos a ON a.id = d.asiento_id
                WHERE a.empresa_id = ? AND a.periodo_id = ? AND a.fecha <= ?
                GROUP BY d.cuenta_id
            ) m ON m.cuenta_id = c.id
            WHERE c.empresa_id IS NULL
            HAVING suma_debe <> 0 OR suma_haber <> 0
            ORDER BY c.codigo
        ");
        $stmt->execute([$periodoContableId, $empresaId, $periodoContableId, $fechaCorte]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $resultado = [];
        foreach ($filas as $f) {
            $debe  = round((float)$f['suma_debe'], 2);
            $haber = round((float)$f['suma_haber'], 2);
            $deudor   = max($debe - $haber, 0);
            $acreedor = max($haber - $debe, 0);

            $activo = $pasivo = $perdidas = $ganancias = 0.0;

            if (in_array($f['tipo'], ['activo', 'pasivo', 'patrimonio'], true)) {
                $activo = $deudor;
                $pasivo = $acreedor;
            } elseif (in_array($f['tipo'], ['ingreso', 'gasto'], true)) {
                $perdidas  = $deudor;
                $ganancias = $acreedor;
            } elseif ($f['tipo'] === 'destino') {
                // Resultado por función: el saldo ya viene fijado por el
                // asiento de reclasificación de 2.7 — no se recalcula nada
                // adicional aquí, solo se muestra deudor/acreedor tal cual.
                $perdidas  = $deudor;
                $ganancias = $acreedor;
            }

            $resultado[] = [
                'codigo' => $f['codigo'], 'nombre' => $f['nombre'], 'tipo' => $f['tipo'],
                'es_inventariable' => (bool)$f['es_inventariable'],
                'debe' => $debe, 'haber' => $haber,
                'deudor' => $deudor, 'acreedor' => $acreedor,
                'activo' => $activo, 'pasivo' => $pasivo,
                'perdidas' => $perdidas, 'ganancias' => $ganancias,
            ];
        }

        $totales = ['debe' => 0, 'haber' => 0, 'deudor' => 0, 'acreedor' => 0, 'activo' => 0, 'pasivo' => 0, 'perdidas' => 0, 'ganancias' => 0];
        foreach ($resultado as $r) {
            foreach ($totales as $k => $v) $totales[$k] += $r[$k];
        }
        foreach ($totales as $k => $v) $totales[$k] = round($v, 2);

        return ['filas' => $resultado, 'totales' => $totales];
    }
}
