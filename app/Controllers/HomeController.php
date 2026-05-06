<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Request;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $query = trim((string) $request->input('q', ''));
        $user = Auth::user();

        $this->view('home', [
            'title' => 'Browser - Buscador independiente',
            'query' => $query,
            'user' => $user,
        ]);
    }

    public function about(Request $request): void
    {
        $this->view('about', [
            'title' => 'Acerca de Browser',
        ]);
    }
}
