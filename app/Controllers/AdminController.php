<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Csrf;
use Browser\Core\Request;
use Browser\Core\Response;
use Browser\Middleware\AdminMiddleware;
use Browser\Models\AdminUser;
use Browser\Models\UserRole;

final class AdminController extends Controller
{
    public function index(Request $request): void
    {
        $this->guardAdmin();

        $this->view('admin/index', [
            'title' => 'Administración',
        ]);
    }

    public function users(Request $request): void
    {
        $this->guardAdmin();

        $users = AdminUser::listUsers(50);

        $this->view('admin/users', [
            'title' => 'Usuarios',
            'users' => $users,
        ]);
    }

    public function showUser(Request $request): void
    {
        $this->guardAdmin();

        $userId = (int) $request->input('id', 0);
        $user = AdminUser::findUserWithRoles($userId);

        if ($user === null) {
            Response::notFound();
            return;
        }

        $this->view('admin/user_show', [
            'title' => 'Detalle de usuario',
            'user' => $user,
        ]);
    }

    public function editUserRoles(Request $request): void
    {
        $this->guardAdmin();

        $userId = (int) $request->input('id', 0);
        $user = AdminUser::findUserWithRoles($userId);

        if ($user === null) {
            Response::notFound();
            return;
        }

        $this->view('admin/user_roles', [
            'title' => 'Roles de usuario',
            'user' => $user,
            'roles' => UserRole::allRoles(),
            'csrfToken' => Csrf::token(),
            'error' => $_SESSION['admin_roles_error'] ?? null,
            'success' => $_SESSION['admin_roles_success'] ?? null,
        ]);

        unset($_SESSION['admin_roles_error'], $_SESSION['admin_roles_success']);
    }

    public function updateUserRoles(Request $request): void
    {
        $this->guardAdmin();

        if (!Csrf::validate((string) $request->post('_csrf_token', ''))) {
            Response::forbidden();
            return;
        }

        $userId = (int) $request->post('user_id', 0);
        $roleId = (int) $request->post('role_id', 0);
        $action = (string) $request->post('action', '');

        if ($userId < 1 || $roleId < 1 || !in_array($action, ['assign', 'remove'], true)) {
            $_SESSION['admin_roles_error'] = 'Solicitud inválida.';
            Response::redirect('/admin/users/roles?id=' . $userId);
        }

        $user = AdminUser::findUserWithRoles($userId);

        if ($user === null) {
            Response::notFound();
            return;
        }

        $adminRole = UserRole::findRoleByName('admin');
        $currentUserId = Auth::id();

        if ($action === 'remove' && $adminRole !== null && $currentUserId === $userId && $roleId === (int) $adminRole['id']) {
            $adminCount = 0;
            foreach ($user['roles'] as $assignedRole) {
                if (($assignedRole['name'] ?? '') === 'admin') {
                    $adminCount++;
                }
            }

            if ($adminCount <= 1) {
                $_SESSION['admin_roles_error'] = 'No puedes quitarte tu último rol admin.';
                Response::redirect('/admin/users/roles?id=' . $userId);
            }
        }

        if ($action === 'assign') {
            UserRole::assignRole($userId, $roleId);
            $_SESSION['admin_roles_success'] = 'Rol asignado correctamente.';
        } else {
            UserRole::removeRole($userId, $roleId);
            $_SESSION['admin_roles_success'] = 'Rol removido correctamente.';
        }

        Response::redirect('/admin/users/roles?id=' . $userId);
    }

    private function guardAdmin(): void
    {
        $this->requireAuth();
        AdminMiddleware::handle();
    }
}
