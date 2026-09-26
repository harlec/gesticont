<?php
/**
 * Cobros y pagos de comprobantes (ventas -> cobrar, compras -> pagar).
 *
 * Fuente de verdad: los movimientos de Caja ligados al comprobante
 * (caja_movimientos.registro_venta_id / registro_compra_id). Lo cobrado o
 * pagado es la suma de esos movimientos; el saldo es total - suma. Así un
 * comprobante puede estar pagado del todo, a medias (parcial) o a crédito
 * (ningún movimiento), y cada cobro/pago aparece en Caja y en el Diario.
 *
 * Los campos cobrado/pagado y fecha_cobro/fecha_pago de los registros se
 * mantienen sincronizados como resumen (los usa el resto del sistema):
 * valen 1 solo cuando el saldo llegó a cero.
 *
 * Criterio de producto: se asume que todo está cobrado/pagado (se crea el
 * movimiento por el total al clasificar) y luego se corrige aquí cuando el
 * cliente/proveedor pagó la mitad o fue a crédito.
 */
class CobroPagoService
{
    public const TOLERANCIA = 0.01;

    /** @return array{tabla:string,col:string,flag:string,fecha:string,tipoMov:string,cuentaContra:string,etiqueta:string} */
    public static function config(string $origen): array
    {
        return $origen === 'venta'
            ? ['tabla' => 'registro_ventas',  'col' => 'registro_venta_id',  'flag' => 'cobrado', 'fecha' => 'fecha_cobro', 'tipoMov' => 'ingreso', 'cuentaContra' => '121', 'etiqueta' => 'Cobro']
            : ['tabla' => 'registro_compras', 'col' => 'registro_compra_id', 'flag' => 'pagado',  'fecha' => 'fecha_pago',  'tipoMov' => 'egreso',  'cuentaContra' => '421', 'etiqueta' => 'Pago'];
    }

    public static function cobradoDe(int $empresaId, string $origen, int $docId): float
    {
        $c = self::config($origen);
        $stmt = Model::db()->prepare("
            SELECT COALESCE(SUM(monto), 0) FROM caja_movimientos
            WHERE empresa_id = ? AND {$c['col']} = ? AND tipo = ?
        ");
        $stmt->execute([$empresaId, $docId, $c['tipoMov']]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    /**
     * Registra un cobro/pago (total o parcial) de un comprobante ya
     * clasificado. Genera el movimiento de Caja contra 121/421.
     *
     * @return array{ok:bool,error?:string,saldo?:float}
     */
    public static function registrar(int $empresaId, string $origen, int $docId, float $monto, ?string $fecha, int $usuarioId): array
    {
        if (!in_array($origen, ['venta', 'compra'], true) || $docId <= 0) return ['ok' => false, 'error' => 'Datos incompletos.'];
        $monto = round($monto, 2);
        if ($monto <= 0) return ['ok' => false, 'error' => 'El monto debe ser mayor a cero.'];

        $c   = self::config($origen);
        $pdo = Model::db();

        $stmt = $pdo->prepare("SELECT * FROM {$c['tabla']} WHERE id = ? AND empresa_id = ? AND estado_imputacion = 'imputado' AND estado_sunat = '1'");
        $stmt->execute([$docId, $empresaId]);
        $doc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$doc) return ['ok' => false, 'error' => 'El comprobante no está clasificado o no existe.'];

        $total  = round((float)$doc['total'], 2);
        $saldo  = round($total - self::cobradoDe($empresaId, $origen, $docId), 2);
        if ($monto > $saldo + self::TOLERANCIA) {
            return ['ok' => false, 'error' => sprintf('El monto (S/ %s) supera el saldo pendiente (S/ %s).', number_format($monto, 2), number_format($saldo, 2))];
        }
        if ($monto > $saldo) $monto = $saldo; // redondeo de centavos

        $fecha = $fecha ?: $doc['fecha_emision'];
        if (self::periodoCerrado($empresaId, $fecha)) {
            return ['ok' => false, 'error' => 'El año de esa fecha está cerrado: no se pueden registrar cobros ni pagos.'];
        }

        $stmtCta = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = ? AND empresa_id IS NULL LIMIT 1");
        $stmtCta->execute([$c['cuentaContra']]);
        $cuentaId = (int)$stmtCta->fetchColumn();

        $parcial = $monto < $total - self::TOLERANCIA;
        $descripcion = $c['etiqueta'] . ($parcial ? ' parcial ' : ' ') . "{$doc['serie']}-{$doc['correlativo']}";

        $pdo->prepare("
            INSERT INTO caja_movimientos (empresa_id, tipo, cuenta_id, {$c['col']}, descripcion, monto, fecha, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$empresaId, $c['tipoMov'], $cuentaId, $docId, $descripcion, $monto, $fecha, $usuarioId]);

        self::sincronizar($empresaId, $origen, $docId);
        return ['ok' => true, 'saldo' => round($saldo - $monto, 2)];
    }

    /** Devuelve el comprobante a crédito: borra todos sus cobros/pagos de Caja. */
    public static function pasarACredito(int $empresaId, string $origen, int $docId): array
    {
        $c   = self::config($origen);
        $pdo = Model::db();

        $stmt = $pdo->prepare("SELECT fecha FROM caja_movimientos WHERE empresa_id = ? AND {$c['col']} = ? AND tipo = ?");
        $stmt->execute([$empresaId, $docId, $c['tipoMov']]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if (self::periodoCerrado($empresaId, $f)) {
                return ['ok' => false, 'error' => 'Tiene cobros/pagos en un año cerrado: no se pueden deshacer.'];
            }
        }

        $pdo->prepare("DELETE FROM caja_movimientos WHERE empresa_id = ? AND {$c['col']} = ? AND tipo = ?")
            ->execute([$empresaId, $docId, $c['tipoMov']]);
        self::sincronizar($empresaId, $origen, $docId);
        return ['ok' => true];
    }

    /**
     * "Asumir todo cobrado/pagado" para un período: cada comprobante
     * clasificado con saldo pendiente se cobra/paga por su saldo, en la
     * fecha de su emisión.
     *
     * @return array{ok:bool,cantidad:int,errores:int}
     */
    public static function marcarTodoPagado(int $empresaId, string $origen, string $periodo, int $usuarioId): array
    {
        $c   = self::config($origen);
        $pdo = Model::db();

        $stmt = $pdo->prepare("
            SELECT id, total, fecha_emision FROM {$c['tabla']}
            WHERE empresa_id = ? AND periodo = ? AND estado_imputacion = 'imputado' AND estado_sunat = '1'
        ");
        $stmt->execute([$empresaId, $periodo]);

        $cantidad = 0; $errores = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $saldo = round((float)$d['total'] - self::cobradoDe($empresaId, $origen, (int)$d['id']), 2);
            if ($saldo <= self::TOLERANCIA) continue;
            $r = self::registrar($empresaId, $origen, (int)$d['id'], $saldo, $d['fecha_emision'], $usuarioId);
            $r['ok'] ? $cantidad++ : $errores++;
        }
        return ['ok' => true, 'cantidad' => $cantidad, 'errores' => $errores];
    }

    /** Recalcula el resumen cobrado/pagado + fecha del comprobante a partir de sus movimientos. */
    public static function sincronizar(int $empresaId, string $origen, int $docId): void
    {
        $c   = self::config($origen);
        $pdo = Model::db();

        $stmt = $pdo->prepare("SELECT total FROM {$c['tabla']} WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$docId, $empresaId]);
        $total = $stmt->fetchColumn();
        if ($total === false) return;

        $stmtM = $pdo->prepare("
            SELECT COALESCE(SUM(monto), 0), MAX(fecha) FROM caja_movimientos
            WHERE empresa_id = ? AND {$c['col']} = ? AND tipo = ?
        ");
        $stmtM->execute([$empresaId, $docId, $c['tipoMov']]);
        [$pagado, $ultima] = $stmtM->fetch(PDO::FETCH_NUM);

        $completo = round((float)$total - (float)$pagado, 2) <= self::TOLERANCIA && (float)$pagado > 0;
        $pdo->prepare("UPDATE {$c['tabla']} SET {$c['flag']} = ?, {$c['fecha']} = ? WHERE id = ?")
            ->execute([$completo ? 1 : 0, $completo ? $ultima : null, $docId]);
    }

    public static function periodoCerrado(int $empresaId, string $fecha): bool
    {
        $stmt = Model::db()->prepare("SELECT estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmt->execute([$empresaId, (int)substr($fecha, 0, 4)]);
        return $stmt->fetchColumn() === 'cerrado';
    }
}
