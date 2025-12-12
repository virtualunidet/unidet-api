<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class News
{
    // Listado público con paginación básica
    public static function listPublic(int $limit = 20, int $offset = 0): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, titulo, resumen, contenido, fecha_publicacion
                FROM news
                WHERE visible = 1
                ORDER BY fecha_publicacion DESC
                OFFSET :offset ROWS
                FETCH NEXT :limit ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 🆕 Listado completo para administrador (incluye no visibles)
    public static function listAdmin(): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       resumen,
                       contenido,
                       fecha_publicacion,
                       visible,
                       creado_por,
                       created_at,
                       updated_at
                FROM news
                ORDER BY fecha_publicacion DESC";

        $stmt = $pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, titulo, resumen, contenido, fecha_publicacion, visible
                FROM news
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function create(array $data, int $userId = null): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO news (titulo, resumen, contenido, fecha_publicacion, visible, creado_por)
                VALUES (:titulo, :resumen, :contenido, :fecha_publicacion, :visible, :creado_por)";

        $stmt = $pdo->prepare($sql);

        $fechaPublicacion = $data['fecha_publicacion'] ?? null;
        if (!$fechaPublicacion) {
            $fechaPublicacion = date('Y-m-d H:i:s');
        }

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;

        $stmt->execute([
            ':titulo'            => $data['titulo'],
            ':resumen'           => $data['resumen'] ?? null,
            ':contenido'         => $data['contenido'],
            ':fecha_publicacion' => $fechaPublicacion,
            ':visible'           => $visible,
            ':creado_por'        => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE news
                SET titulo = :titulo,
                    resumen = :resumen,
                    contenido = :contenido,
                    fecha_publicacion = :fecha_publicacion,
                    visible = :visible,
                    updated_at = SYSDATETIME()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $fechaPublicacion = $data['fecha_publicacion'] ?? date('Y-m-d H:i:s');
        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;

        return $stmt->execute([
            ':id'                => $id,
            ':titulo'            => $data['titulo'],
            ':resumen'           => $data['resumen'] ?? null,
            ':contenido'         => $data['contenido'],
            ':fecha_publicacion' => $fechaPublicacion,
            ':visible'           => $visible,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM news WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
