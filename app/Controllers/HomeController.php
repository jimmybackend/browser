<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Controller;
use Browser\Core\Request;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('home', [
            'title' => 'Browser - Plataforma independiente',
        ]);
    }
}
