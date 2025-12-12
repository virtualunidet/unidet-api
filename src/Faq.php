<?php
declare(strict_types=1);

namespace UnidetApi;

use PDO;
use PDOException;

class Faq
{
    /** Lista pública: solo visibles, ordenados por orden, id */
    public static function listPublic(): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, pregunta, respuesta_corta, respuesta_larga
                FROM faq_questions
                WHERE visible = 1
                ORDER BY orden ASC, id ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Lista para admin (ve todo) */
    public static function listAdmin(): array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, pregunta, respuesta_corta, respuesta_larga,
                       visible, orden, created_at, updated_at
                FROM faq_questions
                ORDER BY orden ASC, id ASC";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getById(int $id): ?array
    {
        $pdo = DB::getConnection();

        $sql = "SELECT id, pregunta, respuesta_corta, respuesta_larga,
                       visible, orden, created_at, updated_at
                FROM faq_questions
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = DB::getConnection();

        $sql = "INSERT INTO faq_questions
                    (pregunta, respuesta_corta, respuesta_larga, visible, orden)
                VALUES
                    (:pregunta, :respuesta_corta, :respuesta_larga, :visible, :orden)";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int)!!$data['visible'] : 1;
        $orden   = isset($data['orden'])   ? max(0, (int)$data['orden']) : 0;

        $stmt->execute([
            ':pregunta'        => (string)($data['pregunta'] ?? ''),
            ':respuesta_corta' => $data['respuesta_corta'] ?? null,
            ':respuesta_larga' => $data['respuesta_larga'] ?? null,
            ':visible'         => $visible,
            ':orden'           => $orden,
        ]);

        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $pdo = DB::getConnection();

        $sql = "UPDATE faq_questions
                SET pregunta        = :pregunta,
                    respuesta_corta = :respuesta_corta,
                    respuesta_larga = :respuesta_larga,
                    visible         = :visible,
                    orden           = :orden,
                    updated_at      = SYSDATETIME()
                WHERE id = :id";

        $stmt = $pdo->prepare($sql);

        $visible = isset($data['visible']) ? (int)!!$data['visible'] : 1;
        $orden   = isset($data['orden'])   ? max(0, (int)$data['orden']) : 0;

        return $stmt->execute([
            ':id'              => $id,
            ':pregunta'        => (string)($data['pregunta'] ?? ''),
            ':respuesta_corta' => $data['respuesta_corta'] ?? null,
            ':respuesta_larga' => $data['respuesta_larga'] ?? null,
            ':visible'         => $visible,
            ':orden'           => $orden,
        ]);
    }

    public static function delete(int $id): bool
    {
        $pdo = DB::getConnection();

        $sql = "DELETE FROM faq_questions WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
