<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Models\User;

final class AuthService
{
    public function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        return $user;
    }

    public function register(string $username, string $email, string $password, ?string $displayName = null): int
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        return User::create($username, $email, $passwordHash, $displayName);
    }
}
