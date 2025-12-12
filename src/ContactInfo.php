<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class ContactInfo
{
    private static function getPdo(): PDO
    {
        return DB::getConnection();
    }

    /**
     * Devuelve la fila única de contacto (id mínimo).
     */
    public static function get(): ?array
    {
        $pdo = self::getPdo();

        $stmt = $pdo->query("
            SELECT TOP 1 *
            FROM contact_info
            ORDER BY id ASC
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Actualiza campos de texto.
     */
    public static function update(array $data): bool
    {
        $pdo = self::getPdo();
        $pdo->beginTransaction();

        try {
            $current = self::get();

            if (!$current) {
                // si no existe fila, crea una
                $stmtInsert = $pdo->prepare("
                    INSERT INTO contact_info (telefono, correo, domicilio, horario, redes_titulo, redes_texto, redes_link, image_url)
                    VALUES (:telefono, :correo, :domicilio, :horario, :redes_titulo, :redes_texto, :redes_link, :image_url)
                ");
                $stmtInsert->execute([
                    ':telefono'     => $data['telefono']     ?? null,
                    ':correo'       => $data['correo']       ?? null,
                    ':domicilio'    => $data['domicilio']    ?? null,
                    ':horario'      => $data['horario']      ?? null,
                    ':redes_titulo' => $data['redes_titulo'] ?? null,
                    ':redes_texto'  => $data['redes_texto']  ?? null,
                    ':redes_link'   => $data['redes_link']   ?? null,
                    ':image_url'    => $data['image_url']    ?? null,
                ]);
            } else {
                $stmtUpdate = $pdo->prepare("
                    UPDATE contact_info
                    SET
                        telefono     = :telefono,
                        correo       = :correo,
                        domicilio    = :domicilio,
                        horario      = :horario,
                        redes_titulo = :redes_titulo,
                        redes_texto  = :redes_texto,
                        redes_link   = :redes_link,
                        updated_at   = SYSDATETIME()
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    ':telefono'     => $data['telefono']     ?? null,
                    ':correo'       => $data['correo']       ?? null,
                    ':domicilio'    => $data['domicilio']    ?? null,
                    ':horario'      => $data['horario']      ?? null,
                    ':redes_titulo' => $data['redes_titulo'] ?? null,
                    ':redes_texto'  => $data['redes_texto']  ?? null,
                    ':redes_link'   => $data['redes_link']   ?? null,
                    ':id'           => $current['id'],
                ]);
            }

            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }

    /**
     * Actualiza solo la imagen.
     */
    public static function updateImage(string $imageUrl): bool
    {
        $pdo = self::getPdo();
        $current = self::get();

        if (!$current) {
            $stmt = $pdo->prepare("
                INSERT INTO contact_info (image_url)
                VALUES (:image_url)
            ");
            return $stmt->execute([
                ':image_url' => $imageUrl,
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE contact_info
            SET image_url = :image_url,
                updated_at = SYSDATETIME()
            WHERE id = :id
        ");
        return $stmt->execute([
            ':image_url' => $imageUrl,
            ':id'        => $current['id'],
        ]);
    }
}
