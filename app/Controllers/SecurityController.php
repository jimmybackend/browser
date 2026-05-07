<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Models\AuditLog;
use Browser\Models\UserSession;

final class SecurityController extends Controller
{
    public function sessions(Request $request): void
    {
        $this->requireAuth();

        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        $currentSessionId = session_id();
        $currentSessionTokenHash = UserSession::hashSessionId($currentSessionId);

        $this->view('security/sessions', [
            'title' => 'Sesiones activas',
            'sessions' => UserSession::listForUser((int) $user['id']),
            'currentSessionTokenHash' => $currentSessionTokenHash,
        ]);
    }

    public function revokeSession(Request $request): void
    {
        $this->requireAuth();

        if (!Csrf::validate((string) $request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/security/sessions');
        }

        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        $sessionRecordId = (int) $request->post('session_id');
        if ($sessionRecordId <= 0) {
            Session::flash('error', 'Sesión inválida.');
            Response::redirect('/security/sessions');
        }

        $sessions = UserSession::listForUser((int) $user['id']);
        $currentHash = UserSession::hashSessionId(session_id());
        $isCurrentSession = false;

        foreach ($sessions as $session) {
            if ((int) ($session['id'] ?? 0) === $sessionRecordId) {
                $isCurrentSession = (string) ($session['session_fingerprint'] ?? '') === $currentHash;
                break;
            }
        }

        UserSession::revokeForUserById((int) $user['id'], $sessionRecordId);
        $this->recordAuditEvent((int) $user['id'], 'session_revoked', 'user_session', (string) $sessionRecordId, [
            'revoked_current_session' => $isCurrentSession,
        ], $request->ip(), $request->userAgent());

        if ($isCurrentSession) {
            Auth::logout();
            Session::flash('success', 'Tu sesión actual fue revocada. Inicia sesión nuevamente.');
            Response::redirect('/login');
        }

        Session::flash('success', 'Sesión revocada correctamente.');
        Response::redirect('/security/sessions');
    }

    public function revokeOtherSessions(Request $request): void
    {
        $this->requireAuth();

        if (!Csrf::validate((string) $request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/security/sessions');
        }

        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        UserSession::revokeOtherSessions((int) $user['id'], session_id());
        $this->recordAuditEvent((int) $user['id'], 'other_sessions_revoked', 'user', (string) $user['id'], [], $request->ip(), $request->userAgent());
        Session::flash('success', 'Se cerraron las otras sesiones activas.');

        Response::redirect('/security/sessions');
    }
    private function recordAuditEvent(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        try {
            AuditLog::record($userId, $action, $entityType, $entityId, $metadata, $ipAddress, $userAgent);
        } catch (\Throwable) {
            error_log('Audit logging failed.');
        }
    }
}
