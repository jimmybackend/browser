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
        $user = Auth::user();

        $this->view('home', [
            'title' => 'Browser - Plataforma independiente de búsqueda, correo y marketing',
            'homeData' => [
                'heroTitle' => 'Browser: búsqueda, correo y marketing en una plataforma independiente',
                'heroSubtitle' => 'Una base web segura para construir servicios propios de búsqueda, comunicación, clientes, campañas y leads.',
                'primaryCta' => [
                    'label' => 'Crear cuenta',
                    'url' => '/register',
                ],
                'secondaryCta' => [
                    'label' => 'Probar buscador',
                    'url' => '/search',
                ],
                'isAuthenticated' => $user !== null,
            ],
        ]);
    }
}
