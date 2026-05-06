<?php

declare(strict_types=1);

namespace Browser\Models;

use Browser\Core\Database;

final class Role
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT id, name, description FROM roles ORDER BY name')
            ->fetchAll();
    }
}
