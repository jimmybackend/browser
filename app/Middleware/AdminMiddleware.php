<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Auth;
use Browser\Core\Response;
use Browser\Models\UserRole;

final class AdminMiddleware
{
    public static function handle(): void
    {
        $userId = Auth::id();

        if ($userId === null || !UserRole::userHasRole($userId, 'admin')) {
            Response::forbidden();
            exit;
        }
    }
}
