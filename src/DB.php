<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;
use PDOException;

class DB
{
    public static function getConnection(): PDO
    {
        $dsn  = $_ENV['DB_DSN']  ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            throw new PDOException('Error de conexión a BD: ' . $e->getMessage());
        }
    }
}
