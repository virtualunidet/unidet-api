<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;
use PDOException;

class Regulation
{
    /** @return PDO */
    private static function db(): PDO
    {
        return DB::getConnection();
    }

    /**
     * Devuelve la fila única de regulations (o null si no existe).
     */
    public static function getSingleton(): ?array
    {
        $pdo = self::db();

        // PostgreSQL: LIMIT 1
        $sql = "SELECT id, content_html, pdf_path, updated_at
                FROM regulations
                ORDER BY id ASC
                LIMIT 1";

        $stmt = $pdo->query($sql);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Asegura que exista una fila en regulations y devuelve su id.
     */
    private static function ensureSingletonId(): int
    {
        $row = self::getSingleton();
        if ($row) {
            return (int)$row['id'];
        }

        $pdo = self::db();

        // Creamos una fila vacía (PostgreSQL: no existe N'')
        $pdo->exec("
            INSERT INTO regulations (content_html, pdf_path)
            VALUES ('', NULL)
        ");

        $row = self::getSingleton();
        return (int)($row['id'] ?? 1);
    }

    /**
     * Actualiza el HTML completo del reglamento.
     */
    public static function updateContentHtml(string $html): bool
    {
        $pdo = self::db();
        $id  = self::ensureSingletonId();

        $sql = "UPDATE regulations
                SET content_html = :html,
                    updated_at   = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':html' => $html,
            ':id'   => $id,
        ]);
    }

    /**
     * Actualiza la ruta del PDF.
     */
    public static function updatePdfPath(string $pdfPath): bool
    {
        $pdo = self::db();
        $id  = self::ensureSingletonId();

        $sql = "UPDATE regulations
                SET pdf_path  = :path,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':path' => $pdfPath,
            ':id'   => $id,
        ]);
    }

    /**
     * Estructura para la parte pública:
     *  - Solo secciones visibles
     *  - Solo items visibles
     */
    public static function getPublicStructured(): array
    {
        $pdo = self::db();

        $sqlSections = "
            SELECT id, titulo, descripcion, orden
            FROM regulation_sections
            WHERE visible = 1
            ORDER BY orden ASC, id ASC
        ";
        $sections = $pdo->query($sqlSections)->fetchAll(PDO::FETCH_ASSOC);

        if (!$sections) {
            return [];
        }

        $sectionIds = array_column($sections, 'id');
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

        $sqlItems = "
            SELECT id, section_id, titulo, contenido, orden
            FROM regulation_items
            WHERE visible = 1
              AND section_id IN ($placeholders)
            ORDER BY orden ASC, id ASC
        ";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($sectionIds);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $bySection = [];
        foreach ($items as $it) {
            $sid = (int)$it['section_id'];
            $bySection[$sid] ??= [];
            $bySection[$sid][] = [
                'id'        => (int)$it['id'],
                'titulo'    => $it['titulo'],
                'contenido' => $it['contenido'],
                'orden'     => (int)$it['orden'],
            ];
        }

        $result = [];
        foreach ($sections as $sec) {
            $sid = (int)$sec['id'];
            $result[] = [
                'id'          => $sid,
                'titulo'      => $sec['titulo'],
                'descripcion' => $sec['descripcion'],
                'orden'       => (int)$sec['orden'],
                'items'       => $bySection[$sid] ?? [],
            ];
        }

        return $result;
    }

    /**
     * Estructura para el panel admin:
     *  - Todas las secciones
     *  - Todos los items
     *  - Incluye campo visible
     */
    public static function getAdminStructured(): array
    {
        $pdo = self::db();

        $sqlSections = "
            SELECT id, titulo, descripcion, orden, visible
            FROM regulation_sections
            ORDER BY orden ASC, id ASC
        ";
        $sections = $pdo->query($sqlSections)->fetchAll(PDO::FETCH_ASSOC);

        if (!$sections) {
            return [];
        }

        $sectionIds   = array_column($sections, 'id');
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

        $sqlItems = "
            SELECT id, section_id, titulo, contenido, orden, visible
            FROM regulation_items
            WHERE section_id IN ($placeholders)
            ORDER BY orden ASC, id ASC
        ";
        $stmtItems = $pdo->prepare($sqlItems);
        $stmtItems->execute($sectionIds);
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $bySection = [];
        foreach ($items as $it) {
            $sid = (int)$it['section_id'];
            $bySection[$sid] ??= [];
            $bySection[$sid][] = [
                'id'        => (int)$it['id'],
                'titulo'    => $it['titulo'],
                'contenido' => $it['contenido'],
                'orden'     => (int)$it['orden'],
                'visible'   => (int)$it['visible'],
            ];
        }

        $result = [];
        foreach ($sections as $sec) {
            $sid = (int)$sec['id'];
            $result[] = [
                'id'          => $sid,
                'titulo'      => $sec['titulo'],
                'descripcion' => $sec['descripcion'],
                'orden'       => (int)$sec['orden'],
                'visible'     => (int)$sec['visible'],
                'items'       => $bySection[$sid] ?? [],
            ];
        }

        return $result;
    }

    public static function createSection(array $data): int
    {
        $pdo = self::db();

        $titulo      = trim((string)($data['titulo'] ?? ''));
        $descripcion = $data['descripcion'] ?? null;
        $orden       = isset($data['orden']) ? max(1, (int)$data['orden']) : 1;
        $visible     = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        $sql = "
            INSERT INTO regulation_sections (titulo, descripcion, orden, visible)
            VALUES (:titulo, :descripcion, :orden, :visible)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':titulo'      => $titulo,
            ':descripcion' => $descripcion,
            ':orden'       => $orden,
            ':visible'     => $visible,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function updateSection(int $id, array $data): bool
    {
        $pdo = self::db();

        $titulo      = trim((string)($data['titulo'] ?? ''));
        $descripcion = $data['descripcion'] ?? null;
        $orden       = isset($data['orden']) ? max(1, (int)$data['orden']) : 1;
        $visible     = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        $sql = "
            UPDATE regulation_sections
            SET titulo      = :titulo,
                descripcion = :descripcion,
                orden       = :orden,
                visible     = :visible
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':titulo'      => $titulo,
            ':descripcion' => $descripcion,
            ':orden'       => $orden,
            ':visible'     => $visible,
            ':id'          => $id,
        ]);
    }

    public static function deleteSection(int $id): bool
    {
        $pdo = self::db();

        try {
            $pdo->beginTransaction();

            $sqlItems = "DELETE FROM regulation_items WHERE section_id = :id";
            $stmtItems = $pdo->prepare($sqlItems);
            $stmtItems->execute([':id' => $id]);

            $sqlSec = "DELETE FROM regulation_sections WHERE id = :id";
            $stmtSec = $pdo->prepare($sqlSec);
            $stmtSec->execute([':id' => $id]);

            $pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
    }

    public static function createItem(array $data): int
    {
        $pdo = self::db();

        $sectionId = (int)($data['section_id'] ?? 0);
        $titulo    = $data['titulo'] ?? null;
        $contenido = (string)($data['contenido'] ?? '');
        $orden     = isset($data['orden']) ? max(1, (int)$data['orden']) : 1;
        $visible   = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        $sql = "
            INSERT INTO regulation_items (section_id, titulo, contenido, orden, visible)
            VALUES (:section_id, :titulo, :contenido, :orden, :visible)
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':section_id' => $sectionId,
            ':titulo'     => $titulo,
            ':contenido'  => $contenido,
            ':orden'      => $orden,
            ':visible'    => $visible,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function updateItem(int $id, array $data): bool
    {
        $pdo = self::db();

        $sectionId = (int)($data['section_id'] ?? 0);
        $titulo    = $data['titulo'] ?? null;
        $contenido = (string)($data['contenido'] ?? '');
        $orden     = isset($data['orden']) ? max(1, (int)$data['orden']) : 1;
        $visible   = isset($data['visible']) ? (int)!!$data['visible'] : 1;

        $sql = "
            UPDATE regulation_items
            SET section_id = :section_id,
                titulo     = :titulo,
                contenido  = :contenido,
                orden      = :orden,
                visible    = :visible
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            ':section_id' => $sectionId,
            ':titulo'     => $titulo,
            ':contenido'  => $contenido,
            ':orden'      => $orden,
            ':visible'    => $visible,
            ':id'         => $id,
        ]);
    }

    public static function deleteItem(int $id): bool
    {
        $pdo = self::db();

        $sql = "DELETE FROM regulation_items WHERE id = :id";
        $stmt = $pdo->prepare($sql);

        return $stmt->execute([':id' => $id]);
    }
}
