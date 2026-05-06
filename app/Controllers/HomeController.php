<?php

declare(strict_types=1);

namespace Browser\Controllers;

use Browser\Core\Auth;
use Browser\Core\Controller;
use Browser\Core\Request;
use Browser\Services\SearchService;

final class HomeController extends Controller
{
    public function index(Request $request): void
    {
        $query = trim((string) $request->input('q', ''));
        if ($query !== '') {
            $searchData = (new SearchService())->search($query, $request);
            $this->view('search/index', [
                'title' => 'Buscador',
                'query' => $query,
                'results' => $searchData['results'],
                'directNavigation' => $searchData['directNavigation'],
            ]);

            return;
        }

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
