<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class Service
{
    // Lista pública: solo visibles, ordenados
    public static function listPublic(): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, titulo, descripcion, orden, visible
                FROM services
                WHERE visible = 1
                ORDER BY orden ASC, id ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Lista completa para admin
    public static function listAdmin(): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, titulo, descripcion, orden, visible
                FROM services
                ORDER BY orden ASC, id ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function create(array $data): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO services (titulo, descripcion, orden, visible)
                VALUES (:titulo, :descripcion, :orden, :visible)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':orden'       => isset($data['orden']) ? (int)$data['orden'] : 1,
            ':visible'     => isset($data['visible']) ? (int)$data['visible'] : 1,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE services
                SET titulo = :titulo,
                    descripcion = :descripcion,
                    orden = :orden,
                    visible = :visible
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        return $stmt->execute([
            ':id'          => $id,
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':orden'       => isset($data['orden']) ? (int)$data['orden'] : 1,
            ':visible'     => isset($data['visible']) ? (int)$data['visible'] : 1,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM services WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
