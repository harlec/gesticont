<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
class EmpresaController {
    public function index(): void {
        Auth::require();
        $pageTitle = 'Mis empresas';
        ob_start();
        require_once ROOT . '/modules/empresas/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
    public function crear(): void {
        Auth::require();
        $pageTitle = 'Nueva empresa';
        ob_start();
        require_once ROOT . '/modules/empresas/views/crear.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
    public function store(): void {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /empresas/crear'); exit; }
        $model = new class extends Model { protected string $table = 'empresas'; };
        $id = $model->insert([
            'ruc'          => trim($_POST['ruc'] ?? ''),
            'razon_social' => trim($_POST['razon_social'] ?? ''),
            'regimen'      => $_POST['regimen'] ?? 'mype',
            'email'        => trim($_POST['email'] ?? ''),
            'telefono'     => trim($_POST['telefono'] ?? ''),
            'direccion'    => trim($_POST['direccion'] ?? ''),
            'activo'       => 1,
        ]);
        // Si viene con credenciales SOL, guardarlas
        if (!empty($_POST['sol_usuario']) && !empty($_POST['sol_clave'])) {
            require_once ROOT . '/services/EncryptService.php';
            $encrypt = new EncryptService();
            $certModel = new class extends Model { protected string $table = 'empresa_certificados'; };
            $certModel->insert([
                'empresa_id'     => $id,
                'cert_path'      => '',
                'cert_password'  => $encrypt->encrypt($_POST['cert_password'] ?? 'sin_cert'),
                'sol_usuario'    => $encrypt->encrypt($_POST['sol_usuario']),
                'sol_clave'      => $encrypt->encrypt($_POST['sol_clave']),
                'ambiente'       => $_POST['ambiente'] ?? 'beta',
                'estado'         => 'activo',
                'configurado_por'=> Auth::id(),
            ]);
        }
        header("Location: /empresas/{$id}");
        exit;
    }
    public function detalle(int $id): void {
        Auth::require();
        $model = new class extends Model { protected string $table = 'empresas'; };
        $empresa = $model->findById($id);
        if (!$empresa) { http_response_code(404); die('Empresa no encontrada'); }
        $pageTitle = $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/empresas/views/detalle.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
    public function editar(int $id): void {
        Auth::require();
        $model = new class extends Model { protected string $table = 'empresas'; };
        $empresa = $model->findById($id);
        if (!$empresa) { http_response_code(404); die('Empresa no encontrada'); }
        $pageTitle = 'Editar — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/empresas/views/editar.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }
    public function update(int $id): void {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$id}/editar"); exit; }
        $model = new class extends Model { protected string $table = 'empresas'; };
        $model->update($id, [
            'razon_social' => trim($_POST['razon_social'] ?? ''),
            'regimen'      => $_POST['regimen'] ?? 'mype',
            'email'        => trim($_POST['email'] ?? ''),
            'telefono'     => trim($_POST['telefono'] ?? ''),
        ]);
        header("Location: /empresas/{$id}");
        exit;
    }
}
