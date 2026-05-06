<?php

declare(strict_types=1);

namespace Browser\Core;

final class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function minLength(string $value, int $length): bool
    {
        return mb_strlen($value) >= $length;
    }

    public static function required(?string $value): bool
    {
        return trim((string)$value) !== '';
    }

    public static function username(string $username): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_\.\-]{3,60}$/', $username);
    }
}
