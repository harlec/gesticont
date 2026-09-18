<?php
class Auth {
    public static function check(): bool { return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0; }
    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): ?int { return $_SESSION['user_id'] ?? null; }
    public static function rol(): ?string { return $_SESSION['user']['rol'] ?? null; }
    public static function isSuperadmin(): bool { return self::rol() === 'superadmin'; }
    public static function isContador(): bool { return in_array(self::rol(), ['superadmin','contador']); }
    public static function empresaId(): ?int { return $_SESSION['empresa_id'] ?? null; }
    public static function setEmpresa(int $id): void { $_SESSION['empresa_id'] = $id; }
    public static function login(array $user): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = ['id'=>$user['id'],'nombre'=>$user['nombre'],'email'=>$user['email'],'rol'=>$user['rol']];
        $_SESSION['login_at'] = time();
    }
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']); }
        session_destroy();
    }
    public static function require(): void {
        if (!self::check()) { header('Location: /login'); exit; }
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 480) * 60;
        if (isset($_SESSION['login_at']) && (time() - $_SESSION['login_at']) > $lifetime) { self::logout(); header('Location: /login?timeout=1'); exit; }
    }
    public static function requireRol(string ...$roles): void {
        self::require();
        if (!in_array(self::rol(), $roles)) { http_response_code(403); die('403 Forbidden'); }
    }
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
}
