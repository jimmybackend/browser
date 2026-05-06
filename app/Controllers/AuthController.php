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
            $this->logAuthWarning('login_invalid_credentials', ['email' => $email]);
            Response::redirect('/login');
        }

        Auth::login((int)$user['id']);
        Response::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'CSRF inválido. Recarga la página e intenta de nuevo.');
            $this->logAuthWarning('logout_csrf_invalid');
            Response::redirect('/dashboard');
        }

        Auth::logout();
        Response::redirect('/');
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
