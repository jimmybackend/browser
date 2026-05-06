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
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/register');
        }

        $username = trim((string)$request->post('username'));
        $email = trim((string)$request->post('email'));
        $password = (string)$request->post('password');
        $displayName = trim((string)$request->post('display_name'));

        if (!Validator::required($username) || !Validator::email($email) || !Validator::minLength($password, 12)) {
            Session::flash('error', 'Revisa usuario, correo y contraseña. La contraseña debe tener mínimo 12 caracteres.');
            Response::redirect('/register');
        }

        try {
            $userId = (new AuthService())->register($username, $email, $password, $displayName ?: null);
            Auth::login($userId);
            Session::flash('success', 'Cuenta creada correctamente.');
            Response::redirect('/dashboard');
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo crear la cuenta. Revisa que el usuario o correo no exista.');
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
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/login');
        }

        $email = trim((string)$request->post('email'));
        $password = (string)$request->post('password');

        $user = (new AuthService())->attempt($email, $password);

        if (!$user) {
            Session::flash('error', 'Credenciales incorrectas.');
            Response::redirect('/login');
        }

        Auth::login((int)$user['id']);
        Response::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        if (!Csrf::validate((string)$request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/dashboard');
        }

        Auth::logout();
        Response::redirect('/');
    }
}
