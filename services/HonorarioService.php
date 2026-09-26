<?php
require_once ROOT . '/services/CobroPagoService.php';

/**
 * Honorarios por recibo (renta de 4ta categoría). Cada recibo genera, al
 * armar el Diario del mes, la provisión Debe 632 (bruto) / Haber 424 (neto
 * por pagar) + Haber 4017 (retención). Se asume pagado por defecto: el pago
 * crea un movimiento de Caja Debe 424 / Haber 101 por el neto. La retención
 * se entrega a SUNAT aparte (egreso de Caja contra 4017).
 */
class HonorarioService
{
    public const TASA_RETENCION = 0.08;
    public const UMBRAL_RETENCION = 1500.0;

    /** SUNAT retiene 8 % cuando el recibo supera S/ 1,500. */
    public static function retencionSugerida(float $monto): float
    {
        return $monto > self::UMBRAL_RETENCION ? round($monto * self::TASA_RETENCION, 2) : 0.0;
    }

    /** @return array{ok:bool,error?:string,id?:int} */
    public static function crear(int $empresaId, array $d, bool $pagado, int $usuarioId): array
    {
        $nombre = trim((string)($d['prestador_nombre'] ?? ''));
        $monto  = round((float)str_replace(',', '', (string)($d['monto'] ?? 0)), 2);
        $fecha  = (string)($d['fecha'] ?? '');

        if ($nombre === '' || $monto <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return ['ok' => false, 'error' => 'Faltan el prestador, la fecha o el monto.'];
        }
        // Retención vacía = la regla de SUNAT; escrita = respeta lo escrito (p. ej. suspensión de 4ta).
        $retencion = ($d['retencion'] ?? '') === '' ? self::retencionSugerida($monto)
                                                    : round((float)str_replace(',', '', (string)$d['retencion']), 2);
        if ($retencion < 0 || $retencion >= $monto) {
            return ['ok' => false, 'error' => 'La retención debe ser menor que el monto del recibo.'];
        }
        if (CobroPagoService::periodoCerrado($empresaId, $fecha)) {
            return ['ok' => false, 'error' => 'El año de esa fecha está cerrado.'];
        }

        $pdo = Model::db();
        $pdo->prepare("
            INSERT INTO honorarios (empresa_id, periodo, fecha, prestador_nombre, prestador_doc, comprobante, descripcion, monto, retencion, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $empresaId, substr($fecha, 0, 4) . substr($fecha, 5, 2), $fecha, $nombre,
            trim((string)($d['prestador_doc'] ?? '')) ?: null, trim((string)($d['comprobante'] ?? '')) ?: null,
            trim((string)($d['descripcion'] ?? '')) ?: null, $monto, $retencion, $usuarioId,
        ]);
        $id = (int)$pdo->lastInsertId();

        if ($pagado) {
            $r = self::pagar($empresaId, $id, !empty($d['fecha_pago']) ? $d['fecha_pago'] : $fecha, $usuarioId);
            if (!$r['ok']) return ['ok' => true, 'id' => $id, 'error' => $r['error']];
        }
        return ['ok' => true, 'id' => $id];
    }

    public static function neto(array $h): float
    {
        return round((float)$h['monto'] - (float)$h['retencion'], 2);
    }

    /** Paga el neto del recibo desde Caja (Debe 424 / Haber 101 al generar el asiento de Caja). */
    public static function pagar(int $empresaId, int $id, ?string $fecha, int $usuarioId): array
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("SELECT * FROM honorarios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);
        $h = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$h) return ['ok' => false, 'error' => 'El recibo no existe.'];
        if ($h['pagado']) return ['ok' => true];

        $fecha = $fecha ?: $h['fecha'];
        if (CobroPagoService::periodoCerrado($empresaId, $fecha)) {
            return ['ok' => false, 'error' => 'El año de la fecha de pago está cerrado.'];
        }

        $stmtCta = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = '424' AND empresa_id IS NULL LIMIT 1");
        $stmtCta->execute();
        $desc = 'Pago honorarios ' . ($h['comprobante'] ?: $h['prestador_nombre']);

        $pdo->prepare("
            INSERT INTO caja_movimientos (empresa_id, tipo, cuenta_id, honorario_id, descripcion, monto, fecha, registrado_por)
            VALUES (?, 'egreso', ?, ?, ?, ?, ?, ?)
        ")->execute([$empresaId, (int)$stmtCta->fetchColumn(), $id, $desc, self::neto($h), $fecha, $usuarioId]);
        $pdo->prepare("UPDATE honorarios SET pagado = 1, fecha_pago = ? WHERE id = ?")->execute([$fecha, $id]);
        return ['ok' => true];
    }

    public static function pasarAPendiente(int $empresaId, int $id): array
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("SELECT fecha FROM caja_movimientos WHERE empresa_id = ? AND honorario_id = ?");
        $stmt->execute([$empresaId, $id]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if (CobroPagoService::periodoCerrado($empresaId, $f)) return ['ok' => false, 'error' => 'El pago está en un año cerrado.'];
        }
        $pdo->prepare("DELETE FROM caja_movimientos WHERE empresa_id = ? AND honorario_id = ?")->execute([$empresaId, $id]);
        $pdo->prepare("UPDATE honorarios SET pagado = 0, fecha_pago = NULL WHERE id = ? AND empresa_id = ?")->execute([$id, $empresaId]);
        return ['ok' => true];
    }

    public static function eliminar(int $empresaId, int $id): array
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("SELECT fecha FROM honorarios WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$id, $empresaId]);
        $fecha = $stmt->fetchColumn();
        if ($fecha === false) return ['ok' => false, 'error' => 'El recibo no existe.'];
        if (CobroPagoService::periodoCerrado($empresaId, $fecha)) return ['ok' => false, 'error' => 'El año está cerrado.'];

        $r = self::pasarAPendiente($empresaId, $id);
        if (!$r['ok']) return $r;
        $pdo->prepare("DELETE FROM honorarios WHERE id = ? AND empresa_id = ?")->execute([$id, $empresaId]);
        return ['ok' => true];
    }
}
