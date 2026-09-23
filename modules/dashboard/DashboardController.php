<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/DashboardContadorService.php';
class DashboardController {
    public function index(): void {
        Auth::require();
        $dash = DashboardContadorService::generar(Auth::isSuperadmin(), Auth::id());

        $pageTitle = 'Panel principal';
        ob_start();
        require_once ROOT . '/modules/dashboard/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
}
