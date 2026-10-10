<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    /** $roles: null = publik, ['*'] = semua yang login, atau daftar role. */
    public function add(string $method, string $pattern, string $handler, ?array $roles = ['*']): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'roles');
    }

    public function get(string $p, string $h, ?array $r = ['*']): void { $this->add('GET', $p, $h, $r); }
    public function post(string $p, string $h, ?array $r = ['*']): void { $this->add('POST', $p, $h, $r); }

    public function dispatch(string $method, string $path): void
    {
        $matchedPath = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) continue;
            $matchedPath = true;
            if ($r['method'] !== $method) continue;

            // Middleware: Auth lalu Role
            if ($r['roles'] !== null) {
                if (!Auth::check()) {
                    Flash::set('warning', 'Silakan login terlebih dahulu.');
                    redirect('/login');
                }
                if ($r['roles'] !== ['*'] && !in_array(Auth::user()['role'], $r['roles'], true)) {
                    throw new HttpException(403, 'Anda tidak memiliki akses ke halaman ini.');
                }
            }
            if ($method === 'POST' && !Csrf::valid($_POST['_token'] ?? '')) {
                throw new HttpException(403, 'Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
            }
            [$class, $action] = explode('@', $r['handler']);
            $class = 'App\\Controllers\\' . $class;
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            (new $class())->$action(...array_values($params));
            return;
        }
        throw new HttpException($matchedPath ? 405 : 404, $matchedPath ? 'Metode tidak diizinkan.' : 'Halaman tidak ditemukan.');
    }
}
