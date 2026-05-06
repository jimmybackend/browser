<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class MarketingClient
{
    public const VALID_STATUSES = ['active', 'prospect', 'paused', 'inactive'];

    public static function list(int $limit = 50, int $offset = 0): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_name, contact_name, contact_email, contact_phone, website, status, notes, created_at, updated_at
             FROM marketing_clients
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_name, contact_name, contact_email, contact_phone, website, status, notes, created_at, updated_at
             FROM marketing_clients
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $id]);

        $client = $statement->fetch(PDO::FETCH_ASSOC);

        return $client === false ? null : $client;
    }

    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO marketing_clients
                (company_name, contact_name, contact_email, contact_phone, website, status, notes)
             VALUES
                (:company_name, :contact_name, :contact_email, :contact_phone, :website, :status, :notes)'
        );

        $statement->execute(self::payload($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $payload = self::payload($data);
        $payload[':id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE marketing_clients
             SET company_name = :company_name,
                 contact_name = :contact_name,
                 contact_email = :contact_email,
                 contact_phone = :contact_phone,
                 website = :website,
                 status = :status,
                 notes = :notes,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $statement->execute($payload);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE marketing_clients SET status = :status, updated_at = NOW() WHERE id = :id'
        );

        $statement->execute([
            ':id' => $id,
            ':status' => $status,
        ]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM marketing_clients WHERE id = :id');
        $statement->execute([':id' => $id]);
    }

    private static function payload(array $data): array
    {
        return [
            ':company_name' => $data['company_name'],
            ':contact_name' => $data['contact_name'] ?: null,
            ':contact_email' => $data['contact_email'] ?: null,
            ':contact_phone' => $data['contact_phone'] ?: null,
            ':website' => $data['website'] ?: null,
            ':status' => $data['status'],
            ':notes' => $data['notes'] ?: null,
        ];
    }
}
