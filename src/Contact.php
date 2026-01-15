<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;

final class Contact
{
    private static function pdo(): PDO
    {
        return DB::getConnection();
    }

    private static function getRow(): array
    {
        $pdo = self::pdo();

        $stmt = $pdo->query("
            SELECT *
            FROM contact_settings
            ORDER BY id ASC
            LIMIT 1
        ");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO contact_settings (phones, emails, socials, address, schedule, social_text, hero_image)
                VALUES ('[]', '[]', '[]', '', '', '', NULL)
            ");
            $stmtInsert->execute();

            $stmt = $pdo->query("
                SELECT *
                FROM contact_settings
                ORDER BY id ASC
                LIMIT 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row ?: [
            'id'          => 1,
            'phones'      => '[]',
            'emails'      => '[]',
            'address'     => '',
            'schedule'    => '',
            'social_text' => '',
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

    public static function getPublic(): array
    {
        $row = self::getRow();

        return [
            'phones'      => self::decodeJson($row['phones'] ?? '[]'),
            'emails'      => self::decodeJson($row['emails'] ?? '[]'),
            'address'     => (string)($row['address'] ?? ''),
            'schedule'    => (string)($row['schedule'] ?? ''),
            'social_text' => (string)($row['social_text'] ?? ''),
            'socials'     => self::decodeJson($row['socials'] ?? '[]'),
            'hero_image'  => $row['hero_image'] ?? null,
        ];
    }

    public static function getAdmin(): array
    {
        return self::getPublic();
    }

    public static function update(array $data): bool
    {
        $pdo = self::pdo();
        $row = self::getRow();

        $phones = [];
        if (isset($data['phones']) && is_array($data['phones'])) {
            foreach ($data['phones'] as $value) {
                $value = trim((string)$value);
                if ($value !== '') $phones[] = $value;
            }
        }

        $emails = [];
        if (isset($data['emails']) && is_array($data['emails'])) {
            foreach ($data['emails'] as $value) {
                $value = trim((string)$value);
                if ($value !== '') $emails[] = $value;
            }
        }

        $socials = [];
        if (isset($data['socials']) && is_array($data['socials'])) {
            foreach ($data['socials'] as $item) {
                $label = trim((string)($item['label'] ?? ''));
                $url   = trim((string)($item['url'] ?? ''));
                if ($label !== '' && $url !== '') {
                    $socials[] = ['label' => $label, 'url' => $url];
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
                socials     = :socials
            WHERE id = :id
        ");

        return $stmt->execute([
            ':phones'      => json_encode($phones, JSON_UNESCAPED_UNICODE),
            ':emails'      => json_encode($emails, JSON_UNESCAPED_UNICODE),
            ':address'     => (string)($data['address'] ?? ''),
            ':schedule'    => (string)($data['schedule'] ?? ''),
            ':social_text' => (string)($data['social_text'] ?? ''),
            ':socials'     => json_encode($socials, JSON_UNESCAPED_UNICODE),
            ':id'          => $row['id'],
        ]);
    }

    public static function updateImage(string $url): bool
    {
        $pdo = self::pdo();
        $row = self::getRow();

        $stmt = $pdo->prepare("
            UPDATE contact_settings
            SET hero_image = :hero_image
            WHERE id = :id
        ");

        return $stmt->execute([
            ':hero_image' => $url,
            ':id'         => $row['id'],
        ]);
    }
}
