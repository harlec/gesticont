<?php
require_once ROOT . '/core/Auth.php';
require_once ROOT . '/core/Model.php';
class AuthController {
    public function loginForm(): void {
        if (Auth::check()) { header('Location: /dashboard'); exit; }
        $error = $_GET['error'] ?? null;
        $timeout = $_GET['timeout'] ?? null;
        require_once ROOT . '/modules/auth/views/login.php';
    }
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /login'); exit; }
        if (!isset($_POST['_token']) || $_POST['_token'] !== ($_SESSION['csrf_token'] ?? '')) { header('Location: /login?error=csrf'); exit; }
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if (empty($email) || empty($password)) { header('Location: /login?error=campos'); exit; }
        $model = new class extends Model { protected string $table = 'usuarios'; };
        $user = $model->findOne(['email' => $email, 'activo' => 1]);
        if (!$user || !password_verify($password, $user['password'])) { header('Location: /login?error=credenciales'); exit; }
        $model->update($user['id'], ['ultimo_acceso' => date('Y-m-d H:i:s')]);
        Auth::login($user);
        header('Location: /dashboard');
        exit;
    }
    public function logout(): void { Auth::logout(); header('Location: /login'); exit; }
    public function recuperarForm(): void {
        if (Auth::check()) { header('Location: /dashboard'); exit; }
        $msg = $_GET['msg'] ?? null;
        require_once ROOT . '/modules/auth/views/recuperar.php';
    }
    public function recuperar(): void {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) { header('Location: /recuperar?msg=error'); exit; }
        $model = new class extends Model { protected string $table = 'usuarios'; };
        $user = $model->findOne(['email' => $email, 'activo' => 1]);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $model->update($user['id'], ['token_reset' => $token, 'token_expira' => date('Y-m-d H:i:s', strtotime('+1 hour'))]);
        }
        header('Location: /recuperar?msg=enviado');
        exit;
    }
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
