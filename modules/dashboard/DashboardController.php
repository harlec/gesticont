<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/DashboardContadorService.php';
class DashboardController {
    /** El panel se unificó con "Mis empresas" (rediseño): la entrada del sistema es una sola pantalla. */
    public function index(): void {
        Auth::require();
        header('Location: /empresas'); exit;
    }
}
