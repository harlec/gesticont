<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
class ReporteController {
    public function index(): void {
        Auth::require();
        $pageTitle = 'Reportes';
        echo '<div style="padding:40px;text-align:center;color:var(--gc-muted);">Módulo en desarrollo</div>';
    }
    public function store(): void { Auth::require(); header('Location: /' . strtolower(str_replace('Controller','','ReporteController'))); exit; }
    public function subir(): void { $this->store(); }
    public function resolver(int $id): void { Auth::require(); header('Location: /alertas'); exit; }
    public function detalle(int $id): void { $this->index(); }
    public function crear(): void { $this->index(); }
    public function emitir(): void { $this->store(); }
    public function ventas(): void { $this->index(); }
    public function comprobantes(): void { $this->index(); }
}
