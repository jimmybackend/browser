<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Core\Validator;
use Browser\Models\AuditLog;
use Browser\Models\UserSession;
use Browser\Services\AuthService;

final class AuthController extends Controller
{
    public function showRegister(Request $request): void
    {
        $this->view('auth/register', ['title' => 'Crear cuenta']);
    }

    public function register(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'CSRF inválido. Recarga la página e intenta de nuevo.');
            $this->logAuthWarning('register_csrf_invalid');
            Response::redirect('/register');
        }

        $username = trim((string)$request->post('username'));
        $email = trim((string)$request->post('email'));
        $password = (string)$request->post('password');
        $passwordConfirmation = (string)$request->post('password_confirmation');
        $displayName = trim((string)$request->post('display_name'));

        if (
            !Validator::required($username)
            || !Validator::username($username)
            || !Validator::email($email)
            || !Validator::minLength($password, 12)
            || !hash_equals($password, $passwordConfirmation)
        ) {
            Session::flash('error', 'Revisa usuario, correo, contraseña (mínimo 12) y confirmación.');
            $this->logAuthWarning('register_validation_failed', ['email' => $email, 'username' => $username]);
            Response::redirect('/register');
        }

        try {
            $userId = (new AuthService())->register($username, $email, $password, $displayName ?: null);
            Auth::login($userId);
            UserSession::create($userId, session_id(), $request->ip(), $request->userAgent());
            $this->recordAuditEvent((int) $userId, 'register_success', 'user', (string) $userId, [
                'email' => $email,
                'username' => $username,
            ], $request->ip(), $request->userAgent());
            Session::flash('success', 'Cuenta creada correctamente.');
            Response::redirect('/dashboard');
        } catch (\RuntimeException $exception) {
            Session::flash('error', $exception->getMessage());
            $this->logAuthWarning('register_business_rule', ['email' => $email, 'message' => $exception->getMessage()]);
            Response::redirect('/register');
        } catch (\Throwable $exception) {
            Session::flash('error', 'Error de registro. Intenta nuevamente.');
            $this->logAuthWarning('register_unexpected_error', ['email' => $email, 'exception' => $exception::class]);
            Response::redirect('/register');
        }
    }

    public function showLogin(Request $request): void
    {
        $this->view('auth/login', ['title' => 'Iniciar sesión']);
    }

    public function login(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'CSRF inválido. Recarga la página e intenta de nuevo.');
            $this->logAuthWarning('login_csrf_invalid');
            Response::redirect('/login');
        }

        $email = trim((string)$request->post('email'));
        $password = (string)$request->post('password');

        $user = (new AuthService())->attempt($email, $password);

        if (!$user) {
            Session::flash('error', 'Credenciales inválidas.');
            $this->recordAuditEvent(null, 'login_failed', 'user', null, ['email' => $email], $request->ip(), $request->userAgent());
            $this->logAuthWarning('login_invalid_credentials', ['email' => $email]);
            Response::redirect('/login');
        }

        Auth::login((int)$user['id']);
        UserSession::create((int)$user['id'], session_id(), $request->ip(), $request->userAgent());
        $this->recordAuditEvent((int) $user['id'], 'login_success', 'user', (string) $user['id'], ['email' => $email], $request->ip(), $request->userAgent());
        Response::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'CSRF inválido. Recarga la página e intenta de nuevo.');
            $this->logAuthWarning('logout_csrf_invalid');
            Response::redirect('/dashboard');
        }

        $userId = Auth::id();
        $this->recordAuditEvent($userId, 'logout', 'user', $userId === null ? null : (string) $userId, [], $request->ip(), $request->userAgent());
        UserSession::revokeBySessionId(session_id());
        Auth::logout();
        Response::redirect('/');
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

    private function logAuthWarning(string $event, array $context = []): void
    {
        $safeContext = [];
        foreach ($context as $key => $value) {
            if (in_array($key, ['password', '_csrf_token', 'token'], true)) {
                continue;
            }
            $safeContext[$key] = $value;
        }

        error_log('[auth] ' . $event . ' ' . json_encode($safeContext, JSON_UNESCAPED_UNICODE));
    }
}
