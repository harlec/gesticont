<?php
/**
 * GestiCont — Depuración de data contable anterior a un período (por defecto
 * Periodo::minimo()). La usan la pantalla "Depurar" y cron/purgar_anterior.php.
 *
 * Qué borra (solo lo anterior a $hasta, YYYYMM, que SÍ se conserva):
 *   registro_ventas / registro_compras (y sus imputaciones, en cascada), resumen_mensual,
 *   caja_movimientos, planillas, honorarios, asientos (y su detalle) y los años contables
 *   anteriores (saldos de apertura, parámetros y cierres, en cascada).
 * Qué NO toca: comprobantes emitidos, guías, declaraciones, alertas, usuarios/empresas,
 *   plan de cuentas, reglas de imputación, préstamos ni los movimientos de caja ligados a
 *   un préstamo (el saldo de un préstamo viejo sigue vivo).
 *
 * El borrado se hace por tandas (paso()) para no pasar el tiempo máximo del servidor web
 * (504) cuando hay decenas de miles de filas. Es reanudable: si se interrumpe, basta repetir.
 */
class PurgaService
{
    public const LOTE = 2000;

    /** [etiqueta, tabla, condición (primer ? = empresa_id), parámetros que siguen]. Orden = orden de borrado. */
    private static function definicion(string $hasta): array
    {
        $fechaCorte = substr($hasta, 0, 4) . '-' . substr($hasta, 4, 2) . '-01';
        return [
            ['Ventas SIRE',          'registro_ventas',    'empresa_id = ? AND periodo < ?',                       [$hasta]],
            ['Compras SIRE',         'registro_compras',   'empresa_id = ? AND periodo < ?',                       [$hasta]],
            ['Resumen mensual',      'resumen_mensual',    'empresa_id = ? AND periodo < ?',                       [$hasta]],
            ['Movimientos de caja',  'caja_movimientos',   'empresa_id = ? AND fecha < ? AND prestamo_id IS NULL', [$fechaCorte]],
            ['Planillas',            'planillas',          'empresa_id = ? AND periodo < ?',                       [$hasta]],
            ['Honorarios',           'honorarios',         'empresa_id = ? AND periodo < ?',                       [$hasta]],
            ['Asientos del diario',  'asientos',           'empresa_id = ? AND fecha < ?',                         [$fechaCorte]],
            ['Años contables',       'periodos_contables', 'empresa_id = ? AND anio < ?',                          [(int)substr($hasta, 0, 4)]],
        ];
    }

    private static function tieneTabla(PDO $pdo, string $t): bool
    {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $s->execute([$t]);
        return (bool)$s->fetchColumn();
    }

    private static function tieneColumna(PDO $pdo, string $t, string $c): bool
    {
        $s = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $s->execute([$t, $c]);
        return (bool)$s->fetchColumn();
    }

    /** Definición filtrada a lo que existe en esta base (tablas/columnas que faltan se omiten). */
    private static function aplicables(PDO $pdo, string $hasta): array
    {
        $out = [];
        foreach (self::definicion($hasta) as [$etq, $tabla, $cond, $extra]) {
            if (!self::tieneTabla($pdo, $tabla)) continue;
            if (str_contains($cond, 'prestamo_id') && !self::tieneColumna($pdo, $tabla, 'prestamo_id')) {
                $cond = str_replace(' AND prestamo_id IS NULL', '', $cond);
            }
            if (preg_match('/\b(periodo|fecha|anio)\b/', $cond, $m) && !self::tieneColumna($pdo, $tabla, $m[1])) continue;
            $out[] = [$etq, $tabla, $cond, $extra];
        }
        return $out;
    }

    /** @return array<int,array{etiqueta:string,tabla:string,n:int}> */
    public static function conteos(PDO $pdo, int $empresaId, string $hasta): array
    {
        $out = [];
        foreach (self::aplicables($pdo, $hasta) as [$etq, $tabla, $cond, $extra]) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$cond}");
            $c->execute(array_merge([$empresaId], $extra));
            $out[] = ['etiqueta' => $etq, 'tabla' => $tabla, 'n' => (int)$c->fetchColumn()];
        }
        return $out;
    }

    /**
     * Seguro: si hay años contables anteriores que borrar, el primer año que se conserva debe
     * tener sus saldos de apertura — si no, su balance perdería el punto de partida.
     * @return array{ok:bool,hay_anios_viejos:bool,anio:int}
     */
    public static function apertura(PDO $pdo, int $empresaId, string $hasta): array
    {
        $anio = (int)substr($hasta, 0, 4);
        $s = $pdo->prepare("SELECT COUNT(*) FROM periodos_contables WHERE empresa_id = ? AND anio < ?");
        $s->execute([$empresaId, $anio]);
        $viejos = (int)$s->fetchColumn() > 0;
        $s = $pdo->prepare("
            SELECT COUNT(*) FROM saldos_apertura sa JOIN periodos_contables pc ON pc.id = sa.periodo_id
            WHERE sa.empresa_id = ? AND pc.anio = ?
        ");
        $s->execute([$empresaId, $anio]);
        return ['ok' => !$viejos || (int)$s->fetchColumn() > 0, 'hay_anios_viejos' => $viejos, 'anio' => $anio];
    }

    /**
     * Borra UNA tanda (hasta LOTE filas) de la primera tabla que aún tenga pendientes.
     * @return array{etiqueta:?string,borradas:int,pendientes:int,terminado:bool}
     */
    public static function paso(PDO $pdo, int $empresaId, string $hasta): array
    {
        $pendientes = 0; $hecho = null; $borradas = 0;
        foreach (self::aplicables($pdo, $hasta) as [$etq, $tabla, $cond, $extra]) {
            $params = array_merge([$empresaId], $extra);
            $c = $pdo->prepare("SELECT COUNT(*) FROM {$tabla} WHERE {$cond}");
            $c->execute($params);
            $n = (int)$c->fetchColumn();
            if ($n === 0) continue;

            if ($hecho === null) {
                // Los asientos arrastran su detalle en cascada: tandas más chicas.
                $limite = $tabla === 'asientos' ? 500 : self::LOTE;
                $sql = "DELETE FROM {$tabla} WHERE {$cond}" . ($tabla === 'periodos_contables' ? '' : " LIMIT {$limite}");
                $d = $pdo->prepare($sql);
                $d->execute($params);
                $borradas = $d->rowCount();
                if ($borradas === 0) throw new RuntimeException("No se pudo borrar de {$tabla}: quedan {$n} filas pero ninguna se eliminó (¿restricción de clave foránea?).");
                $hecho = $etq;
                $n -= $borradas;
            }
            $pendientes += max(0, $n);
        }
        return ['etiqueta' => $hecho, 'borradas' => $borradas, 'pendientes' => $pendientes, 'terminado' => $pendientes === 0];
    }
}
