<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
class DashboardController {
    public function index(): void {
        Auth::require();
        $db = Model::db();
        $esSuper = Auth::isSuperadmin();

        // Todo lo que se muestra aquí se escopa a las empresas del usuario
        // actual (empresa_usuarios) — el superadmin sigue viendo el total
        // del sistema, igual que en el resto de la app.
        $filtroEmpresa = $esSuper ? '' : 'INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id AND eu.usuario_id = ? AND eu.activo = 1';
        $params = $esSuper ? [] : [Auth::id()];

        $stmtCount = $db->prepare("SELECT COUNT(DISTINCT e.id) FROM empresas e {$filtroEmpresa} WHERE e.activo = 1");
        $stmtCount->execute($params);
        $totalEmpresas = (int)$stmtCount->fetchColumn();

        $stmtAlertas = $db->prepare("
            SELECT COUNT(*) FROM alertas a
            INNER JOIN empresas e ON e.id = a.empresa_id {$filtroEmpresa}
            WHERE a.resuelta = 0
        ");
        $stmtAlertas->execute($params);
        $totalAlertas = (int)$stmtAlertas->fetchColumn();

        $stmtEmp = $db->prepare("
            SELECT e.id, e.ruc, e.razon_social, e.regimen,
                   ec.cert_hasta,
                   DATEDIFF(ec.cert_hasta, CURDATE()) as dias_cert,
                   CASE
                       WHEN ec.cert_hasta IS NULL THEN 'sin_cert'
                       WHEN ec.cert_hasta < CURDATE() THEN 'vencido'
                       WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 7 THEN 'critico'
                       WHEN DATEDIFF(ec.cert_hasta, CURDATE()) <= 30 THEN 'por_vencer'
                       ELSE 'vigente'
                   END as estado_cert
            FROM empresas e
            {$filtroEmpresa}
            LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
            WHERE e.activo = 1
            ORDER BY e.razon_social
            LIMIT 10
        ");
        $stmtEmp->execute($params);
        $empresas = $stmtEmp->fetchAll(PDO::FETCH_ASSOC);

        $stmtAl = $db->prepare("
            SELECT a.*, e.razon_social
            FROM alertas a
            JOIN empresas e ON e.id = a.empresa_id
            {$filtroEmpresa}
            WHERE a.resuelta = 0
            ORDER BY a.fecha_vence ASC
            LIMIT 5
        ");
        $stmtAl->execute($params);
        $alertas = $stmtAl->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = 'Panel principal';
        ob_start();
        require_once ROOT . '/modules/dashboard/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
}
