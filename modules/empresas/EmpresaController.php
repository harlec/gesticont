<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/core/Periodo.php';
require_once ROOT . '/services/DashboardEmpresaService.php';
class EmpresaController {
    public function index(): void {
        Auth::require();
        $db = Model::db();

        if (Auth::isSuperadmin()) {
            $stmt = $db->prepare("
                SELECT e.*, ec.cert_hasta, ec.estado as cert_estado,
                       DATEDIFF(ec.cert_hasta, CURDATE()) as dias_cert
                FROM empresas e
                LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
                WHERE e.activo = 1
                ORDER BY e.razon_social
            ");
            $stmt->execute();
        } else {
            // Cada usuario (contador o su operador/asistente) solo ve las
            // empresas donde tiene una fila en empresa_usuarios — nunca
            // el listado completo del sistema.
            $stmt = $db->prepare("
                SELECT e.*, ec.cert_hasta, ec.estado as cert_estado,
                       DATEDIFF(ec.cert_hasta, CURDATE()) as dias_cert
                FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                LEFT JOIN empresa_certificados ec ON ec.empresa_id = e.id AND ec.estado = 'activo'
                WHERE e.activo = 1 AND eu.usuario_id = ? AND eu.activo = 1
                ORDER BY e.razon_social
            ");
            $stmt->execute([Auth::id()]);
        }
        $empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        $db = Model::db();
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

        // Quien crea la empresa queda como su administrador — si no se
        // hiciera esto, un contador (no-superadmin) perdería el acceso a
        // la empresa que acaba de dar de alta, porque el resto del sistema
        // exige una fila en empresa_usuarios para poder verla.
        $db->prepare("
            INSERT INTO empresa_usuarios (empresa_id, usuario_id, rol, activo)
            VALUES (?, ?, 'admin', 1)
        ")->execute([$id, Auth::id()]);

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
        $empresa = $this->_getEmpresa($id);
        if (!$empresa) { http_response_code(404); die('Empresa no encontrada o sin acceso'); }

        $periodoActivo = Periodo::resolver($id);
        $anio = (int)($_GET['anio'] ?? substr($periodoActivo, 0, 4));
        $dash = DashboardEmpresaService::generar($id, $anio, $periodoActivo);

        $stmtCert = Model::db()->prepare("SELECT * FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1");
        $stmtCert->execute([$id]);
        $cert = $stmtCert->fetch(PDO::FETCH_ASSOC);

        $pageTitle = $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/empresas/views/detalle.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function editar(int $id): void {
        Auth::require();
        $empresa = $this->_getEmpresa($id);
        if (!$empresa) { http_response_code(404); die('Empresa no encontrada o sin acceso'); }
        $pageTitle = 'Editar — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/empresas/views/editar.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function update(int $id): void {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header("Location: /empresas/{$id}/editar"); exit; }
        $empresa = $this->_getEmpresa($id);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

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

    private function _getEmpresa(int $id): ?array {
        $db = Model::db();
        if (Auth::isSuperadmin()) {
            $stmt = $db->prepare("SELECT * FROM empresas WHERE id = ? AND activo = 1");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("
                SELECT e.* FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ? AND eu.activo = 1
            ");
            $stmt->execute([$id, Auth::id()]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
