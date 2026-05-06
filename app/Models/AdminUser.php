<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

final class AdminUser
{
    public static function listUsers(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $statement = Database::connection()->prepare(
            "SELECT u.id, u.username, u.email, u.display_name, u.status, u.created_at, u.last_login_at,
                    GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') AS roles
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT :limit"
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findUserWithRoles(int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, username, email, display_name, status, created_at, last_login_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            return null;
        }

        $rolesStatement = Database::connection()->prepare(
            'SELECT r.id, r.name, r.description
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
        );
        $rolesStatement->execute(['user_id' => $userId]);
        $user['roles'] = $rolesStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $user;
    }
}
