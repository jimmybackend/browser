<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;
use PDO;

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

    public static function create(string $username, string $email, string $passwordHash, ?string $displayName = null): int
    {
        $statement = Database::connection()->prepare(
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

        return (int)Database::connection()->lastInsertId();
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
