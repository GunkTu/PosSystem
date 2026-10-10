<?php
namespace App\Core;

class Auth
{
    public static function check(): bool
    {
        if (empty($_SESSION['user'])) return false;
        $timeout = config('app')['session_timeout'];
        if (time() - ($_SESSION['last_activity'] ?? 0) > $timeout) {
            self::logout();
            session_start();
            Flash::set('warning', 'Sesi berakhir karena tidak aktif. Silakan login kembali.');
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }
    public static function id(): ?int { return $_SESSION['user']['id_user'] ?? null; }
    public static function isAdmin(): bool { return (self::user()['role'] ?? '') === 'admin'; }

    /** @return string|null pesan error, null jika berhasil */
    public static function attempt(string $username, string $password): ?string
    {
        $cfg = config('app');
        $key = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($username));
        $file = STORAGE_PATH . '/logs/throttle.json';
        $data = is_file($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
        $now = time();
        foreach ($data as $k => $v) if ($v['until'] < $now && $v['t'] < $now - 3600) unset($data[$k]);
        $rec = $data[$key] ?? ['n' => 0, 'until' => 0, 't' => $now];
        if ($rec['until'] > $now) {
            return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil(($rec['until'] - $now) / 60) . ' menit.';
        }

        $u = Database::one('SELECT * FROM pengguna WHERE username = ?', [$username]);
        $ok = $u && $u['status_pengguna'] === 'aktif' && password_verify($password, $u['password']);
        if (!$ok) {
            // jalankan hash dummy agar waktu respons relatif sama
            if (!$u) password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234');
            $rec['n']++; $rec['t'] = $now;
            if ($rec['n'] >= $cfg['login_max_fail']) { $rec['until'] = $now + $cfg['login_lock_sec']; $rec['n'] = 0; }
            $data[$key] = $rec;
            @file_put_contents($file, json_encode($data), LOCK_EX);
            return 'Username atau password salah, atau akun tidak aktif.';
        }
        unset($data[$key]);
        @file_put_contents($file, json_encode($data), LOCK_EX);

        session_regenerate_id(true);
        $_SESSION['user'] = ['id_user' => (int)$u['id_user'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
        $_SESSION['last_activity'] = time();
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
