<?php

declare(strict_types=1);

namespace Browser\Core;

use Dotenv\Dotenv;

final class Env
{
    public static function load(string $basePath): void
    {
        if (file_exists($basePath . '/.env')) {
            Dotenv::createImmutable($basePath)->safeLoad();
        }
    }
}
