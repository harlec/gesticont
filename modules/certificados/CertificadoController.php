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

        // Qué campos ya están guardados — la vista solo muestra un indicador,
        // nunca el valor (van encriptados y no se devuelven al navegador).
        $tiene = $this->_camposGuardados($cert);

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

        $stmtPrev = $db->prepare("SELECT * FROM empresa_certificados WHERE empresa_id = ? AND estado = 'activo' ORDER BY id DESC LIMIT 1");
        $stmtPrev->execute([$empresaId]);
        $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC) ?: [];

        // Un campo vacío conserva el valor ya guardado (encriptado tal cual);
        // solo lo que se escribe de nuevo se vuelve a encriptar. Sin esto,
        // guardar con las credenciales de API en blanco las borraba.
        $valor = function (string $campoPost, string $campoBd, ?string $porDefecto = null) use ($encrypt, $prev): ?string {
            $nuevo = trim($_POST[$campoPost] ?? '');
            if ($nuevo !== '') return $encrypt->encrypt($nuevo);
            if (!empty($prev[$campoBd])) return $prev[$campoBd];
            return $porDefecto !== null ? $encrypt->encrypt($porDefecto) : null;
        };

        $solUsuario = $valor('sol_usuario', 'sol_usuario');
        $solClave   = $valor('sol_clave', 'sol_clave');
        if ($solUsuario === null || $solClave === null) {
            $_SESSION['certificado_error'] = 'Falta el usuario o la clave SOL.';
            header("Location: /empresas/{$empresaId}/certificado"); exit;
        }

        // Desactivar certificado anterior
        $db->prepare("UPDATE empresa_certificados SET estado = 'revocado' WHERE empresa_id = ? AND estado = 'activo'")->execute([$empresaId]);

        $data = [
            'empresa_id'     => $empresaId,
            'cert_path'      => '',
            'cert_password'  => $valor('cert_password', 'cert_password', 'sin_cert'),
            'sol_usuario'    => $solUsuario,
            'sol_clave'      => $solClave,
            'ambiente'       => in_array($_POST['ambiente'] ?? '', ['beta', 'produccion'], true) ? $_POST['ambiente'] : 'beta',
            'estado'         => 'activo',
            'configurado_por'=> Auth::id(),
        ];

        // Credenciales API: solo se guardan si al final hay ID y clave
        // (los nuevos, o los que ya estaban).
        $apiId  = $valor('api_client_id', 'api_client_id');
        $apiSec = $valor('api_client_secret', 'api_client_secret');
        if ($apiId !== null && $apiSec !== null) {
            $data['api_client_id']     = $apiId;
            $data['api_client_secret'] = $apiSec;
        }

        // Insertar
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_map(fn($k) => ":$k", array_keys($data)));
        $db->prepare("INSERT INTO empresa_certificados ({$cols}) VALUES ({$places})")->execute($data);

        header("Location: /empresas/{$empresaId}?ok=cert");
        exit;
    }

    /** @return array<string,bool> */
    private function _camposGuardados(?array $cert): array
    {
        $campos = ['sol_usuario', 'sol_clave', 'cert_password', 'api_client_id', 'api_client_secret'];
        $tiene  = array_fill_keys($campos, false);
        if (!$cert) return $tiene;

        try { $encrypt = new EncryptService(); } catch (Throwable $e) { $encrypt = null; }

        foreach ($campos as $c) {
            if (empty($cert[$c])) continue;
            try {
                $plano = $encrypt ? $encrypt->decrypt($cert[$c]) : 'x';
            } catch (Throwable $e) {
                $plano = 'x'; // hay algo guardado aunque no se pueda verificar
            }
            // 'sin_cert' es el marcador de "no tiene .pfx", no un dato real.
            $tiene[$c] = $plano !== '' && $plano !== 'sin_cert';
        }
        return $tiene;
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
