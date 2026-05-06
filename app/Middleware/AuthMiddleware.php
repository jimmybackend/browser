<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Auth;
use Browser\Core\Response;

final class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }
}
