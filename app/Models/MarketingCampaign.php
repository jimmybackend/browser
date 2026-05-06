<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class MarketingCampaign
{
    public const VALID_STATUSES = ['draft', 'active', 'paused', 'completed', 'cancelled'];

    public static function list(int $limit = 100, int $offset = 0): array
    {
        $statement = Database::connection()->prepare(
            'SELECT mc.id, mc.client_id, mc.name, mc.description, mc.channel, mc.budget, mc.start_date, mc.end_date, mc.status, mc.created_at, mc.updated_at,
                    cl.company_name AS client_company_name
             FROM marketing_campaigns mc
             INNER JOIN marketing_clients cl ON cl.id = mc.client_id
             ORDER BY mc.created_at DESC
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
            'SELECT mc.id, mc.client_id, mc.name, mc.description, mc.channel, mc.budget, mc.start_date, mc.end_date, mc.status, mc.created_at, mc.updated_at,
                    cl.company_name AS client_company_name
             FROM marketing_campaigns mc
             INNER JOIN marketing_clients cl ON cl.id = mc.client_id
             WHERE mc.id = :id LIMIT 1'
        );
        $statement->execute([':id' => $id]);
        $campaign = $statement->fetch(PDO::FETCH_ASSOC);

        return $campaign === false ? null : $campaign;
    }

    public static function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO marketing_campaigns
                (client_id, name, description, channel, budget, start_date, end_date, status)
             VALUES
                (:client_id, :name, :description, :channel, :budget, :start_date, :end_date, :status)'
        );

        $statement->execute(self::payload($data));

        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $payload = self::payload($data);
        $payload[':id'] = $id;

        $statement = Database::connection()->prepare(
            'UPDATE marketing_campaigns
             SET client_id = :client_id,
                 name = :name,
                 description = :description,
                 channel = :channel,
                 budget = :budget,
                 start_date = :start_date,
                 end_date = :end_date,
                 status = :status,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $statement->execute($payload);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare('UPDATE marketing_campaigns SET status = :status, updated_at = NOW() WHERE id = :id');
        $statement->execute([':id' => $id, ':status' => $status]);
    }

    public static function delete(int $id): void
    {
        $statement = Database::connection()->prepare('DELETE FROM marketing_campaigns WHERE id = :id');
        $statement->execute([':id' => $id]);
    }

    private static function payload(array $data): array
    {
        return [
            ':client_id' => $data['client_id'],
            ':name' => $data['name'],
            ':description' => $data['description'] ?: null,
            ':channel' => $data['channel'],
            ':budget' => $data['budget'],
            ':start_date' => $data['start_date'] ?: null,
            ':end_date' => $data['end_date'] ?: null,
            ':status' => $data['status'],
        ];
    }
}
