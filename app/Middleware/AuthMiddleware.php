<?php

declare(strict_types=1);

namespace Browser\Middleware;

use Browser\Core\Auth;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Models\AuditLog;
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
                self::recordAuditEvent($userId, 'persisted_session_invalid', 'user_session', null);
                self::logoutAndRedirect();
            }
        } catch (Throwable) {
            self::recordAuditEvent($userId, 'persisted_session_validation_error', 'user_session', null);
            error_log('Session validation failed in AuthMiddleware.');
            self::logoutAndRedirect();
        }
    }

    private static function logoutAndRedirect(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    private static function recordAuditEvent(?int $userId, string $action, ?string $entityType = null, ?string $entityId = null): void
    {
        try {
            AuditLog::record($userId, $action, $entityType, $entityId);
        } catch (Throwable) {
            error_log('Audit logging failed.');
        }
    }
}
