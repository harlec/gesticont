<?php
/**
 * GestiCont — Dashboard del contador (panel global, portafolio de
 * empresas). Prioriza lo accionable (qué falta clasificar, qué período
 * quedó sin cerrar) sobre lo puramente informativo — un contador con
 * varias empresas necesita saber por dónde empezar el día, no solo un
 * conteo de cuántas empresas administra.
 */
class DashboardContadorService
{
    public static function generar(bool $esSuper, ?int $usuarioId): array
    {
        $pdo = Model::db();
        $filtro = $esSuper ? '' : 'INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id AND eu.usuario_id = ? AND eu.activo = 1';
        $params = $esSuper ? [] : [$usuarioId];

        // Empresas + pendientes por clasificar de cada una, en una sola
        // consulta — evita un loop de N queries por empresa.
        $stmt = $pdo->prepare("
            SELECT e.id, e.ruc, e.razon_social,
                   ec.cert_hasta,
                   CASE
                       WHEN ec.cert_hasta IS NULL THEN 'sin_cert'
                       WHEN ec.cert_hasta < CURDATE() THEN 'vencido'
                       WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7 THEN 'critico'
                       WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 30 THEN 'por_vencer'
                       ELSE 'vigente'
                   END AS estado_cert,
                   COALESCE(pv.cant, 0) + COALESCE(pc.cant, 0) AS pendientes,
                   COALESCE(pa.anios, '') AS anios_sin_cerrar
            FROM empresas e
            {$filtro}
            LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
            LEFT JOIN (SELECT empresa_id, COUNT(*) AS cant FROM registro_ventas  WHERE estado_imputacion='pendiente' AND estado_sunat='1' GROUP BY empresa_id) pv ON pv.empresa_id = e.id
            LEFT JOIN (SELECT empresa_id, COUNT(*) AS cant FROM registro_compras WHERE estado_imputacion='pendiente' AND estado_sunat='1' GROUP BY empresa_id) pc ON pc.empresa_id = e.id
            LEFT JOIN (
                SELECT empresa_id, GROUP_CONCAT(anio ORDER BY anio SEPARATOR ', ') AS anios
                FROM periodos_contables WHERE estado = 'abierto' AND anio < YEAR(CURDATE())
                GROUP BY empresa_id
            ) pa ON pa.empresa_id = e.id
            WHERE e.activo = 1
            ORDER BY pendientes DESC, e.razon_social
        ");
        $stmt->execute($params);
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalPendientes = array_sum(array_column($empresas, 'pendientes'));
        $empresasSinCerrar = array_values(array_filter($empresas, fn($e) => $e['anios_sin_cerrar'] !== ''));

        $stmtAlertas = $pdo->prepare("
            SELECT COUNT(*) FROM alertas a INNER JOIN empresas e ON e.id = a.empresa_id {$filtro}
            WHERE a.resuelta = 0
        ");
        $stmtAlertas->execute($params);
        $totalAlertas = (int)$stmtAlertas->fetchColumn();

        $stmtAl = $pdo->prepare("
            SELECT a.*, e.razon_social FROM alertas a
            JOIN empresas e ON e.id = a.empresa_id {$filtro}
            WHERE a.resuelta = 0 ORDER BY a.fecha_vence ASC LIMIT 5
        ");
        $stmtAl->execute($params);
        $alertas = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

        return [
            'empresas'           => $empresas,
            'total_empresas'     => count($empresas),
            'total_pendientes'   => (int)$totalPendientes,
            'empresas_sin_cerrar'=> $empresasSinCerrar,
            'total_alertas'      => $totalAlertas,
            'alertas'            => $alertas,
        ];
    }
}
