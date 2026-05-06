<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Models\UserRole;

final class DashboardController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();

        $this->view('dashboard/index', [
            'title' => 'Dashboard',
            'user' => Auth::user(),
            'roles' => Auth::id() !== null ? UserRole::rolesForUser((int) Auth::id()) : [],
        ]);
    }
}
