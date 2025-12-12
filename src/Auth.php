<?php
declare(strict_types=1);

namespace UnidetApi;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;

class Auth
{
    public static function generateToken(array $payload): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? 'secret';
        $now    = time();

        $tokenPayload = array_merge([
            'iat' => $now,
            'exp' => $now + (60 * 60 * 4) // 4 horas
        ], $payload);

        return JWT::encode($tokenPayload, $secret, 'HS256');
    }

    public static function validateToken(string $token): array
    {
        $secret  = $_ENV['JWT_SECRET'] ?? 'secret';
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
        return (array) $decoded;
    }

    public static function attemptLogin(string $email, string $password): ?array
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare(
            'SELECT id, nombre, email, password_hash, role
             FROM users
             WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null; // usuario no existe
        }

        // Verificar password
        if (!password_verify($password, $user['password_hash'])) {
            return null; // contraseña incorrecta
        }

        // Usuario autenticado correctamente
        return [
            'id'    => (int)$user['id'],
            'name'  => $user['nombre'],
            'email' => $user['email'],
            'role'  => $user['role'], // "aspirant" | "student" | "admin"
        ];
    }
}
