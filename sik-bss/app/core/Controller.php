<?php
namespace App\Core;

abstract class Controller
{
    protected function view(string $name, array $data = [], string $layout = 'app'): void
    {
        $data['title'] ??= '';
        extract($data);
        ob_start();
        include APP_PATH . '/views/' . $name . '.php';
        $content = ob_get_clean();
        include APP_PATH . '/views/layouts/' . $layout . '.php';
        unset($_SESSION['_old']);
    }

    protected function input(string $key, $default = '')
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** Simpan input untuk ditampilkan ulang saat validasi gagal. */
    protected function keepOld(): void { $_SESSION['_old'] = $_POST; }

    protected function back(string $fallback): never
    {
        redirect($fallback);
    }

    protected function fail(string $msg, string $to): never
    {
        $this->keepOld();
        Flash::set('danger', $msg);
        redirect($to);
    }

    protected function page(): int { return max(1, (int)($_GET['page'] ?? 1)); }
    protected function per(): int { return (int)($_GET['per'] ?? 10); }
}
