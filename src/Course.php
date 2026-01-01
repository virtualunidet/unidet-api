<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;
use Psr\Http\Message\UploadedFileInterface;

class Course
{
    /**
     * Lista pública: solo cursos visibles, con filtro opcional por categoría.
     */
    public static function listPublic(
        ?string $categoria = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       categoria,
                       imagen_url,
                       orden
                FROM courses
                WHERE visible = 1";

        // si viene categoría (no null ni cadena vacía) filtramos
        if ($categoria !== null && $categoria !== '') {
            $sql .= " AND categoria = :categoria";
        }

        // ✅ PostgreSQL: LIMIT/OFFSET
        $sql .= " ORDER BY orden ASC, id ASC
                  LIMIT :limit
                  OFFSET :offset";

        $stmt = $pdo->prepare($sql);

        if ($categoria !== null && $categoria !== '') {
            $stmt->bindValue(':categoria', $categoria, PDO::PARAM_STR);
        }

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        // ✅ Parche: normalizar imagen_url (por si la BD trae \)
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            if (!empty($r['imagen_url'])) {
                $r['imagen_url'] = str_replace('\\', '/', (string)$r['imagen_url']);
            }
        }
        unset($r);

        return $rows;
    }

    /**
     * Lista para admin (ve todo, visibles y no visibles)
     */
    public static function listAdmin(
        int $limit = 100,
        int $offset = 0
    ): array {
        $pdo = DB::getConnection();

        // ✅ PostgreSQL: LIMIT/OFFSET
        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       categoria,
                       imagen_url,
                       visible,
                       orden,
                       created_at,
                       updated_at
                FROM courses
                ORDER BY categoria ASC, orden ASC, id ASC
                LIMIT :limit
                OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        // ✅ Parche: normalizar imagen_url (por si la BD trae \)
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            if (!empty($r['imagen_url'])) {
                $r['imagen_url'] = str_replace('\\', '/', (string)$r['imagen_url']);
            }
        }
        unset($r);

        return $rows;
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id,
                       titulo,
                       descripcion,
                       categoria,
                       imagen_url,
                       visible,
                       orden
                FROM courses
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // ✅ Parche: normalizar imagen_url (por si la BD trae \)
        if ($row && !empty($row['imagen_url'])) {
            $row['imagen_url'] = str_replace('\\', '/', (string)$row['imagen_url']);
        }

        return $row ?: null;
    }

    public static function create(array $data, ?int $userId = null): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO courses
                    (titulo,
                     descripcion,
                     categoria,
                     imagen_url,
                     visible,
                     orden,
                     creado_por)
                VALUES
                    (:titulo,
                     :descripcion,
                     :categoria,
                     :imagen_url,
                     :visible,
                     :orden,
                     :creado_por)";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;
        $orden   = isset($data['orden']) ? max(0, (int) $data['orden']) : 0;

        $stmt->execute([
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':categoria'   => $data['categoria'],
            ':imagen_url'  => $data['imagen_url'] ?? null,
            ':visible'     => $visible,
            ':orden'       => $orden,
            ':creado_por'  => $userId,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE courses
                SET titulo      = :titulo,
                    descripcion = :descripcion,
                    categoria   = :categoria,
                    imagen_url  = :imagen_url,
                    visible     = :visible,
                    orden       = :orden,
                    updated_at  = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int) !!$data['visible'] : 1;
        $orden   = isset($data['orden']) ? max(0, (int) $data['orden']) : 0;

        return $stmt->execute([
            ':id'          => $id,
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':categoria'   => $data['categoria'],
            ':imagen_url'  => $data['imagen_url'] ?? null,
            ':visible'     => $visible,
            ':orden'       => $orden,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Guarda un archivo de imagen subido y devuelve el nombre final.
     */
    public static function moveUploadedFile(string $directory, UploadedFileInterface $uploadedFile): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION) ?: 'bin';
        $basename  = bin2hex(random_bytes(8));
        $filename  = sprintf('%s.%s', $basename, $extension);

        // Esto es ruta de disco, aquí sí aplica DIRECTORY_SEPARATOR
        $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

        return $filename;
    }
}
