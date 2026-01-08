<?php
// app/core/DB.php

class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $cfg = config('database');

        // Azure MySQL: require_secure_transport=ON => wajib TLS
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=%s;sslmode=required',
            $cfg['driver'],
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], $cfg['options'] ?? []);
        return self::$pdo;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return (int) self::pdo()->lastInsertId();
    }
}
