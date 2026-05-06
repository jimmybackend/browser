<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class UserRole
{
    public static function rolesForUser(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.name
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
        );
        $statement->execute(['user_id' => $userId]);

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function userHasRole(int $userId, string $role): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id AND r.name = :role
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'role' => $role,
        ]);

        return (bool) $statement->fetchColumn();
    }
}
