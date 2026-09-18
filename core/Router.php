<?php
class Router {
    private array $routes = [];
    public function get(string $path, string $handler): void { $this->routes['GET'][$path] = $handler; }
    public function post(string $path, string $handler): void { $this->routes['POST'][$path] = $handler; }
    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') ?: '/';
        if (isset($this->routes[$method][$uri])) { $this->execute($this->routes[$method][$uri], []); return; }
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = '#^' . preg_replace('/\{[a-z_]+\}/', '([^/]+)', $route) . '$#';
            if (preg_match($pattern, $uri, $matches)) { array_shift($matches); $this->execute($handler, $matches); return; }
        }
        http_response_code(404);
        if (file_exists(ROOT . '/views/errors/404.php')) require ROOT . '/views/errors/404.php';
        else echo '<h1>404 - No encontrado</h1>';
    }
    private function execute(string $handler, array $params): void {
        [$controllerPath, $method] = explode('@', $handler);
        $file = ROOT . '/modules/' . $controllerPath . '.php';
        $parts = explode('/', $controllerPath);
        $className = end($parts);
        require_once ROOT . '/core/Model.php';
        require_once ROOT . '/core/Auth.php';
        if (!file_exists($file)) { http_response_code(500); echo "Controller not found: $file"; return; }
        require_once $file;
        if (!class_exists($className)) { http_response_code(500); echo "Class not found: $className"; return; }
        $controller = new $className();
        if (!method_exists($controller, $method)) { http_response_code(500); echo "Method not found: $method"; return; }
        call_user_func_array([$controller, $method], $params);
    }
}
