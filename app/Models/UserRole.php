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

    public static function allRoles(): array
    {
        $statement = Database::connection()->query(
            'SELECT id, name, description FROM roles ORDER BY name'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function assignRole(int $userId, int $roleId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    public static function removeRole(int $userId, int $roleId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    public static function findRoleByName(string $name): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name FROM roles WHERE name = :name LIMIT 1'
        );
        $statement->execute(['name' => $name]);
        $role = $statement->fetch(PDO::FETCH_ASSOC);

        return $role === false ? null : $role;
    }
}
