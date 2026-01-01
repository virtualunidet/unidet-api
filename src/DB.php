<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;
use PDOException;

class DB
{
    private static function env(string $key): string
    {
        return getenv($key) ?: ($_ENV[$key] ?? '');
    }

    public static function getConnection(): PDO
    {
        $dsn  = self::env('DB_DSN');
        $user = self::env('DB_USER');
        $pass = self::env('DB_PASS');

        if ($dsn === '' || $user === '') {
            throw new PDOException('Faltan variables de entorno de BD (DB_DSN/DB_USER/DB_PASS).');
        }

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException('Error de conexión a BD: ' . $e->getMessage());
        }
    }
}
