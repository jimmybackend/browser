<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Core\Session;
use Browser\Models\UserPreference;

final class ProfileController extends Controller
{
    private const THEMES = ['system', 'light', 'dark'];
    private const LANGUAGES = ['es', 'en'];

    public function index(Request $request): void
    {
        $this->requireAuth();

        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        $userId = (int) $user['id'];
        $preferences = UserPreference::findByUserId($userId);

        if (!$preferences) {
            UserPreference::createDefault($userId);
            $preferences = UserPreference::findByUserId($userId);
        }

        $this->view('profile/index', [
            'title' => 'Perfil',
            'user' => $user,
            'preferences' => $preferences,
            'themes' => self::THEMES,
            'languages' => self::LANGUAGES,
        ]);
    }

    public function update(Request $request): void
    {
        $this->requireAuth();

        if (!Csrf::validate((string) $request->post('_csrf_token'))) {
            Session::flash('error', 'Token CSRF inválido.');
            Response::redirect('/profile');
        }

        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        $displayName = trim((string) $request->post('display_name'));
        $theme = trim((string) $request->post('theme'));
        $language = trim((string) $request->post('language'));
        $timezone = trim((string) $request->post('timezone'));
        $searchHistoryEnabled = $request->post('search_history_enabled') !== null ? 1 : 0;
        $emailNotificationsEnabled = $request->post('email_notifications_enabled') !== null ? 1 : 0;

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            Session::flash('error', 'El nombre visible es obligatorio y no puede exceder 120 caracteres.');
            Response::redirect('/profile');
        }

        if (!in_array($theme, self::THEMES, true)) {
            Session::flash('error', 'Tema inválido.');
            Response::redirect('/profile');
        }

        if (!in_array($language, self::LANGUAGES, true)) {
            Session::flash('error', 'Idioma inválido.');
            Response::redirect('/profile');
        }

        if ($timezone === '' || mb_strlen($timezone) > 60 || !in_array($timezone, timezone_identifiers_list(), true)) {
            Session::flash('error', 'Zona horaria inválida.');
            Response::redirect('/profile');
        }

        try {
            UserPreference::updateForUser((int) $user['id'], [
                'search_history_enabled' => $searchHistoryEnabled,
                'email_notifications_enabled' => $emailNotificationsEnabled,
                'theme' => $theme,
                'language' => $language,
                'timezone' => $timezone,
            ]);

            Session::flash('success', 'Perfil actualizado correctamente.');
        } catch (\Throwable $exception) {
            Session::flash('error', 'No se pudo actualizar el perfil.');
        }

        Response::redirect('/profile');
    }
}
