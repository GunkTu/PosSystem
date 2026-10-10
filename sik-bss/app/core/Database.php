<?php
namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            $c = config('database');
            self::$pdo = new PDO(
                "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4",
                $c['user'], $c['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        return self::$pdo;
    }

    public static function all(string $sql, array $p = []): array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }

    public static function one(string $sql, array $p = []): ?array
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        $r = $s->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $p = [])
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        $r = $s->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $p = []): int
    {
        $s = self::pdo()->prepare($sql);
        $s->execute($p);
        return $s->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
        $ph = implode(',', array_fill(0, count($data), '?'));
        self::exec("INSERT INTO `$table` ($cols) VALUES ($ph)", array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $pk, $id): void
    {
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
        self::exec("UPDATE `$table` SET $set WHERE `$pk`=?", [...array_values($data), $id]);
    }

    /** Paginasi: $from berisi "FROM ... WHERE ...". */
    public static function paged(string $select, string $from, array $params, string $order, int $page, int $per): array
    {
        $per = in_array($per, [10, 25, 50], true) ? $per : 10;
        $total = (int)self::val("SELECT COUNT(*) $from", $params);
        $pages = max(1, (int)ceil($total / $per));
        $page = min(max(1, $page), $pages);
        $off = ($page - 1) * $per;
        $rows = self::all("$select $from ORDER BY $order LIMIT $per OFFSET $off", $params);
        return compact('rows', 'total', 'page', 'per', 'pages');
    }
}
