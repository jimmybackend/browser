<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Auth;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Models\UserSession;
use Throwable;

final class AuthMiddleware
{
    public static function handle(): void
    {
        $userId = Auth::id();
        $sessionId = session_id();

        if ($userId === null || $sessionId === '') {
            self::logoutAndRedirect();
        }

        try {
            if (!UserSession::isActive($sessionId)) {
                self::logoutAndRedirect();
            }
        } catch (Throwable) {
            error_log('Session validation failed in AuthMiddleware.');
            self::logoutAndRedirect();
        }
    }

    private static function logoutAndRedirect(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }
}
