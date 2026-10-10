<?php
namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function valid(string $t): bool
    {
        return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
    }
}
