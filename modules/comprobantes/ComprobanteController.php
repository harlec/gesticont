<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
class ComprobanteController {
    public function index(): void {
        Auth::require();
        $pageTitle = 'Comprobantes';
        ob_start();
        require_once ROOT . '/modules/comprobantes/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
    public function store(): void { Auth::require(); header('Location: /' . strtolower(str_replace('Controller','','ComprobanteController'))); exit; }
    public function subir(): void { $this->store(); }
    public function resolver(int $id): void { Auth::require(); header('Location: /alertas'); exit; }
    public function detalle(int $id): void { $this->index(); }
    public function crear(): void { $this->index(); }
    public function emitir(): void { $this->store(); }
    public function ventas(): void { $this->index(); }
    public function comprobantes(): void { $this->index(); }
}
