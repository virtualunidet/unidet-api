<?php
declare(strict_types=1);

namespace UnidetApi;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;

class Auth
{
    private static function env(string $key, string $default = ''): string
    {
        return getenv($key) ?: ($_ENV[$key] ?? $default);
    }

    public static function generateToken(array $payload): string
    {
        $secret = self::env('JWT_SECRET', 'secret');
        $now    = time();

        $tokenPayload = array_merge([
            'iat' => $now,
            'exp' => $now + (60 * 60 * 4), // 4 horas
        ], $payload);

        return JWT::encode($tokenPayload, $secret, 'HS256');
    }

    public static function validateToken(string $token): array
    {
        $secret  = self::env('JWT_SECRET', 'secret');
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return (array)$decoded;
    }

    public static function attemptLogin(string $email, string $password): ?array
    {
        $pdo = DB::getConnection();

        $email = trim(mb_strtolower($email));

        // Si tu tabla users tiene is_active, deja esa línea.
        // Si NO la tiene, quítala.
        $stmt = $pdo->prepare(
            "SELECT id, nombre, email, password_hash, role
             FROM users
             WHERE email = :email
             AND (is_active IS NULL OR is_active = 1)
             LIMIT 1"
        );

        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) return null;

        if (!password_verify($password, (string)$user['password_hash'])) {
            return null;
        }

        return [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['nombre'],
            'email' => (string)$user['email'],
            'role'  => (string)$user['role'],
        ];
    }
}
