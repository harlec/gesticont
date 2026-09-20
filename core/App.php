<?php
class App {
    public static function run(): void {
        if (getenv('APP_DEBUG') === 'true') { ini_set('display_errors',1); error_reporting(E_ALL); }
        else { ini_set('display_errors',0); error_reporting(0); }
        date_default_timezone_set('America/Lima');
        session_name(getenv('SESSION_NAME') ?: 'gesticont_session');
        session_start();
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        require_once ROOT . '/core/Router.php';
        $router = new Router();
        self::registerRoutes($router);
        $router->dispatch();
    }
    private static function registerRoutes(Router $router): void {
        $router->get('/',                           'auth/AuthController@loginForm');
        $router->get('/login',                      'auth/AuthController@loginForm');
        $router->post('/login',                     'auth/AuthController@login');
        $router->get('/logout',                     'auth/AuthController@logout');
        $router->get('/recuperar',                  'auth/AuthController@recuperarForm');
        $router->post('/recuperar',                 'auth/AuthController@recuperar');
        $router->get('/dashboard',                  'dashboard/DashboardController@index');
        $router->get('/empresas',                   'empresas/EmpresaController@index');
        $router->get('/empresas/crear',             'empresas/EmpresaController@crear');
        $router->post('/empresas/crear',            'empresas/EmpresaController@store');
        $router->get('/empresas/{id}',              'empresas/EmpresaController@detalle');
        $router->get('/empresas/{id}/editar',       'empresas/EmpresaController@editar');
        $router->post('/empresas/{id}/editar',      'empresas/EmpresaController@update');
        $router->get('/empresas/{id}/certificado',  'certificados/CertificadoController@index');
        $router->post('/empresas/{id}/certificado', 'certificados/CertificadoController@subir');
        // Sincronización SIRE
        $router->get('/empresas/{id}/sync',          'sync/SyncController@index');
        $router->post('/empresas/{id}/sync/ejecutar','sync/SyncController@ejecutar');
        // Motor contable — clasificación (imputación) manual
        $router->get('/empresas/{id}/imputacion',            'imputacion/ImputacionController@index');
        $router->post('/empresas/{id}/imputacion/clasificar','imputacion/ImputacionController@clasificar');
        // Motor contable — Libro Diario (generación de asientos)
        $router->get('/empresas/{id}/diario',          'diario/DiarioController@index');
        $router->post('/empresas/{id}/diario/generar', 'diario/DiarioController@generar');
        // Motor contable — Balance de Comprobación
        $router->get('/empresas/{id}/balance', 'balance/BalanceController@comprobacion');
        $router->get('/comprobantes',               'comprobantes/ComprobanteController@index');
        $router->get('/guias',                      'guias/GuiaController@index');
        $router->get('/declaraciones',              'declaraciones/DeclaracionController@index');
        $router->get('/alertas',                    'alertas/AlertaController@index');
        $router->get('/caja',                       'caja/CajaController@index');
        $router->get('/reportes',                   'reportes/ReporteController@index');
		// Registros ventas y compras
		$router->get('/empresas/{id}/ventas',  'registros/RegistroController@ventas');
		$router->get('/empresas/{id}/compras', 'registros/RegistroController@compras');
        // Usuarios
        $router->get('/usuarios',                   'usuarios/UsuarioController@index');
        $router->get('/usuarios/crear',             'usuarios/UsuarioController@crear');
        $router->post('/usuarios/crear',            'usuarios/UsuarioController@store');
        $router->get('/usuarios/{id}',              'usuarios/UsuarioController@detalle');
        $router->get('/usuarios/{id}/editar',       'usuarios/UsuarioController@editar');
        $router->post('/usuarios/{id}/update',      'usuarios/UsuarioController@update');
        $router->post('/usuarios/{id}/empresas',    'usuarios/UsuarioController@asignarEmpresas');
        $router->get('/usuarios/{id}/desactivar',   'usuarios/UsuarioController@desactivar');
    }
}
