<?php
use App\Core\Csrf;
use App\Core\Flash;

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function base_url(): string {
    return $GLOBALS['BASE_URL'] ?? '';
}
function url(string $path = ''): string { return base_url() . '/' . ltrim($path, '/'); }

function redirect(string $path): never {
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function csrf_field(): string { return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">'; }

function old(string $key, $default = '') {
    return $_SESSION['_old'][$key] ?? $default;
}

function rupiah($n): string { return 'Rp ' . number_format((float)$n, 2, ',', '.'); }
function angka($n): string { return number_format((float)$n, 2, ',', '.'); }

function tgl(?string $d): string {
    if (!$d) return '-';
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    return date('d', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t);
}

function badge(string $status): string {
    $map = [
        'lunas' => ['success', 'Lunas'], 'sebagian' => ['warning', 'Sebagian'],
        'belum_lunas' => ['danger-subtle text-danger-emphasis', 'Belum Lunas'],
        'batal' => ['secondary', 'Batal'], 'nonaktif' => ['secondary', 'Nonaktif'],
        'aktif' => ['success', 'Aktif'],
    ];
    [$cls, $label] = $map[$status] ?? ['secondary', ucfirst($status)];
    return '<span class="badge text-bg-' . $cls . '">' . e($label) . '</span>';
}

function is_active_menu(string $prefix): string {
    $p = $GLOBALS['CURRENT_PATH'] ?? '/';
    return ($p === $prefix || str_starts_with($p, rtrim($prefix, '/') . '/')) ? 'active' : '';
}

/** Muat ulang query string dengan perubahan tertentu (untuk paginasi). */
function qs(array $override = []): string {
    $q = array_merge($_GET, $override);
    foreach ($q as $k => $v) if ($v === '' || $v === null) unset($q[$k]);
    return $q ? '?' . http_build_query($q) : '';
}

function partial(string $name, array $data = []): void {
    extract($data);
    include APP_PATH . '/views/partials/' . $name . '.php';
}

function config(string $file): array {
    static $cache = [];
    return $cache[$file] ??= require APP_PATH . '/config/' . $file . '.php';
}

/** Validasi tanggal Y-m-d. */
function valid_date(?string $d): bool {
    if (!$d) return false;
    $x = DateTime::createFromFormat('Y-m-d', $d);
    return $x && $x->format('Y-m-d') === $d;
}

function dec($v): float { return round((float)$v, 2); }
