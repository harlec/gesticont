<?php
define('ROOT', __DIR__);
define('VERSION', '1.0.0');
define('APP_NAME', 'GestiCont');
if (file_exists(ROOT . '/vendor/autoload.php')) require_once ROOT . '/vendor/autoload.php';
if (file_exists(ROOT . '/.env')) {
    $env = parse_ini_file(ROOT . '/.env', false, INI_SCANNER_RAW);
    foreach ($env as $k => $v) putenv("$k=$v");
}
require_once ROOT . '/core/App.php';
App::run();
