<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;
use Throwable;

final class User
{
    public static function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, username, email, display_name, status, created_at FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);

        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function existsByUsername(string $username): bool
    {
        $statement = Database::connection()->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);

        return (bool) $statement->fetchColumn();
    }

    public static function create(string $username, string $email, string $passwordHash, ?string $displayName = null): int
    {
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(
                'INSERT INTO users (uuid, username, email, password_hash, display_name, status)
                 VALUES (:uuid, :username, :email, :password_hash, :display_name, :status)'
            );

            $statement->execute([
                'uuid' => self::uuid(),
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'display_name' => $displayName,
                'status' => 'active',
            ]);

            $userId = (int) $connection->lastInsertId();

            $pref = $connection->prepare(
                'INSERT INTO user_preferences (user_id, search_history_enabled, email_notifications_enabled, theme, language, timezone)
                 VALUES (:user_id, 0, 1, :theme, :language, :timezone)'
            );
            $pref->execute([
                'user_id' => $userId,
                'theme' => 'system',
                'language' => 'es',
                'timezone' => 'America/Mexico_City',
            ]);

            $roleId = $connection->prepare('SELECT id FROM roles WHERE name = :name LIMIT 1');
            $roleId->execute(['name' => 'user']);
            $userRoleId = $roleId->fetchColumn();

            if ($userRoleId !== false) {
                $roleAssign = $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
                $roleAssign->execute(['user_id' => $userId, 'role_id' => (int) $userRoleId]);
            }

            $connection->commit();

            return $userId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public static function updateLastLogin(int $id): void
    {
        $statement = Database::connection()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
