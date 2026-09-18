<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
require_once ROOT . '/services/EncryptService.php';

class CertificadoController {

    public function index(int $empresaId): void {
        Auth::require();
        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(404); die('Empresa no encontrada'); }

        $db = Model::db();
        $stmtC = $db->prepare("SELECT * FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' LIMIT 1");
        $stmtC->execute([$empresaId]);
        $cert = $stmtC->fetch(PDO::FETCH_ASSOC) ?: null;

        // Desencriptar api_client_id para mostrar si ya está configurado (solo los primeros chars)
        if ($cert && !empty($cert['api_client_id'])) {
            try {
                $encrypt = new EncryptService();
                $decoded = $encrypt->decrypt($cert['api_client_id']);
                // Mostrar solo que existe, no el valor completo
                $cert['api_client_id'] = $decoded ? 'CONFIGURADO' : null;
            } catch (Exception $e) {
                $cert['api_client_id'] = 'CONFIGURADO';
            }
        }

        $pageTitle = 'Credenciales — ' . $empresa['razon_social'];
        ob_start();
        require_once ROOT . '/modules/certificados/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    public function subir(int $empresaId): void {
        Auth::require();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /empresas/{$empresaId}/certificado"); exit;
        }

        $empresa = $this->_getEmpresa($empresaId);
        if (!$empresa) { http_response_code(403); die('Sin acceso'); }

        $db      = Model::db();
        $encrypt = new EncryptService();

        // Desactivar certificado anterior
        $db->prepare("UPDATE empresa_certificados SET estado = 'revocado' WHERE empresa_id = ? AND estado = 'activo'")->execute([$empresaId]);

        // Preparar datos a encriptar
        $data = [
            'empresa_id'     => $empresaId,
            'cert_path'      => '',
            'cert_password'  => $encrypt->encrypt($_POST['cert_password'] ?? 'sin_cert'),
            'sol_usuario'    => $encrypt->encrypt(trim($_POST['sol_usuario'] ?? '')),
            'sol_clave'      => $encrypt->encrypt(trim($_POST['sol_clave']   ?? '')),
            'ambiente'       => $_POST['ambiente'] ?? 'beta',
            'estado'         => 'activo',
            'configurado_por'=> Auth::id(),
        ];

        // Agregar credenciales API si se proporcionaron
        $apiClientId  = trim($_POST['api_client_id']     ?? '');
        $apiClientSec = trim($_POST['api_client_secret'] ?? '');

        if (!empty($apiClientId) && !empty($apiClientSec)) {
            $data['api_client_id']     = $encrypt->encrypt($apiClientId);
            $data['api_client_secret'] = $encrypt->encrypt($apiClientSec);
        }

        // Insertar
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $db->prepare("INSERT INTO empresa_certificados ({$cols}) VALUES ({$places})")->execute($data);

        header("Location: /empresas/{$empresaId}?ok=cert");
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
                WHERE e.id = ? AND e.activo = 1 AND eu.usuario_id = ?
            ");
            $stmt->execute([$id, Auth::id()]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
