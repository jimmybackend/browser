<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Middleware\AdminMiddleware;

final class AdminController extends Controller
{
    public function index(Request $request): void
    {
        $this->requireAuth();
        AdminMiddleware::handle();

        $this->view('admin/index', [
            'title' => 'Administración',
        ]);
    }
}
