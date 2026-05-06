<?php

declare(strict_types=1);

namespace Browser\Services;

use Browser\Models\User;

final class AuthService
{
    public function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        User::updateLastLogin((int) $user['id']);

        return $user;
    }

    public function register(string $username, string $email, string $password, ?string $displayName = null): int
    {
        if (User::existsByUsername($username)) {
            throw new \RuntimeException('El usuario ya está en uso.');
        }

        if (User::findByEmail($email)) {
            throw new \RuntimeException('El correo ya está en uso.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        return User::create($username, $email, $passwordHash, $displayName);
    }
}
