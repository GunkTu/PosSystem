<?php
declare(strict_types=1);

// Server bawaan PHP (php -S): biarkan file statis dilayani langsung
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f)) return false;
}

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');

require ROOT_PATH . '/vendor/autoload.php';
require APP_PATH . '/core/helpers.php';

// Autoloader App\Controllers\X -> app/controllers/X.php
spl_autoload_register(function (string $class) {
    if (!str_starts_with($class, 'App\\')) return;
    $parts = explode('\\', substr($class, 4));
    $file = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $path = APP_PATH . '/' . ($dir ? $dir . '/' : '') . $file . '.php';
    if (is_file($path)) require $path;
});

use App\Core\{Router, HttpException, Auth, Flash};

$cfg = config('app');
date_default_timezone_set($cfg['timezone']);
ini_set('display_errors', $cfg['debug'] ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
error_reporting(E_ALL);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if ($cfg['force_https'] && !$https) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
if ($https) header('Strict-Transport-Security: max-age=31536000');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
session_name('SIKBSSID');
session_start();

// Base URL & path
// Base URL: folder skrip (…/public) atau induknya bila diakses lewat .htaccess di root proyek (…/sik-bss/)
$script = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$path = '/' . trim((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$base = $script;
$underPublic = $script === '' || $path === $script || str_starts_with($path, $script . '/');
if (!$underPublic && str_ends_with($script, '/public')) {
    $base = substr($script, 0, -7);
}
$GLOBALS['BASE_URL'] = $base;
if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
    $path = substr($path, strlen($base)) ?: '/';
}
if (str_starts_with($path, '/index.php')) $path = substr($path, 10) ?: '/';
$path = '/' . trim($path, '/');
$GLOBALS['CURRENT_PATH'] = $path;

$router = new Router();
require APP_PATH . '/routes.php';

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
} catch (HttpException $e) {
    http_response_code($e->status);
    $status = $e->status; $message = $e->getMessage();
    include APP_PATH . '/views/errors/http.php';
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    $status = 500;
    $message = $cfg['debug'] ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'Terjadi kesalahan pada server.';
    include APP_PATH . '/views/errors/http.php';
}
