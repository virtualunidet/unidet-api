<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

class Contact
{
    private static function pdo(): PDO
    {
        return DB::getConnection();
    }

    /**
     * Obtiene (o crea) la fila única de contact_settings.
     */
    private static function getRow(): array
    {
        $pdo = self::pdo();

        $stmt = $pdo->query("
            SELECT TOP 1 *
            FROM contact_settings
            ORDER BY id ASC
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // crea fila inicial si no existe
            $stmtInsert = $pdo->prepare("
                INSERT INTO contact_settings (phones, emails, socials)
                VALUES (N'[]', N'[]', N'[]')
            ");
            $stmtInsert->execute();

            $stmt = $pdo->query("
                SELECT TOP 1 *
                FROM contact_settings
                ORDER BY id ASC
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row ?: [
            'id'          => 1,
            'phones'      => '[]',
            'emails'      => '[]',
            'address'     => null,
            'schedule'    => null,
            'social_text' => null,
            'socials'     => '[]',
            'hero_image'  => null,
        ];
    }

    private static function decodeJson(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /* ==========================
     *    PUBLIC / ADMIN GET
     * ==========================*/

    public static function getPublic(): array
    {
        $row = self::getRow();

        return [
            'phones'      => self::decodeJson($row['phones'] ?? '[]'),
            'emails'      => self::decodeJson($row['emails'] ?? '[]'),
            'address'     => $row['address'] ?? '',
            'schedule'    => $row['schedule'] ?? '',
            'social_text' => $row['social_text'] ?? '',
            'socials'     => self::decodeJson($row['socials'] ?? '[]'),
            'hero_image'  => $row['hero_image'] ?? null,
        ];
    }

    public static function getAdmin(): array
    {
        // por ahora igual que público
        return self::getPublic();
    }

    /* ==========================
     *         UPDATE
     * ==========================*/

    public static function update(array $data): bool
    {
        $pdo = self::pdo();
        $row = self::getRow();

        // Limpieza básica
        $phones = [];
        if (isset($data['phones']) && is_array($data['phones'])) {
            foreach ($data['phones'] as $value) {
                $value = trim((string)$value);
                if ($value !== '') {
                    $phones[] = $value;
                }
            }
        }

        $emails = [];
        if (isset($data['emails']) && is_array($data['emails'])) {
            foreach ($data['emails'] as $value) {
                $value = trim((string)$value);
                if ($value !== '') {
                    $emails[] = $value;
                }
            }
        }

        $socials = [];
        if (isset($data['socials']) && is_array($data['socials'])) {
            foreach ($data['socials'] as $item) {
                $label = trim((string)($item['label'] ?? ''));
                $url   = trim((string)($item['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $socials[] = [
                        'label' => $label,
                        'url'   => $url,
                    ];
                }
            }
        }

        $stmt = $pdo->prepare("
            UPDATE contact_settings
            SET
                phones      = :phones,
                emails      = :emails,
                address     = :address,
                schedule    = :schedule,
                social_text = :social_text,
                socials     = :socials,
                updated_at  = SYSDATETIME()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':phones'      => json_encode($phones, JSON_UNESCAPED_UNICODE),
            ':emails'      => json_encode($emails, JSON_UNESCAPED_UNICODE),
            ':address'     => $data['address']     ?? null,
            ':schedule'    => $data['schedule']    ?? null,
            ':social_text' => $data['social_text'] ?? null,
            ':socials'     => json_encode($socials, JSON_UNESCAPED_UNICODE),
            ':id'          => $row['id'],
        ]);
    }

    /* ==========================
     *      UPDATE IMAGE
     * ==========================*/

    public static function updateImage(string $url): bool
    {
        $pdo = self::pdo();
        $row = self::getRow();

        $stmt = $pdo->prepare("
            UPDATE contact_settings
            SET hero_image = :hero_image,
                updated_at = SYSDATETIME()
            WHERE id = :id
        ");

        return $stmt->execute([
            ':hero_image' => $url,
            ':id'         => $row['id'],
        ]);
    }
}
