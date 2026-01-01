<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class Event
{
    public static function listPublic(int $limit = 50, int $offset = 0): array
    {
        $pdo = DB::getConnection();

        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, lugar
                FROM events
                WHERE visible = 1
                ORDER BY fecha_inicio ASC
                LIMIT :limit
                OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function listAdmin(int $limit = 100, int $offset = 0): array
    {
        $pdo = DB::getConnection();

        $limit  = max(1, (int)$limit);
        $offset = max(0, (int)$offset);

        $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, lugar, visible
                FROM events
                ORDER BY fecha_inicio ASC
                LIMIT :limit
                OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, titulo, descripcion, fecha_inicio, fecha_fin, lugar, visible
                FROM events
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data, ?int $userId = null): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO events
                    (titulo, descripcion, fecha_inicio, fecha_fin, lugar, visible, creado_por)
                VALUES
                    (:titulo, :descripcion, :fecha_inicio, :fecha_fin, :lugar, :visible, :creado_por)";

        $stmt = $pdo->prepare($sql);

        $fechaInicio = $data['fecha_inicio'] ?? date('Y-m-d 08:00:00');
        $fechaFin    = $data['fecha_fin']    ?? null;
        $visible     = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        $stmt->execute([
            ':titulo'       => (string)($data['titulo'] ?? ''),
            ':descripcion'  => $data['descripcion'] ?? null,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin,
            ':lugar'        => $data['lugar'] ?? null,
            ':visible'      => $visible,
            ':creado_por'   => $userId,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE events
                SET titulo        = :titulo,
                    descripcion   = :descripcion,
                    fecha_inicio  = :fecha_inicio,
                    fecha_fin     = :fecha_fin,
                    lugar         = :lugar,
                    visible       = :visible,
                    updated_at    = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $fechaInicio = $data['fecha_inicio'] ?? date('Y-m-d 08:00:00');
        $fechaFin    = $data['fecha_fin']    ?? null;
        $visible     = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        return $stmt->execute([
            ':id'           => $id,
            ':titulo'       => (string)($data['titulo'] ?? ''),
            ':descripcion'  => $data['descripcion'] ?? null,
            ':fecha_inicio' => $fechaInicio,
            ':fecha_fin'    => $fechaFin,
            ':lugar'        => $data['lugar'] ?? null,
            ':visible'      => $visible,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM events WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
