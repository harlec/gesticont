<?php
require_once ROOT . '/services/BalanceService.php';
require_once ROOT . '/services/AsientoService.php';

/**
 * GestiCont — Cierre de Período. Ver plan-completo-motor-contable.md
 * sección 2.16.
 *
 * Simplificación consciente: la Reserva Legal se muestra en el Estado de
 * Cambios en el Patrimonio como una reclasificación informativa dentro de
 * Resultados Acumulados, pero NO se postea a una cuenta "58 Reservas"
 * separada — esa cuenta no se agregó al catálogo porque no se pudo
 * verificar contra el PCGE oficial en esta sesión (a diferencia de las
 * demás, que sí se verificaron una por una). El asiento de cierre solo
 * mueve Utilidad/Pérdida Neta a 5911/5912, que sí están verificadas.
 */
class CierreService
{
    public static function cerrarPeriodo(int $empresaId, int $anio, int $usuarioId): array
    {
        $pdo = Model::db();

        $stmtPer = $pdo->prepare("SELECT id, estado FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
        $stmtPer->execute([$empresaId, $anio]);
        $periodoContable = $stmtPer->fetch(PDO::FETCH_ASSOC);
        if (!$periodoContable) return ['error' => "No existe el período contable {$anio} para esta empresa."];
        if ($periodoContable['estado'] === 'cerrado') return ['error' => "El período {$anio} ya está cerrado."];
        $periodoContableId = (int)$periodoContable['id'];

        $stmtYaCerrado = $pdo->prepare("SELECT id FROM cierres_periodo WHERE empresa_id = ? AND periodo_id = ?");
        $stmtYaCerrado->execute([$empresaId, $periodoContableId]);
        if ($stmtYaCerrado->fetch()) return ['error' => "Ya existe un cierre registrado para {$anio}."];

        // Guard 1: no debe haber compras/ventas del año sin clasificar.
        $stmtPend = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM registro_ventas  WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente') AS ventas,
                (SELECT COUNT(*) FROM registro_compras WHERE empresa_id=? AND periodo LIKE ? AND estado_imputacion='pendiente') AS compras
        ");
        $likeAnio = $anio . '%';
        $stmtPend->execute([$empresaId, $likeAnio, $empresaId, $likeAnio]);
        $pend = $stmtPend->fetch(PDO::FETCH_ASSOC);
        if ((int)$pend['ventas'] > 0 || (int)$pend['compras'] > 0) {
            return ['error' => "No se puede cerrar: quedan {$pend['ventas']} venta(s) y {$pend['compras']} compra(s) de {$anio} sin clasificar."];
        }

        $fechaCierre = "{$anio}-12-31";
        $balance = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCierre);

        // Guard 2: no debe haber compras inventariables pendientes de
        // reclasificar (601-604) — cerrar con esto pendiente asumiría
        // silenciosamente "todo se vendió", lo cual puede ser falso.
        $pendienteInventariable = 0.0;
        foreach ($balance['filas'] as $f) {
            if ($f['es_inventariable']) $pendienteInventariable += ($f['deudor'] - $f['acreedor']);
        }
        $pendienteInventariable = round($pendienteInventariable, 2);
        if (abs($pendienteInventariable) > 0.01) {
            return ['error' => sprintf(
                'No se puede cerrar: hay S/ %s en compras de mercadería/materia prima/suministros sin reclasificar a existencias o costo de venta (falta el módulo de Kardex/Inventario Final). Resolver eso primero.',
                number_format($pendienteInventariable, 2)
            )];
        }

        $stmtParam = $pdo->prepare("SELECT * FROM parametros_contables WHERE empresa_id = ? AND periodo_id = ?");
        $stmtParam->execute([$empresaId, $periodoContableId]);
        $parametros = $stmtParam->fetch(PDO::FETCH_ASSOC);
        if (!$parametros) return ['error' => 'No hay parámetros contables (tasa IR, etc.) para este período — genera al menos un asiento primero.'];

        Model::beginTransaction();
        try {
            // 1. Asiento de cierre: zonifica cuentas 6/7/9 y provisiona el IR.
            $lineas = [];
            $gananciasTotal = 0.0; $perdidasTotal = 0.0;
            foreach ($balance['filas'] as $f) {
                if (!in_array($f['tipo'], ['ingreso', 'gasto', 'destino'], true)) continue;
                if ($f['ganancias'] > 0) {
                    $lineas[] = ['cuenta_id' => self::cuentaIdPorCodigo($pdo, $f['codigo']), 'debe' => $f['ganancias'], 'haber' => 0];
                    $gananciasTotal += $f['ganancias'];
                }
                if ($f['perdidas'] > 0) {
                    $lineas[] = ['cuenta_id' => self::cuentaIdPorCodigo($pdo, $f['codigo']), 'debe' => 0, 'haber' => $f['perdidas']];
                    $perdidasTotal += $f['perdidas'];
                }
            }
            $utilidadAntesImpuestos = round($gananciasTotal - $perdidasTotal, 2);

            $tasaIr = (float)$parametros['tasa_ir'];
            $impuestoRenta = $utilidadAntesImpuestos > 0 ? round($utilidadAntesImpuestos * $tasaIr / 100, 2) : 0.0;
            $utilidadNeta = round($utilidadAntesImpuestos - $impuestoRenta, 2);

            if ($impuestoRenta > 0) {
                $lineas[] = ['cuenta_id' => self::cuentaIdPorCodigo($pdo, '4017'), 'debe' => 0, 'haber' => $impuestoRenta];
            }
            if ($utilidadNeta > 0) {
                $lineas[] = ['cuenta_id' => self::cuentaIdPorCodigo($pdo, '5911'), 'debe' => 0, 'haber' => $utilidadNeta];
            } elseif ($utilidadNeta < 0) {
                $lineas[] = ['cuenta_id' => self::cuentaIdPorCodigo($pdo, '5912'), 'debe' => -$utilidadNeta, 'haber' => 0];
            }

            $correlativo = self::siguienteCorrelativo($pdo, $empresaId);
            $glosa = "Cierre del ejercicio {$anio}";
            $pdo->prepare("
                INSERT INTO asientos (empresa_id, periodo_id, correlativo, fecha, glosa, origen)
                VALUES (?, ?, ?, ?, ?, 'cierre')
            ")->execute([$empresaId, $periodoContableId, $correlativo, $fechaCierre, $glosa]);
            $asientoId = (int)$pdo->lastInsertId();

            $sumaDebe  = round(array_sum(array_column($lineas, 'debe')), 2);
            $sumaHaber = round(array_sum(array_column($lineas, 'haber')), 2);
            if ($sumaDebe !== $sumaHaber) {
                throw new Exception("Asiento de cierre descuadrado: Debe {$sumaDebe} ≠ Haber {$sumaHaber} — no debería pasar, revisar manualmente.");
            }
            $stmtLinea = $pdo->prepare("INSERT INTO asientos_detalle (asiento_id, cuenta_id, debe, haber) VALUES (?,?,?,?)");
            foreach ($lineas as $l) {
                if ($l['debe'] == 0 && $l['haber'] == 0) continue;
                $stmtLinea->execute([$asientoId, $l['cuenta_id'], $l['debe'], $l['haber']]);
            }

            // 2. Recalcular el Balance YA CON el asiento de cierre aplicado,
            // para tomar los saldos finales reales de Balance General.
            $balanceFinal = BalanceService::comprobacion($empresaId, $periodoContableId, $fechaCierre);

            $anioSiguiente = $anio + 1;
            $stmtPerSig = $pdo->prepare("SELECT id FROM periodos_contables WHERE empresa_id = ? AND anio = ?");
            $stmtPerSig->execute([$empresaId, $anioSiguiente]);
            $periodoSiguiente = $stmtPerSig->fetch(PDO::FETCH_ASSOC);
            if ($periodoSiguiente) {
                $periodoSiguienteId = (int)$periodoSiguiente['id'];
            } else {
                $pdo->prepare("
                    INSERT INTO periodos_contables (empresa_id, anio, estado, fecha_apertura)
                    VALUES (?, ?, 'abierto', ?)
                ")->execute([$empresaId, $anioSiguiente, "{$anioSiguiente}-01-01"]);
                $periodoSiguienteId = (int)$pdo->lastInsertId();
            }

            // Reemplaza cualquier saldo de apertura previo del año siguiente
            // (por si se estaba usando un valor provisional).
            $pdo->prepare("DELETE FROM saldos_apertura WHERE empresa_id = ? AND periodo_id = ?")
                ->execute([$empresaId, $periodoSiguienteId]);

            $stmtInsSaldo = $pdo->prepare("
                INSERT INTO saldos_apertura (empresa_id, periodo_id, cuenta_id, debe, haber)
                VALUES (?, ?, ?, ?, ?)
            ");
            $cuentaIdPorCodigoCache = [];
            foreach ($balanceFinal['filas'] as $f) {
                if (!in_array($f['tipo'], ['activo', 'pasivo', 'patrimonio'], true)) continue;
                if ($f['activo'] == 0 && $f['pasivo'] == 0) continue;
                if (!isset($cuentaIdPorCodigoCache[$f['codigo']])) {
                    $cuentaIdPorCodigoCache[$f['codigo']] = self::cuentaIdPorCodigo($pdo, $f['codigo']);
                }
                $stmtInsSaldo->execute([
                    $empresaId, $periodoSiguienteId, $cuentaIdPorCodigoCache[$f['codigo']],
                    round($f['activo'], 2), round($f['pasivo'], 2),
                ]);
            }

            // 3. Cerrar el período y dejar registro del cierre.
            $pdo->prepare("UPDATE periodos_contables SET estado = 'cerrado' WHERE id = ?")->execute([$periodoContableId]);
            $pdo->prepare("
                INSERT INTO cierres_periodo (empresa_id, periodo_id, fecha_cierre, usuario_id, resultado_ejercicio)
                VALUES (?, ?, ?, ?, ?)
            ")->execute([$empresaId, $periodoContableId, $fechaCierre, $usuarioId, $utilidadNeta]);

            Model::commit();
        } catch (Exception $e) {
            Model::rollback();
            return ['error' => 'No se pudo cerrar: ' . $e->getMessage()];
        }

        return [
            'utilidad_antes_impuestos' => $utilidadAntesImpuestos,
            'impuesto_renta' => $impuestoRenta,
            'utilidad_neta' => $utilidadNeta,
            'anio_siguiente' => $anioSiguiente,
        ];
    }

    private static function siguienteCorrelativo(PDO $pdo, int $empresaId): int
    {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(correlativo), 0) + 1 FROM asientos WHERE empresa_id = ?");
        $stmt->execute([$empresaId]);
        return (int)$stmt->fetchColumn();
    }

    private static function cuentaIdPorCodigo(PDO $pdo, string $codigo): int
    {
        $stmt = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = ? AND empresa_id IS NULL LIMIT 1");
        $stmt->execute([$codigo]);
        $id = $stmt->fetchColumn();
        if (!$id) throw new Exception("Cuenta contable {$codigo} no existe en el catálogo.");
        return (int)$id;
    }
}
