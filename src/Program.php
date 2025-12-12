<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class Program
{
    /**
     * Lista pública (solo visibles) con paginación
     */
    public static function listPublic(int $limit = 20, int $offset = 0): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT
                    id,
                    nombre,
                    resumen,
                    nivel,
                    modalidad,
                    duracion,
                    turno,
                    imagen_url
                FROM programs
                WHERE visible = 1
                ORDER BY nombre ASC
                OFFSET :offset ROWS
                FETCH NEXT :limit ROWS ONLY";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista para admin (ve TODO, incluso no visibles)
     */
    public static function listAdmin(int $limit = 100, int $offset = 0): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT
                    id,
                    nombre,
                    resumen,
                    descripcion,
                    nivel,
                    modalidad,
                    duracion,
                    turno,
                    imagen_url,
                    visible,
                    created_at,
                    updated_at
                FROM programs
                ORDER BY nombre ASC
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

        $sql = "SELECT
                    id,
                    nombre,
                    resumen,
                    descripcion,
                    nivel,
                    modalidad,
                    duracion,
                    turno,
                    imagen_url,
                    visible,
                    created_at,
                    updated_at
                FROM programs
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function create(array $data, ?int $userId = null): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO programs (
                    nombre,
                    resumen,
                    descripcion,
                    nivel,
                    modalidad,
                    duracion,
                    turno,
                    imagen_url,
                    visible,
                    creado_por
                ) VALUES (
                    :nombre,
                    :resumen,
                    :descripcion,
                    :nivel,
                    :modalidad,
                    :duracion,
                    :turno,
                    :imagen_url,
                    :visible,
                    :creado_por
                )";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;

        $stmt->execute([
            ':nombre'      => $data['nombre'],
            ':resumen'     => $data['resumen']      ?? null,
            ':descripcion' => $data['descripcion']  ?? null,
            ':nivel'       => $data['nivel']        ?? null,
            ':modalidad'   => $data['modalidad']    ?? null,
            ':duracion'    => $data['duracion']     ?? null,
            ':turno'       => $data['turno']        ?? null,
            ':imagen_url'  => $data['imagen_url']   ?? null,
            ':visible'     => $visible,
            ':creado_por'  => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE programs
                SET nombre      = :nombre,
                    resumen     = :resumen,
                    descripcion = :descripcion,
                    nivel       = :nivel,
                    modalidad   = :modalidad,
                    duracion    = :duracion,
                    turno       = :turno,
                    imagen_url  = :imagen_url,
                    visible     = :visible,
                    updated_at  = SYSDATETIME()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;

        return $stmt->execute([
            ':id'          => $id,
            ':nombre'      => $data['nombre'],
            ':resumen'     => $data['resumen']      ?? null,
            ':descripcion' => $data['descripcion']  ?? null,
            ':nivel'       => $data['nivel']        ?? null,
            ':modalidad'   => $data['modalidad']    ?? null,
            ':duracion'    => $data['duracion']     ?? null,
            ':turno'       => $data['turno']        ?? null,
            ':imagen_url'  => $data['imagen_url']   ?? null,
            ':visible'     => $visible,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM programs WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
