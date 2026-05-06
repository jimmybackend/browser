<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;

final class MarketingClient
{
    public static function latest(int $limit = 10): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, company_name, contact_name, contact_email, status, created_at
             FROM marketing_clients
             ORDER BY created_at DESC
             LIMIT :limit'
        );

        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }
}
