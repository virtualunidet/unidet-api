<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class Admission
{
    /**
     * Lista pública: solo pasos visibles, ordenados.
     */
    public static function listPublic(
        int $limit = 50,
        int $offset = 0
    ): array {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       orden
                FROM admission_steps
                WHERE visible = 1
                ORDER BY orden ASC, id ASC
                OFFSET :offset ROWS
                FETCH NEXT :limit ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista para admin (ve todos los pasos)
     */
    public static function listAdmin(
        int $limit = 100,
        int $offset = 0
    ): array {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       visible,
                       orden,
                       created_at,
                       updated_at
                FROM admission_steps
                ORDER BY orden ASC, id ASC
                OFFSET :offset ROWS
                FETCH NEXT :limit ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       visible,
                       orden
                FROM admission_steps
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO admission_steps
                    (titulo,
                     descripcion,
                     visible,
                     orden)
                VALUES
                    (:titulo,
                     :descripcion,
                     :visible,
                     :orden)";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;
        $orden   = isset($data['orden'])   ? max(0, (int) $data['orden']) : 0;

        $stmt->execute([
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':visible'     => $visible,
            ':orden'       => $orden,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE admission_steps
                SET titulo      = :titulo,
                    descripcion = :descripcion,
                    visible     = :visible,
                    orden       = :orden,
                    updated_at  = SYSDATETIME()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;
        $orden   = isset($data['orden'])   ? max(0, (int) $data['orden']) : 0;

        return $stmt->execute([
            ':id'          => $id,
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':visible'     => $visible,
            ':orden'       => $orden,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM admission_steps WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
