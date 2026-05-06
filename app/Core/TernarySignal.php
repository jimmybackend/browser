<?php

declare(strict_types=1);

namespace Browser\Core;

final class TernarySignal
{
    public const POSITIVE = 1;
    public const NEUTRAL = 0;
    public const NEGATIVE = -1;

    public static function isValid(int $value): bool
    {
        return in_array($value, [self::NEGATIVE, self::NEUTRAL, self::POSITIVE], true);
    }

    public static function label(int $value): string
    {
        return match ($value) {
            self::POSITIVE => 'positive',
            self::NEGATIVE => 'negative',
            default => 'neutral',
        };
    }

    public static function normalize(int|string|null $value): int
    {
        if ($value === null || $value === '') {
            return self::NEUTRAL;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return self::NEUTRAL;
            }

            if (!is_numeric($value)) {
                return self::NEUTRAL;
            }

            $value = (int)$value;
        }

        return self::isValid($value) ? $value : self::NEUTRAL;
    }
}
