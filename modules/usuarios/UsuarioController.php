<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';

class UsuarioController {

    // ── Listar usuarios ──────────────────────────
    public function index(): void {
        Auth::requireRol('superadmin', 'contador');
        $db = Model::db();

        if (Auth::isSuperadmin()) {
            // Superadmin ve TODOS
            $stmt = $db->prepare("
                SELECT u.*,
                       COUNT(DISTINCT eu.empresa_id) as total_empresas
                FROM usuarios u
                LEFT JOIN empresa_usuarios eu ON eu.usuario_id = u.id
                WHERE u.activo = 1
                GROUP BY u.id
                ORDER BY FIELD(u.rol,'superadmin','contador','operador','cliente'), u.nombre
            ");
            $stmt->execute();
        } else {
            // Contador ve solo usuarios que ÉL creó
            $stmt = $db->prepare("
                SELECT u.*,
                       COUNT(DISTINCT eu.empresa_id) as total_empresas
                FROM usuarios u
                LEFT JOIN empresa_usuarios eu ON eu.usuario_id = u.id
                WHERE u.activo = 1
                  AND u.creado_por = ?
                GROUP BY u.id
                ORDER BY u.nombre
            ");
            $stmt->execute([Auth::id()]);
        }
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pageTitle = 'Usuarios';
        ob_start();
        require_once ROOT . '/modules/usuarios/views/index.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    // ── Formulario crear ─────────────────────────
    public function crear(): void {
        Auth::requireRol('superadmin', 'contador');
        $db  = Model::db();

        // Empresas disponibles según rol
        $empresas = $this->_getEmpresasDisponibles($db);

        $error    = $_GET['error'] ?? null;
        $pageTitle = 'Nuevo usuario';
        ob_start();
        require_once ROOT . '/modules/usuarios/views/crear.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    // ── Guardar nuevo usuario ────────────────────
    public function store(): void {
        Auth::requireRol('superadmin', 'contador');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /usuarios/crear'); exit;
        }

        $nombre   = trim($_POST['nombre']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol      = $_POST['rol']           ?? 'operador';
        $empresas = $_POST['empresas']      ?? [];

        if (empty($nombre) || empty($email) || empty($password)) {
            header('Location: /usuarios/crear?error=campos'); exit;
        }

        // Contador solo puede crear operadores y clientes
        if (!Auth::isSuperadmin() && in_array($rol, ['superadmin', 'contador'])) {
            $rol = 'operador';
        }

        $db = Model::db();

        // Email único
        $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            header('Location: /usuarios/crear?error=email'); exit;
        }

        // Insertar usuario — guardamos creado_por
        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre, email, password, rol, activo, creado_por, created_at)
            VALUES (?, ?, ?, ?, 1, ?, NOW())
        ");
        $stmt->execute([
            $nombre,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $rol,
            Auth::id(),
        ]);
        $userId = (int) $db->lastInsertId();

        // Asignar empresas seleccionadas
        if (!empty($empresas)) {
            $rolEmp = ($rol === 'contador') ? 'admin' : 'operador';
            $ins = $db->prepare("INSERT IGNORE INTO empresa_usuarios (empresa_id, usuario_id, rol, activo) VALUES (?, ?, ?, 1)");
            foreach ($empresas as $empId) {
                $empId = (int) $empId;
                if ($empId > 0) $ins->execute([$empId, $userId, $rolEmp]);
            }
        }

        header('Location: /usuarios?ok=creado'); exit;
    }

    // ── Detalle / gestionar ──────────────────────
    public function detalle(int $id): void {
        Auth::requireRol('superadmin', 'contador');
        $db = Model::db();

        $usuario = $this->_getUsuario($db, $id);
        if (!$usuario) { http_response_code(404); die('Usuario no encontrado o sin acceso'); }

        // Empresas ya asignadas a este usuario
        $stmtEU = $db->prepare("
            SELECT e.id, e.ruc, e.razon_social, eu.rol as rol_empresa
            FROM empresa_usuarios eu
            JOIN empresas e ON e.id = eu.empresa_id
            WHERE eu.usuario_id = ? AND e.activo = 1
            ORDER BY e.razon_social
        ");
        $stmtEU->execute([$id]);
        $empresasUsuario = $stmtEU->fetchAll(PDO::FETCH_ASSOC);
        $asignadasIds    = array_column($empresasUsuario, 'id');

        // Empresas disponibles para asignar
        $todasEmpresas = $this->_getEmpresasDisponibles($db);

        $pageTitle = 'Usuario: ' . $usuario['nombre'];
        ob_start();
        require_once ROOT . '/modules/usuarios/views/detalle.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    // ── Editar datos básicos del usuario ─────────
    public function editar(int $id): void {
        Auth::requireRol('superadmin', 'contador');
        $db      = Model::db();
        $usuario = $this->_getUsuario($db, $id);
        if (!$usuario) { http_response_code(404); die('Usuario no encontrado'); }

        $error     = $_GET['error'] ?? null;
        $pageTitle = 'Editar usuario';
        ob_start();
        require_once ROOT . '/modules/usuarios/views/editar.php';
        $content = ob_get_clean();
        require_once ROOT . '/views/layout/base.php';
    }

    // ── Guardar edición ──────────────────────────
    public function update(int $id): void {
        Auth::requireRol('superadmin', 'contador');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /usuarios/{$id}/editar"); exit;
        }

        $db      = Model::db();
        $usuario = $this->_getUsuario($db, $id);
        if (!$usuario) { http_response_code(403); die('Sin acceso'); }

        $nombre   = trim($_POST['nombre']   ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($nombre) || empty($email)) {
            header("Location: /usuarios/{$id}/editar?error=campos"); exit;
        }

        // Email único excepto el propio
        $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
            header("Location: /usuarios/{$id}/editar?error=email"); exit;
        }

        $data = ['nombre' => $nombre, 'email' => $email];
        if (!empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sets = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $data['id'] = $id;
        $db->prepare("UPDATE usuarios SET {$sets} WHERE id = :id")->execute($data);

        header("Location: /usuarios/{$id}?ok=editado"); exit;
    }

    // ── Actualizar empresas asignadas ─────────────
    public function asignarEmpresas(int $id): void {
        Auth::requireRol('superadmin', 'contador');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: /usuarios/{$id}"); exit;
        }

        $db      = Model::db();
        $usuario = $this->_getUsuario($db, $id);
        if (!$usuario) { http_response_code(403); die('Sin acceso'); }

        $empresas = $_POST['empresas'] ?? [];

        // Obtener IDs de empresas que tiene permiso de asignar
        $permitidas = array_column($this->_getEmpresasDisponibles($db), 'id');

        // Eliminar solo las asignaciones de empresas que puede gestionar
        if (!empty($permitidas)) {
            $placeholders = implode(',', array_fill(0, count($permitidas), '?'));
            $db->prepare("
                DELETE FROM empresa_usuarios
                WHERE usuario_id = ?
                AND empresa_id IN ({$placeholders})
            ")->execute(array_merge([$id], $permitidas));
        }

        // Insertar nuevas
        if (!empty($empresas)) {
            $rolEmp = ($usuario['rol'] === 'contador') ? 'admin' : 'operador';
            $ins    = $db->prepare("INSERT IGNORE INTO empresa_usuarios (empresa_id, usuario_id, rol, activo) VALUES (?, ?, ?, 1)");
            foreach ($empresas as $empId) {
                $empId = (int) $empId;
                if ($empId > 0 && in_array($empId, $permitidas)) {
                    $ins->execute([$empId, $id, $rolEmp]);
                }
            }
        }

        header("Location: /usuarios/{$id}?ok=empresas"); exit;
    }

    // ── Desactivar usuario ───────────────────────
    public function desactivar(int $id): void {
        Auth::requireRol('superadmin', 'contador');
        if ($id === Auth::id()) { header('Location: /usuarios?error=self'); exit; }

        $db      = Model::db();
        $usuario = $this->_getUsuario($db, $id);
        if (!$usuario) { http_response_code(403); die('Sin acceso'); }

        $db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?")->execute([$id]);
        header('Location: /usuarios?ok=desactivado'); exit;
    }

    // ── Helpers privados ─────────────────────────

    // Obtener usuario verificando permisos de acceso
    private function _getUsuario(\PDO $db, int $id): ?array {
        if (Auth::isSuperadmin()) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1");
            $stmt->execute([$id]);
        } else {
            // Contador solo puede gestionar usuarios que él creó
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE id = ? AND activo = 1 AND creado_por = ?");
            $stmt->execute([$id, Auth::id()]);
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Empresas disponibles según rol del usuario actual
    private function _getEmpresasDisponibles(\PDO $db): array {
        if (Auth::isSuperadmin()) {
            $stmt = $db->prepare("SELECT id, ruc, razon_social FROM empresas WHERE activo = 1 ORDER BY razon_social");
            $stmt->execute();
        } else {
            // Contador solo puede asignar SUS empresas
            $stmt = $db->prepare("
                SELECT e.id, e.ruc, e.razon_social
                FROM empresas e
                INNER JOIN empresa_usuarios eu ON eu.empresa_id = e.id
                WHERE eu.usuario_id = ? AND e.activo = 1
                ORDER BY e.razon_social
            ");
            $stmt->execute([Auth::id()]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
