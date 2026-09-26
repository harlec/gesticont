<?php
require_once ROOT . '/services/CobroPagoService.php';

/**
 * Préstamos bancarios. Todo se apoya en movimientos de Caja ligados al
 * préstamo: desembolso = ingreso contra 451, amortización de capital =
 * egreso contra 451, interés = egreso contra 673. Recibido, pagado y saldo
 * pendiente se calculan sumando esos movimientos — nunca se guardan.
 */
class PrestamoService
{
    public static function listar(int $empresaId): array
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("
            SELECT p.*,
                   COALESCE(SUM(CASE WHEN cm.tipo = 'ingreso' THEN cm.monto END), 0)                        AS recibido,
                   COALESCE(SUM(CASE WHEN cm.tipo = 'egreso' AND c.codigo = '451' THEN cm.monto END), 0)   AS capital_pagado,
                   COALESCE(SUM(CASE WHEN cm.tipo = 'egreso' AND c.codigo <> '451' THEN cm.monto END), 0)  AS intereses
            FROM prestamos p
            LEFT JOIN caja_movimientos cm ON cm.prestamo_id = p.id
            LEFT JOIN cuentas_contables c ON c.id = cm.cuenta_id
            WHERE p.empresa_id = ?
            GROUP BY p.id ORDER BY p.fecha_desembolso DESC, p.id DESC
        ");
        $stmt->execute([$empresaId]);
        $prestamos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtMov = $pdo->prepare("
            SELECT cm.id, cm.prestamo_id, cm.tipo, cm.fecha, cm.monto, cm.descripcion, c.codigo AS cuenta
            FROM caja_movimientos cm JOIN cuentas_contables c ON c.id = cm.cuenta_id
            WHERE cm.empresa_id = ? AND cm.prestamo_id IS NOT NULL ORDER BY cm.fecha, cm.id
        ");
        $stmtMov->execute([$empresaId]);
        $movs = [];
        foreach ($stmtMov->fetchAll(PDO::FETCH_ASSOC) as $m) $movs[$m['prestamo_id']][] = $m;

        foreach ($prestamos as &$p) {
            $p['recibido']       = round((float)$p['recibido'], 2);
            $p['capital_pagado'] = round((float)$p['capital_pagado'], 2);
            $p['intereses']      = round((float)$p['intereses'], 2);
            $p['saldo']          = round($p['recibido'] - $p['capital_pagado'], 2);
            $p['movimientos']    = $movs[$p['id']] ?? [];
        }
        return $prestamos;
    }

    /** Registra el préstamo y su desembolso (ingreso de Caja contra 451). */
    public static function crear(int $empresaId, string $entidad, ?string $referencia, string $fecha, float $monto, int $usuarioId): array
    {
        $entidad = trim($entidad); $monto = round($monto, 2);
        if ($entidad === '' || $monto <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['ok' => false, 'error' => 'Faltan la entidad, la fecha o el monto.'];
        }
        if (CobroPagoService::periodoCerrado($empresaId, $fecha)) return ['ok' => false, 'error' => 'El año de esa fecha está cerrado.'];

        $pdo = Model::db();
        $pdo->prepare("INSERT INTO prestamos (empresa_id, entidad, referencia, fecha_desembolso, usuario_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$empresaId, $entidad, trim((string)$referencia) ?: null, $fecha, $usuarioId]);
        $id = (int)$pdo->lastInsertId();
        self::mov($empresaId, $id, 'ingreso', '451', $monto, $fecha, "Desembolso préstamo {$entidad}", $usuarioId);
        return ['ok' => true, 'id' => $id];
    }

    /** Registra un pago: capital (contra 451) y/o interés (contra 673). */
    public static function pagar(int $empresaId, int $id, float $capital, float $interes, string $fecha, int $usuarioId): array
    {
        $capital = round($capital, 2); $interes = round($interes, 2);
        if ($capital < 0 || $interes < 0 || ($capital == 0 && $interes == 0) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['ok' => false, 'error' => 'Indica el capital y/o el interés pagado, y la fecha.'];
        }
        $p = null;
        foreach (self::listar($empresaId) as $x) if ((int)$x['id'] === $id) $p = $x;
        if (!$p) return ['ok' => false, 'error' => 'El préstamo no existe.'];
        if ($capital > $p['saldo'] + CobroPagoService::TOLERANCIA) {
            return ['ok' => false, 'error' => sprintf('El capital (S/ %s) supera el saldo del préstamo (S/ %s).', number_format($capital, 2), number_format($p['saldo'], 2))];
        }
        if (CobroPagoService::periodoCerrado($empresaId, $fecha)) return ['ok' => false, 'error' => 'El año de esa fecha está cerrado.'];

        if ($capital > 0) self::mov($empresaId, $id, 'egreso', '451', $capital, $fecha, "Pago capital préstamo {$p['entidad']}", $usuarioId);
        if ($interes > 0) self::mov($empresaId, $id, 'egreso', '673', $interes, $fecha, "Interés préstamo {$p['entidad']}", $usuarioId);
        return ['ok' => true];
    }

    /** Borra el préstamo con todos sus movimientos de Caja. */
    public static function eliminar(int $empresaId, int $id): array
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("SELECT fecha FROM caja_movimientos WHERE empresa_id = ? AND prestamo_id = ?");
        $stmt->execute([$empresaId, $id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if (CobroPagoService::periodoCerrado($empresaId, $f)) return ['ok' => false, 'error' => 'Tiene movimientos en un año cerrado.'];
        }
        $pdo->prepare("DELETE FROM caja_movimientos WHERE empresa_id = ? AND prestamo_id = ?")->execute([$empresaId, $id]);
        $pdo->prepare("DELETE FROM prestamos WHERE id = ? AND empresa_id = ?")->execute([$id, $empresaId]);
        return ['ok' => true];
    }

    private static function mov(int $empresaId, int $prestamoId, string $tipo, string $codigoCuenta, float $monto, string $fecha, string $desc, int $usuarioId): void
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = ? AND empresa_id IS NULL LIMIT 1");
        $stmt->execute([$codigoCuenta]);
        $pdo->prepare("
            INSERT INTO caja_movimientos (empresa_id, tipo, cuenta_id, prestamo_id, descripcion, monto, fecha, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$empresaId, $tipo, (int)$stmt->fetchColumn(), $prestamoId, $desc, $monto, $fecha, $usuarioId]);
    }
}
