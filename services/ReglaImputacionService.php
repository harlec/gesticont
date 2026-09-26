<?php
/**
 * Motor de reglas de clasificación (Fase 2 de reglas_imputacion, tabla
 * creada en Fase 1 pero dejada inactiva). Dos niveles de sugerencia,
 * de más a menos específico:
 *
 *   1) Regla propia por RUC de la contraparte (reglas_imputacion) —
 *      "todo lo que compro a este RUC va a esta cuenta".
 *   2) Perfil comercial de la empresa (empresas.vende_tipo/compra_tipo) —
 *      fallback genérico solo cuando la empresa vende o compra un único
 *      tipo (producto O servicio, no "ambos" — ahí no hay forma de
 *      adivinar cuál aplica a un comprobante puntual).
 *
 * Nunca clasifica solo — arma "propuestas" agrupadas que la persona
 * aplica con un clic desde Clasificar, igual que la selección múltiple
 * manual que ya existía.
 */
class ReglaImputacionService
{
    /** Reglas de una empresa para un origen ('compra' | 'venta'), con datos de cuenta para mostrar. */
    public static function listar(int $empresaId, string $origen): array
    {
        $stmt = Model::db()->prepare("
            SELECT r.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
            FROM reglas_imputacion r
            JOIN cuentas_contables c ON c.id = r.cuenta_destino_id
            WHERE r.empresa_id = ? AND r.aplica_a = ?
            ORDER BY r.activa DESC, r.veces_aplicada DESC, r.id DESC
        ");
        $stmt->execute([$empresaId, $origen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Cuántas reglas tiene cada origen — para el contador de las pestañas. */
    public static function contarPorOrigen(int $empresaId): array
    {
        $stmt = Model::db()->prepare("SELECT aplica_a, COUNT(*) FROM reglas_imputacion WHERE empresa_id = ? GROUP BY aplica_a");
        $stmt->execute([$empresaId]);
        return ['compra' => 0, 'venta' => 0] + $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function crear(int $empresaId, string $origen, string $ruc, int $cuentaDestinoId, int $prioridad = 0): int
    {
        $pdo = Model::db();
        $stmt = $pdo->prepare("
            INSERT INTO reglas_imputacion (empresa_id, aplica_a, tipo_criterio, valor_criterio, cuenta_destino_id, prioridad, activa)
            VALUES (?, ?, 'ruc_contraparte', ?, ?, ?, 1)
        ");
        $stmt->execute([$empresaId, $origen, trim($ruc), $cuentaDestinoId, $prioridad]);
        return (int)$pdo->lastInsertId();
    }

    public static function eliminar(int $empresaId, int $reglaId): bool
    {
        return Model::db()->prepare("DELETE FROM reglas_imputacion WHERE id = ? AND empresa_id = ?")
            ->execute([$reglaId, $empresaId]);
    }

    public static function toggle(int $empresaId, int $reglaId): bool
    {
        return Model::db()->prepare("UPDATE reglas_imputacion SET activa = NOT activa WHERE id = ? AND empresa_id = ?")
            ->execute([$reglaId, $empresaId]);
    }

    /** Suma veces_aplicada tras un lote exitoso — nunca se decrementa. */
    public static function registrarUso(int $empresaId, int $reglaId, int $cantidad): void
    {
        if ($cantidad <= 0) return;
        Model::db()->prepare("UPDATE reglas_imputacion SET veces_aplicada = veces_aplicada + ? WHERE id = ? AND empresa_id = ?")
            ->execute([$cantidad, $reglaId, $empresaId]);
    }

    /**
     * Contrapartes (RUC) que se repiten en el historial de compras/ventas
     * y todavía no tienen una regla activa — candidatas a "detectar
     * proveedores frecuentes". No se limita al período activo: el patrón
     * ("todos los meses le compro a Entel") solo se ve mirando el
     * histórico completo. Salen primero los que más comprobantes
     * pendientes tienen; los de 1 solo comprobante también se listan.
     */
    public static function escanearContrapartes(int $empresaId, string $origen): array
    {
        $pdo = Model::db();

        $stmtRucsConRegla = $pdo->prepare("
            SELECT valor_criterio FROM reglas_imputacion
            WHERE empresa_id = ? AND aplica_a = ? AND tipo_criterio = 'ruc_contraparte' AND activa = 1
        ");
        $stmtRucsConRegla->execute([$empresaId, $origen]);
        $rucsConRegla = $stmtRucsConRegla->fetchAll(PDO::FETCH_COLUMN);

        if ($origen === 'venta') {
            $sql = "
                SELECT cliente_num_doc AS ruc, MAX(cliente_nombre) AS nombre, COUNT(*) AS apariciones,
                       SUM(total - igv) AS monto_total, MAX(fecha_emision) AS ultima,
                       SUM(estado_imputacion = 'pendiente') AS pendientes
                FROM registro_ventas
                WHERE empresa_id = ? AND cliente_num_doc IS NOT NULL AND cliente_num_doc != ''
                GROUP BY cliente_num_doc";
        } else {
            $sql = "
                SELECT proveedor_ruc AS ruc, MAX(proveedor_nombre) AS nombre, COUNT(*) AS apariciones,
                       SUM(total - igv) AS monto_total, MAX(fecha_emision) AS ultima,
                       SUM(estado_imputacion = 'pendiente') AS pendientes
                FROM registro_compras
                WHERE empresa_id = ? AND proveedor_ruc IS NOT NULL AND proveedor_ruc != ''
                GROUP BY proveedor_ruc";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$empresaId]);

        $candidatas = array_filter(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            fn($c) => !in_array($c['ruc'], $rucsConRegla, true)
        );

        usort($candidatas, fn($a, $b) => [$b['pendientes'], $b['apariciones']] <=> [$a['pendientes'], $a['apariciones']]);
        return array_slice(array_values($candidatas), 0, 100);
    }

    /**
     * Agrupa los comprobantes pendientes (misma forma que
     * ImputacionController::index() ya arma en $pendientes) por
     * contraparte y arma una propuesta por cada grupo que resuelve a una
     * cuenta — vía regla propia primero, perfil comercial después. Cada
     * propuesta trae ya los ids listos para mandar directo a
     * clasificar-lote.
     */
    public static function construirPropuestas(int $empresaId, array $empresa, array $pendientes): array
    {
        if (empty($pendientes)) return [];
        $pdo = Model::db();

        $stmtReglas = $pdo->prepare("
            SELECT r.id, r.aplica_a, r.valor_criterio AS ruc, r.cuenta_destino_id AS cuenta_id, c.codigo, c.nombre
            FROM reglas_imputacion r
            JOIN cuentas_contables c ON c.id = r.cuenta_destino_id
            WHERE r.empresa_id = ? AND r.tipo_criterio = 'ruc_contraparte' AND r.activa = 1
        ");
        $stmtReglas->execute([$empresaId]);
        $reglasPorRuc = [];
        foreach ($stmtReglas->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $reglasPorRuc[$r['aplica_a'] . ':' . $r['ruc']] = $r;
        }

        $perfilCuenta = fn(string $origen) => self::_perfilComercialCuenta($pdo, $empresa, $origen);

        // Grupo 1: por regla específica de RUC.
        $gruposRegla = [];
        // Grupo 2: por perfil comercial, solo para lo que ninguna regla cubrió.
        $gruposPerfil = ['venta' => null, 'compra' => null];

        foreach ($pendientes as $doc) {
            $ruc = $doc['contraparte_doc'] ?? '';
            $claveRegla = $doc['origen'] . ':' . $ruc;
            if ($ruc !== '' && isset($reglasPorRuc[$claveRegla])) {
                $clave = 'regla:' . $reglasPorRuc[$claveRegla]['id'];
                if (!isset($gruposRegla[$clave])) {
                    $regla = $reglasPorRuc[$claveRegla];
                    $gruposRegla[$clave] = [
                        'fuente' => 'regla', 'regla_id' => (int)$regla['id'],
                        'origen' => $doc['origen'], 'contraparte' => $doc['contraparte_nombre'], 'ruc' => $ruc,
                        'cuenta_id' => (int)$regla['cuenta_id'], 'cuenta_codigo' => $regla['codigo'], 'cuenta_nombre' => $regla['nombre'],
                        'documentos' => [], 'monto_total' => 0.0,
                    ];
                }
                $gruposRegla[$clave]['documentos'][] = ['origen' => $doc['origen'], 'documento_id' => (int)$doc['id']];
                $gruposRegla[$clave]['monto_total'] += (float)$doc['monto_neto'];
                continue;
            }

            $cuenta = $perfilCuenta($doc['origen']);
            if (!$cuenta) continue;

            if ($gruposPerfil[$doc['origen']] === null) {
                $gruposPerfil[$doc['origen']] = [
                    'fuente' => 'perfil', 'regla_id' => null, 'origen' => $doc['origen'],
                    'contraparte' => null, 'ruc' => null,
                    'cuenta_id' => $cuenta['id'], 'cuenta_codigo' => $cuenta['codigo'], 'cuenta_nombre' => $cuenta['nombre'],
                    'documentos' => [], 'monto_total' => 0.0,
                ];
            }
            $gruposPerfil[$doc['origen']]['documentos'][] = ['origen' => $doc['origen'], 'documento_id' => (int)$doc['id']];
            $gruposPerfil[$doc['origen']]['monto_total'] += (float)$doc['monto_neto'];
        }

        $propuestas = array_values($gruposRegla);
        foreach ($gruposPerfil as $grupo) {
            if ($grupo !== null) $propuestas[] = $grupo;
        }

        // Reglas primero (más específicas), luego perfil; dentro de cada
        // bloque, la que cubre más comprobantes primero.
        usort($propuestas, function ($a, $b) {
            if ($a['fuente'] !== $b['fuente']) return $a['fuente'] === 'regla' ? -1 : 1;
            return count($b['documentos']) <=> count($a['documentos']);
        });

        return $propuestas;
    }

    private static function _perfilComercialCuenta(PDO $pdo, array $empresa, string $origen): ?array
    {
        $tipo   = $origen === 'venta' ? ($empresa['vende_tipo'] ?? null) : ($empresa['compra_tipo'] ?? null);
        if ($tipo !== 'productos' && $tipo !== 'servicios') return null; // 'ambos' o sin definir: no se puede adivinar

        $campo = $origen === 'venta'
            ? ($tipo === 'productos' ? 'venta_tipo_gasto_producto_id' : 'venta_tipo_gasto_servicio_id')
            : ($tipo === 'productos' ? 'compra_tipo_gasto_producto_id' : 'compra_tipo_gasto_servicio_id');
        $tipoGastoId = $empresa[$campo] ?? null;
        if (!$tipoGastoId) return null;

        $stmt = $pdo->prepare("
            SELECT c.id, c.codigo, c.nombre
            FROM tipos_gasto tg JOIN cuentas_contables c ON c.id = tg.cuenta_id
            WHERE tg.id = ? AND tg.activo = 1 LIMIT 1
        ");
        $stmt->execute([$tipoGastoId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
